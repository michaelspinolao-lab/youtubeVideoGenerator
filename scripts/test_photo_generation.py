"""Offline integration test: real FFmpeg, synthetic narration, no provider calls."""
import importlib.util
import json
import subprocess
import sys
import tempfile
import types
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('generate', Path(__file__).with_name('generate.py'))
generator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(generator)

class Voice:
    def __init__(self, **kwargs): pass
    def save(self, path):
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i',
                        'sine=frequency=440:duration=2', path], check=True)

class PhotoGenerationTest(unittest.TestCase):
    def test_photo_renders_without_stock_or_gemini(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            image = root/'input.png'
            subprocess.run(['ffmpeg','-v','error','-f','lavfi','-i',
                            'color=c=blue:s=400x600','-frames:v','1','-threads','1',str(image)],check=True)
            with patch.dict(sys.modules, {'gtts': types.SimpleNamespace(gTTS=Voice)}),                  patch.object(generator.requests, 'get', side_effect=AssertionError('Unexpected external call')),                  patch.object(generator.requests, 'post', side_effect=AssertionError('Unexpected external call')):
                generator.main({'photo':str(image), 'directory':str(root/'out'),
                                'script':'Teste local.', 'topic':'Teste', 'duration':30})
            output = root/'out/final.mp4'
            self.assertTrue(output.is_file())
            streams = json.loads(subprocess.check_output(['ffprobe','-v','error',
                '-show_streams','-of','json',str(output)]))['streams']
            video = next(s for s in streams if s['codec_type']=='video')
            self.assertEqual((720,1280),(video['width'],video['height']))
            self.assertTrue(any(s['codec_type']=='audio' for s in streams))
            self.assertAlmostEqual(2, generator.duration(output), delta=0.2)

    def test_missing_photo_has_specific_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaises(generator.GenerationError) as error:
                generator.main({'photo':tmp+'/missing.jpg','directory':tmp,'script':'Teste'})
            self.assertEqual('photo_missing', error.exception.code)

if __name__ == '__main__': unittest.main()
