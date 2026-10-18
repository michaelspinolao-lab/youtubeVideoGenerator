"""Provider search regression tests without external calls or credentials."""
import importlib.util
import os
from pathlib import Path
import unittest
from unittest.mock import Mock, patch
import requests
spec = importlib.util.spec_from_file_location('generate', Path(__file__).with_name('generate.py'))
generator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(generator)

class SearchTest(unittest.TestCase):
    def response(self, hits, status=200, text=''):
        response = Mock(status_code=status, text=text)
        response.json.return_value = {'hits': hits}
        if status >= 400:
            response.raise_for_status.side_effect = requests.HTTPError('SECRET must not escape')
        return response

    @patch.dict(os.environ, {'PIXABAY_API_KEY':'test'})
    def test_long_topic_falls_back_to_short_portuguese_phrase(self):
        found = [{'id': 123, 'videos': {'medium': {'url':'https://cdn.pixabay.com/fixture.mp4'}}}]
        with patch.object(generator.requests, 'get', side_effect=[self.response([]), self.response(found)]) as get:
            self.assertEqual(found, generator.search_stock('Moda feminina: nova coleção inspirada em vestido curto listrado vinho e branco.'))
            self.assertEqual('Moda feminina', get.call_args_list[1].kwargs['params']['q'])
            self.assertEqual('pt', get.call_args_list[1].kwargs['params']['lang'])

    @patch.dict(os.environ, {'PIXABAY_API_KEY':'test'})
    def test_missing_hits_never_substitutes_unrelated_random_stock(self):
        with patch.object(generator.requests, 'get', return_value=self.response([])) as get:
            with self.assertRaises(generator.GenerationError) as err:
                generator.search_stock('inexistente')
            self.assertEqual('no_stock_footage', err.exception.code)
            self.assertEqual(2, get.call_count)
            self.assertTrue(all(c.kwargs['params']['q']=='inexistente' for c in get.call_args_list))

    @patch.dict(os.environ, {'PIXABAY_API_KEY':'test'})
    def test_rejected_key_and_rate_limit_do_not_retry_or_leak(self):
        for status, text, code in [(400,'Invalid API key SECRET','stock_key_rejected'),(429,'SECRET','stock_rate_limited')]:
            with self.subTest(code=code), patch.object(generator.requests, 'get', return_value=self.response([],status,text)) as get:
                with self.assertRaises(generator.GenerationError) as err:
                    generator.search_stock('Moda')
                self.assertEqual(code, err.exception.code)
                self.assertEqual(1,get.call_count)
                self.assertNotIn('SECRET',str(err.exception))

if __name__ == '__main__': unittest.main()
