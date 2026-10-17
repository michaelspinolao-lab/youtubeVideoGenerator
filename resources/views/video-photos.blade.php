<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fotos — VideoGenius</title>
<style>body{font:17px system-ui;background:#f3f4f6;color:#172033;margin:0}main{max-width:720px;margin:auto;padding:24px}form,article{padding:20px;background:white;border-radius:16px;margin:16px 0}button{background:#4338ca;color:white;border:0;border-radius:8px;padding:14px;font:inherit;margin-top:16px}img{max-width:100%;max-height:300px}code{overflow-wrap:anywhere}.error{color:#b91c1c}input{max-width:100%}</style></head>
<body><main><a href="/videos/create">Criar vídeo</a><h1>Suas fotos para vídeos</h1><p>Envie uma foto JPG ou PNG de até 5 MB. Depois peça ao VideoGenius no ChatGPT para usar a foto mais recente.</p><p>O vídeo usa a foto original com zoom suave e narração. A pessoa não será animada.</p>
@if(session('photo_id'))<p role="status">Foto enviada! ID: <code>{{session('photo_id')}}</code></p>@endif
@foreach($errors->all() as $error)<p class="error">{{$error}}</p>@endforeach
<form method="post" enctype="multipart/form-data" action="/photos">@csrf<label>Escolha a foto<br><input required type="file" name="photo" accept="image/jpeg,image/png"></label><br><button>Enviar foto</button></form>
@foreach($photos as $photo)<article><img src="/photos/{{$photo->id}}" alt="Foto enviada para vídeo"><p>ID: <code>{{$photo->id}}</code></p></article>@endforeach
</main></body></html>
