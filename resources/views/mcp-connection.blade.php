<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conectar ao ChatGPT — VideoGenius</title>
<style>body{font:17px system-ui;background:#f5f5fa;color:#222;margin:0;padding:24px}main{max-width:600px;margin:30px auto;background:white;padding:24px;border-radius:20px}button{padding:14px;border:0;border-radius:10px;background:#5145cd;color:white;font-size:16px;cursor:pointer;margin:8px 0}textarea{box-sizing:border-box;width:100%;padding:12px;font:16px monospace;resize:vertical}a{color:#5145cd}p{line-height:1.5}.secondary{background:#555}</style></head><body><main>
<a href="/dashboard">← Voltar ao painel</a><h1>Conectar ao ChatGPT</h1>
<p>O plugin poderá criar e consultar seus vídeos e gerar links temporários de download. A chave vale por 90 dias e pode ser revogada aqui.</p>
@if($token)
<h2>Sua chave de conexão</h2><p>Copie e cole no campo de chave do plugin VideoGenius. Não envie esta chave em uma conversa.</p>
<textarea id="key" rows="3" readonly aria-label="Chave de conexão">{{ $token }}</textarea><button type="button" id="copy">Copiar chave</button>
<p>A chave só aparece nesta tela. Guarde-a antes de sair.</p>
<script>document.getElementById('copy').onclick=async function(){try{await navigator.clipboard.writeText(document.getElementById('key').value);this.textContent='Copiada!'}catch(e){document.getElementById('key').select();this.textContent='Selecione e copie a chave'}};</script>
@else
<p>{{ $active ? 'Você já tem uma chave ativa. Criar outra desativa a anterior.' : 'Crie uma chave para conectar o plugin à sua conta.' }}</p>
<form method="post" action="/integrations/chatgpt">@csrf<button type="submit">{{ $active ? 'Substituir chave de conexão' : 'Criar chave de conexão' }}</button></form>
@endif
@if($active)<form method="post" action="/integrations/chatgpt/revoke">@csrf<button class="secondary" type="submit">Revogar acesso do plugin</button></form>@endif
</main></body></html>
