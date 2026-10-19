"""Validate local uploads by content, normalize stills and report safe metadata."""
import json, math, subprocess, sys, warnings
from pathlib import Path
from PIL import Image, ImageOps
from pillow_heif import register_heif_opener
register_heif_opener()
Image.MAX_IMAGE_PIXELS = 48000000
warnings.simplefilter('error', Image.DecompressionBombWarning)

def inspect(source, normalized):
    source = Path(source)
    if source.stat().st_size > 100*1024*1024: raise ValueError('size')
    try:
        image = Image.open(source)
    except Exception:
        image = None
    if image is not None:
        with image:
            if image.format not in ('JPEG', 'PNG', 'HEIF', 'WEBP') or source.stat().st_size > 20*1024*1024: raise ValueError('image')
            w, h = image.size
            if min(w,h)<100 or max(w,h)>12000 or w*h>48000000: raise ValueError('dimensions')
            image = ImageOps.exif_transpose(image).convert('RGB')
            image.thumbnail((2560,2560)); image.save(normalized, 'JPEG', quality=92)
            w,h=image.size
            return dict(kind='photo',mime_type='image/jpeg',width=w,height=h,duration=None,has_audio=False)
    result = subprocess.run(['ffprobe','-v','error','-protocol_whitelist','file,pipe','-format_whitelist','mov,matroska,webm',
        '-show_streams','-show_format','-of','json',str(source)],capture_output=True,timeout=30,check=True)
    data=json.loads(result.stdout)
    videos=[s for s in data['streams'] if s.get('codec_type')=='video' and not s.get('disposition',{}).get('attached_pic')]
    if len(videos)!=1: raise ValueError('video')
    stream=videos[0]; w,h=stream['width'],stream['height']; seconds=float(data['format']['duration'])
    if stream.get('codec_name') not in ('h264','hevc','vp8','vp9','av1','mpeg4') or not math.isfinite(seconds) or not 0<seconds<=600 or min(w,h)<100 or max(w,h)>4096 or w*h>9000000: raise ValueError('video limits')
    mime='video/webm' if 'matroska' in data['format']['format_name'] else 'video/mp4'
    if str(data['format'].get('tags',{}).get('major_brand','')).strip()=='qt': mime='video/quicktime'
    return dict(kind='video',mime_type=mime,width=w,height=h,duration=seconds,has_audio=any(s.get('codec_type')=='audio' for s in data['streams']))

if __name__=='__main__':
    try: print(json.dumps(inspect(sys.argv[1],sys.argv[2])))
    except Exception: print(json.dumps({'error':'invalid_media'})); sys.exit(1)
