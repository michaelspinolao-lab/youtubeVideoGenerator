# Plugin privado VideoGenius

Abra /integrations/chatgpt após entrar com o Google do proprietário. Crie uma chave, copie para o campo de autenticação do plugin e nunca envie a chave no chat. A chave é mostrada uma única vez, dura 90 dias e fica armazenada apenas como SHA-256. Substituir ou revogar desativa imediatamente o acesso antigo.

Endpoint: https://aripuango.info/mcp (Streamable HTTP, JSON, autenticação Bearer obrigatória).

Ferramentas: account_status, list_videos, get_video, create_video e get_video_download. A criação exige roteiro próprio e request_id único; repetir a mesma solicitação com o mesmo identificador não consome outra cota. Não reutilize o identificador para outro conteúdo. A cota mensal existente continua valendo, inclusive tentativas que falhem na geração. Consulte account_status antes de gerar. O roteiro próprio dispensa Gemini, mas Pixabay e o serviço de voz precisam estar disponíveis.

Os vídeos são montados com clipes do Pixabay e narração; esta integração não sintetiza filmagens novas nem publica no YouTube. URLs de download assinadas expiram em 30 minutos e podem ser usadas por quem tiver o link até a expiração. A revogação de uma chave não invalida links já emitidos; eles expiram normalmente.

O login Google e os cookies do site não autorizam o endpoint MCP. Os tokens ficam vinculados ao usuário e ao e-mail proprietário configurado no servidor. Alterar ALLOWED_LOGIN_EMAIL invalida o acesso de um proprietário anterior.

Pacote privado: integrations/videogenius. Não incluir credenciais no ZIP.

## Conexão pelo ChatGPT web (OAuth)

No ChatGPT web: **Plugins → Adicionar → Adicionar servidor MCP personalizado**.
Use o nome `VideoGenius Web`, a URL `https://aripuango.info/mcp` e autenticação **OAuth**.
O servidor publica os metadados automaticamente e permite registro dinâmico do cliente.
Após criar, entre com a conta Google do proprietário e aprove a conexão na tela do VideoGenius.
Não coloque o segredo do Google nem a chave `vg_` nas configurações OAuth do ChatGPT.
A disponibilidade no iPhone deve ser confirmada com essa conexão instalada e um teste de `account_status` no dispositivo.

O fluxo utiliza códigos de uso único (5 minutos), PKCE S256, consentimento com proteção CSRF,
validação exata do callback registrado e da audiência `/mcp`. Os únicos callbacks aceitos são
os endpoints HTTPS documentados do ChatGPT (estável ou específico da conexão).
Tokens de acesso duram uma hora; refresh tokens são rotacionados, com revogação da conexão
em caso de reutilização. A autorização dura no máximo 90 dias. Somente hashes dos tokens
são armazenados. A página `/integrations/chatgpt` permite revogar tanto chaves desktop
quanto autorizações OAuth. A conexão desktop por chave continua disponível.

A correção OAuth não altera o processamento dos vídeos nem resolve falhas dos provedores.


## Vídeos com foto própria (v1.1)
- Envie JPG/PNG até 5 MB em /photos, com login. As imagens ficam privadas.
- No ChatGPT, consulte list_photos e envie photo_id em create_video.
- Com photo_id e custom_script, Pixabay e Gemini não são necessários.
- Saída vertical 720×1280, foto original com zoom suave e narração em português.
- Não anima a pessoa, não gera filmagens novas e não inclui música ou legendas.
- Fotos anexadas ao chat podem ser enviadas pela ferramenta upload_photo após atualizar os metadados da conexão (v1.2).
- Atualize as ferramentas da conexão após instalar a nova versão do servidor.
- Falhas agora retornam categorias específicas, sem revelar chaves dos provedores.
- Teste local sem rede/provedores: python scripts/test_photo_generation.py (requer FFmpeg).


## Fluxo completo no ChatGPT (v1.2)
1. Use upload_photo com o anexo fornecido pelo usuário. O host resolve file em download_url/file_id/mime_type/file_name via openai/fileParams.
2. Escreva apenas a narração em custom_script, usando o contexto da foto e sem inventar preço, tecido, disponibilidade ou promessas.
3. Use prepare_video para validar a foto, apresentar o roteiro e informar quota_cost. Esta consulta e o upload não consomem cota.
4. Quando a geração estiver autorizada, chame create_video com photo_id e request_id estável. Um vídeo novo consome uma unidade.
5. Consulte get_video até ready ou failed. A duração final acompanha a narração.
6. Em caso de falha, consulte failure_reason. retry_video usa o mesmo vídeo e roteiro sem nova cota, com request_id estável por tentativa autorizada. Não faça ciclos de tentativas automáticas.
7. Use get_video_download quando pronto. O link expira em 30 minutos.

Segurança: imagens privadas, isolamento por usuário, JPG/PNG até 5 MB/32 MP,
downloads apenas de hosts de anexos OpenAI, sem redirecionamentos, limites de leitura,
erros sem URLs assinadas e deduplicação por conteúdo na conta. Upload não aceita
URLs de redes internas, caminhos do servidor ou fotos arbitrárias de outras contas.

Após publicar: Plugins → VideoGenius Web → conexão → Refresh/Atualizar.
Inicie uma conversa nova e confirme que upload_photo, prepare_video, retry_video e
list_photos aparecem. A lista antiga de cinco ferramentas fica em cache no host;
alterar apenas a versão do pacote não substitui a atualização dos metadados MCP.
