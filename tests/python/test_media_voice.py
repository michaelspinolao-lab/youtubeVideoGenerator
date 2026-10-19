import os, sys, tempfile, unittest
from pathlib import Path
from unittest.mock import patch, MagicMock
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts'))
from media_pipeline import assemble_media, speech, VoiceError, run, duration
from inspect_media import inspect
from PIL import Image
from pillow_heif import from_pillow

class MediaVoiceTest(unittest.TestCase):
    def test_heic_mov_montage_and_narration(self):
        with tempfile.TemporaryDirectory() as tmp:
            d=Path(tmp);p=d/'iphone.heic'; normalized=d/'photo.jpg'
            from_pillow(Image.new('RGB',(200,300),'red')).save(p)
            self.assertEqual(inspect(p,normalized)['kind'],'photo')
            movie=d/'iphone.mov'
            run(['ffmpeg','-y','-f','lavfi','-i','color=c=blue:s=160x120:d=1','-f','lavfi','-i','sine=frequency=440:duration=1','-c:v','libx264','-threads','1','-c:a','aac',str(movie)])
            info=inspect(movie,d/'unused.jpg');self.assertTrue(info['has_audio'])
            items=[dict(path=str(normalized),kind='photo'),dict(path=str(movie),kind='video',has_audio=True)]
            self.assertAlmostEqual(assemble_media(items,None,d/'final.mp4',d,2),2)
            probe=run(['ffprobe','-v','error','-show_streams','-of','json',str(d/'final.mp4')])
            self.assertIn('1280',probe)
            audio=d/'voice.mp3';run(['ffmpeg','-y','-f','lavfi','-i','sine=frequency=600:duration=2',str(audio)])
            self.assertAlmostEqual(assemble_media(items,audio,d/'spoken.mp4',d,3,'horizontal'),duration(audio))
    def test_paid_audio_reused_and_uncertain_request_not_repeated(self):
        with tempfile.TemporaryDirectory() as tmp, patch.dict(os.environ,{'ELEVENLABS_API_KEY':'test-secret'}):
            d=Path(tmp);fixture=d/'fixture.mp3';run(['ffmpeg','-y','-f','lavfi','-i','sine=frequency=400:duration=1',str(fixture)])
            response=MagicMock();response.status_code=200;response.iter_content.return_value=[fixture.read_bytes()]
            response.__enter__.return_value=response
            with patch('media_pipeline.requests.post',return_value=response) as post:
                a=speech('Olá','voice123',d);self.assertTrue(a.is_file());self.assertEqual(speech('Olá','voice123',d),a);self.assertEqual(post.call_count,1)
            with patch('media_pipeline.requests.post',side_effect=TimeoutError('secret')) as post:
                for i in range(2):
                    with self.assertRaises(VoiceError) as error: speech('Outra fala','voice123',d)
                    self.assertEqual(error.exception.code,'elevenlabs_uncertain')
                self.assertEqual(post.call_count,1)
    def test_invalid_media(self):
        with tempfile.TemporaryDirectory() as tmp:
            d=Path(tmp);p=d/'fake.mov';p.write_text('invalid')
            with self.assertRaises(Exception):inspect(p,d/'out.jpg')
