"""Private worker: JSON stdin, progress JSON stdout. No credentials in arguments/logs."""
import json, os, subprocess, sys
from pathlib import Path
from urllib.parse import urlparse
import requests

def progress(status):
    print(json.dumps({'status': status}), flush=True)

def run(args):
    return subprocess.run(args, check=True, capture_output=True, text=True, timeout=600).stdout

def duration(path):
    return float(run(['ffprobe','-v','error','-show_entries','format=duration','-of','default=nw=1:nk=1',str(path)]))

def assemble(clips, audio, output, directory):
    seconds = duration(audio)
    if not 0 < seconds <= 900 or not clips:
        raise ValueError('Invalid audio duration or empty clips')
    normalized = []
    for i, clip in enumerate(clips):
        dest = directory / f'part-{i}.mp4'
        run(['ffmpeg','-y','-i',str(clip),'-t','12','-an','-vf','scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720,setsar=1,fps=25','-c:v','libx264','-preset','fast','-pix_fmt','yuv420p','-threads','2',str(dest)])
        normalized.append(dest)
    playlist = directory/'clips.txt'
    playlist.write_text(''.join(f"file '{x.name}'\n" for x in normalized))
    sequence = directory/'sequence.mp4'
    run(['ffmpeg','-y','-f','concat','-safe','1','-i',str(playlist),'-c','copy',str(sequence)])
    run(['ffmpeg','-y','-stream_loop','-1','-i',str(sequence),'-i',str(audio),'-map','0:v:0','-map','1:a:0','-t',str(seconds),'-c:v','copy','-c:a','aac','-movflags','+faststart',str(output)])
    if abs(duration(output)-seconds)>1:
        raise ValueError('Audio/video duration mismatch')
    return seconds

def download(url, dest):
    parsed = urlparse(url)
    if parsed.scheme != 'https' or parsed.hostname not in ('cdn.pixabay.com','videos.pexels.com'):
        raise ValueError('Unexpected media host')
    with requests.get(url,stream=True,timeout=(10,60),allow_redirects=False) as response:
        response.raise_for_status()
        if response.status_code != 200: raise ValueError('Unexpected download response')
        size=0
        with dest.open('wb') as f:
            for chunk in response.iter_content(65536):
                size += len(chunk)
                if size > 100*1024*1024: raise ValueError('Media exceeds 100MB')
                f.write(chunk)

def main(data):
    directory=Path(data['directory']); directory.mkdir(parents=True, exist_ok=True)
    progress('generating_script')
    script=(data.get('script') or '').strip()
    if not script:
        model=os.environ['GEMINI_MODEL']
        if not model or any(c not in 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._' for c in model):
            raise ValueError('Invalid model')
        response=requests.post(f'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',headers={'x-goog-api-key':os.environ['GEMINI_API_KEY']},json={'contents':[{'parts':[{'text':f"Escreva somente a narração em português brasileiro, sem markdown, para um vídeo sobre: {data['topic']}. Aproximadamente {int(data['duration'])*2} palavras. Não invente estatísticas nem prometa resultados garantidos."}]}]},timeout=90)
        response.raise_for_status()
        script=''.join(x.get('text','') for x in response.json()['candidates'][0]['content']['parts']).strip()
    if not script or len(script)>10000: raise ValueError('Invalid script')
    progress('fetching_visuals')
    response=requests.get('https://pixabay.com/api/videos/',params={'key':os.environ['PIXABAY_API_KEY'],'q':data['topic'][:100],'per_page':5,'safesearch':'true'},timeout=40)
    response.raise_for_status()
    clips=[]; sources=[]
    for i,hit in enumerate(response.json().get('hits',[])[:5]):
        variant=hit.get('videos',{}).get('medium') or hit.get('videos',{}).get('small')
        if not variant: continue
        dest=directory/f'clip-{i}.mp4'; download(variant['url'],dest);clips.append(dest)
        sources.append({'id':hit['id'],'page':hit.get('pageURL'),'author':hit.get('user')})
    if not clips: raise ValueError('No stock footage found; choose a simpler topic')
    progress('generating_voiceover')
    from gtts import gTTS
    audio=directory/'voice.mp3'
    gTTS(text=script,lang='pt',tld='com.br',timeout=(10,60)).save(str(audio))
    progress('assembling_video')
    seconds=assemble(clips,audio,directory/'final.mp4',directory)
    (directory/'result.json').write_text(json.dumps({'script':script,'duration':seconds,'sources':sources},ensure_ascii=False))
    for pattern in ('clip-*.mp4','part-*.mp4','sequence.mp4','clips.txt'):
        for path in directory.glob(pattern): path.unlink()

if __name__=='__main__':
    try: main(json.load(sys.stdin))
    except Exception:
        # Do not leak provider URLs, API keys or user scripts into queue logs.
        print('Generation failed: check providers, topic and worker dependencies.',file=sys.stderr)
        sys.exit(1)
