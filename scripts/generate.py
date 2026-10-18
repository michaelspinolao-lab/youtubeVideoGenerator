"""Private worker: JSON stdin, progress JSON stdout. No credentials in arguments/logs."""
import json, os, re, subprocess, sys
from pathlib import Path
from urllib.parse import urlparse
import requests

STAGE = 'initializing'

class GenerationError(Exception):
    def __init__(self, code):
        self.code = code

def progress(status):
    global STAGE
    STAGE = status
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

def search_stock(topic):
    # Try the original search, then shorter Portuguese phrases from the same topic.
    # Never fall back to unrelated/random footage when a specific topic has no hits.
    topic = topic.strip()
    words = re.findall(r"[^\W_]+", re.split(r'[:.!?;]', topic, maxsplit=1)[0], re.UNICODE)
    candidates = [(topic[:100], 'en')]
    for count in (4, 2, 1):
        query = ' '.join(words[:count])[:100]
        if query and (query, 'pt') not in candidates:
            candidates.append((query, 'pt'))
    for attempt, (query, language) in enumerate(candidates, 1):
        response = requests.get('https://pixabay.com/api/videos/', params={
            'key': os.environ['PIXABAY_API_KEY'], 'q': query, 'lang': language,
            'per_page': 5, 'safesearch': 'true'}, timeout=40)
        if response.status_code in (401, 403) or (response.status_code == 400 and 'api key' in response.text.lower()):
            raise GenerationError('stock_key_rejected')
        if response.status_code == 429:
            raise GenerationError('stock_rate_limited')
        response.raise_for_status()
        hits = response.json().get('hits', [])
        print(json.dumps({'event': 'stock_search', 'attempt': attempt, 'matches': len(hits)}), flush=True)
        if hits:
            return hits[:5]
    raise GenerationError('no_stock_footage')

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
    clips=[]; sources=[]
    photo = data.get('photo')
    if photo:
        if not Path(photo).is_file():
            raise GenerationError('photo_missing')
    else:
        progress('fetching_visuals')
        clips=[]; sources=[]
        for i,hit in enumerate(search_stock(data['topic'])):
            variant=hit.get('videos',{}).get('medium') or hit.get('videos',{}).get('small')
            if not variant: continue
            dest=directory/f'clip-{i}.mp4'; download(variant['url'],dest);clips.append(dest)
            sources.append({'id':hit['id'],'page':hit.get('pageURL'),'author':hit.get('user')})
        if not clips: raise GenerationError('no_stock_footage')
    progress('generating_voiceover')
    from gtts import gTTS
    audio=directory/'voice.mp3'
    gTTS(text=script,lang='pt',tld='com.br',timeout=(10,60)).save(str(audio))
    progress('assembling_video')
    if photo:
        seconds = duration(audio)
        if not 0 < seconds <= 900:
            raise GenerationError('invalid_audio')
        frames = int(seconds * 25) + 1
        # Fit the whole photograph first; a subtle centered zoom adds camera motion.
        vf = ("scale=720:1280:force_original_aspect_ratio=decrease,"
              "pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=white,setsar=1,"
              f"zoompan=z='1+0.025*on/{frames}':x='iw/2-iw/zoom/2':"
              f"y='ih/2-ih/zoom/2':d={frames}:s=720x1280:fps=25,format=yuv420p")
        run(['ffmpeg','-y','-i',str(photo),'-i',str(audio),'-vf',vf,
             '-map','0:v:0','-map','1:a:0','-t',str(seconds),'-c:v','libx264',
             '-preset','fast','-threads','2','-c:a','aac','-movflags','+faststart',
             str(directory/'final.mp4')])
        if abs(duration(directory/'final.mp4') - seconds) > 1:
            raise GenerationError('invalid_output')
    else:
        seconds=assemble(clips,audio,directory/'final.mp4',directory)
    (directory/'result.json').write_text(json.dumps({'script':script,'duration':seconds,'sources':sources},ensure_ascii=False))
    for pattern in ('clip-*.mp4','part-*.mp4','sequence.mp4','clips.txt'):
        for path in directory.glob(pattern): path.unlink()

if __name__=='__main__':
    try: main(json.load(sys.stdin))
    except Exception as error:
        # Only stable codes leave the worker: never include exception text or provider URLs.
        code = error.code if isinstance(error, GenerationError) else {
            'generating_script': 'script_provider_failed',
            'fetching_visuals': 'stock_provider_failed',
            'generating_voiceover': 'voice_provider_failed',
            'assembling_video': 'render_failed',
        }.get(STAGE, 'worker_failed')
        print(json.dumps({'error_code': code, 'stage': STAGE}), flush=True)
        sys.exit(1)
