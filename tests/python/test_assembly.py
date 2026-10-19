import importlib.util, pathlib, subprocess, tempfile, unittest, sys
sys.path.insert(0,str(pathlib.Path(__file__).parents[2]/"scripts"))
spec=importlib.util.spec_from_file_location('generate',pathlib.Path(__file__).parents[2]/'scripts/generate.py'); module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class AssemblyTest(unittest.TestCase):
 def test_multiple_clips_preserve_entire_narration(self):
  with tempfile.TemporaryDirectory() as tmp:
   p=pathlib.Path(tmp);clips=[]
   for i,color in enumerate(['red','blue']):
    clip=p/f'input{i}.mp4';module.run(['ffmpeg','-y','-f','lavfi','-i',f'color=c={color}:s=160x90:d=1','-c:v','libx264','-threads','1',str(clip)]);clips.append(clip)
   audio=p/'voice.wav';module.run(['ffmpeg','-y','-f','lavfi','-i','sine=frequency=440:duration=5',str(audio)])
   output=p/'out.mp4';module.assemble(clips,audio,output,p)
   self.assertAlmostEqual(module.duration(output),5,delta=.2)
   frames=[]
   for t in [.5,1.5,2.5]:
    rgb=subprocess.check_output(['ffmpeg','-v','error','-ss',str(t),'-i',str(output),'-frames:v','1','-vf','scale=1:1','-f','rawvideo','-pix_fmt','rgb24','-'])
    frames.append(rgb)
   self.assertGreater(frames[0][0],frames[0][2]);self.assertGreater(frames[1][2],frames[1][0]);self.assertGreater(frames[2][0],frames[2][2])
 def test_download_rejects_private_or_unknown_host(self):
  for url in ['http://localhost/test','https://127.0.0.1/test','https://example.com/test']:
   with self.assertRaises(ValueError):module.download(url,pathlib.Path('/tmp/never-written'))
if __name__=='__main__':unittest.main()
