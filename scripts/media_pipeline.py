"""Own-media montage and optional ElevenLabs speech. No secrets in diagnostics."""
import hashlib, json, os, re, subprocess
from pathlib import Path
import requests

class VoiceError(Exception):
    def __init__(self, code): self.code=code

def run(args):
    return subprocess.run(args,capture_output=True,check=True,timeout=600,text=True).stdout

def duration(path):
    return float(run(['ffprobe','-v','error','-show_entries','format=duration','-of','default=nw=1:nk=1',str(path)]))

def speech(script, voice_id, directory):
    if not re.fullmatch(r'[a-zA-Z0-9_-]{1,100}', voice_id or '') or not os.environ.get('ELEVENLABS_API_KEY'):
        raise VoiceError('elevenlabs_not_configured')
    signature=hashlib.sha256(json.dumps([script,voice_id,'eleven_multilingual_v2']).encode()).hexdigest()
    audio=directory/f'elevenlabs-{signature}.mp3'
    if audio.is_file() and 0<duration(audio)<=900: return audio
    pending=directory/f'elevenlabs-{signature}.pending'
    # Do not repeat a potentially charged request after an interrupted connection.
    if pending.exists(): raise VoiceError('elevenlabs_uncertain')
    pending.write_text('Request started; do not automatically charge again.')
    temp=directory/f'elevenlabs-{signature}.tmp.mp3'
    try:
        with requests.post(f'https://api.elevenlabs.io/v1/text-to-speech/{voice_id}',
            params={'output_format':'mp3_44100_128'},headers={'xi-api-key':os.environ['ELEVENLABS_API_KEY']},
            json={'text':script,'model_id':'eleven_multilingual_v2'},stream=True,timeout=(10,120),allow_redirects=False) as response:
            if response.status_code in (401,403,404,422,429):
                pending.unlink(missing_ok=True)
                raise VoiceError('elevenlabs_rejected')
            if response.status_code!=200: raise VoiceError('elevenlabs_uncertain')
            size=0
            with temp.open('wb') as f:
                for chunk in response.iter_content(65536):
                    size+=len(chunk)
                    if size>30*1024*1024: raise VoiceError('elevenlabs_uncertain')
                    f.write(chunk)
        if not 0<duration(temp)<=900: raise VoiceError('elevenlabs_uncertain')
        temp.replace(audio);pending.unlink(missing_ok=True)
        return audio
    except VoiceError: raise
    except Exception: raise VoiceError('elevenlabs_uncertain')
    finally: temp.unlink(missing_ok=True)

def assemble_media(media, audio, output, directory, seconds, aspect='vertical', original=True):
    if not media: raise ValueError('empty media')
    seconds=duration(audio) if audio else float(seconds)
    if not 0<seconds<=900: raise ValueError('duration')
    w,h=(1280,720) if aspect=='horizontal' else (720,1280)
    segment=seconds/len(media); parts=[]
    for i,item in enumerate(media):
        path=Path(item['path'])
        if not path.is_file(): raise ValueError('missing media')
        part=directory/f'part-{i}.mp4'
        args=['ffmpeg','-y','-protocol_whitelist','file,pipe']
        args+=['-loop','1','-i',str(path)] if item['kind']=='photo' else ['-stream_loop','-1','-i',str(path)]
        keep=not audio and original and item.get('has_audio') and item['kind']=='video'
        if not keep: args+=['-f','lavfi','-i','anullsrc=r=48000:cl=stereo']
        args+=['-map','0:v:0','-map','0:a:0' if keep else '1:a:0','-t',str(segment),
            '-vf',f'scale={w}:{h}:force_original_aspect_ratio=decrease,pad={w}:{h}:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=25',
            '-af','aresample=48000,apad','-ac','2','-ar','48000','-c:a','aac',
            '-c:v','libx264','-threads','2','-preset','fast','-pix_fmt','yuv420p',str(part)]
        run(args);parts.append(part)
    playlist=directory/'clips.txt';playlist.write_text(''.join(f"file '{p.name}'\n" for p in parts))
    sequence=directory/'sequence.mp4';run(['ffmpeg','-y','-f','concat','-safe','1','-i',str(playlist),'-c','copy',str(sequence)])
    args=['ffmpeg','-y','-i',str(sequence)]
    if audio: args+=['-i',str(audio),'-map','0:v:0','-map','1:a:0']
    args+=['-t',str(seconds),'-c:v','copy','-c:a','aac','-movflags','+faststart',str(output)]
    run(args)
    if abs(duration(output)-seconds)>1: raise ValueError('duration mismatch')
    return seconds
