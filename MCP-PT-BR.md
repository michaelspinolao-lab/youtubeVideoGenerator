# Plugin privado VideoGenius

Abra /integrations/chatgpt após entrar com o Google do proprietário. Crie uma chave, copie para o campo de autenticação do plugin e nunca envie a chave no chat. A chave é mostrada uma única vez, dura 90 dias e fica armazenada apenas como SHA-256. Substituir ou revogar desativa imediatamente o acesso antigo.

Endpoint: https://aripuango.info/mcp (Streamable HTTP, JSON, autenticação Bearer obrigatória).

Ferramentas: account_status, list_videos, get_video, create_video e get_video_download. A criação exige roteiro próprio e request_id único; repetir a mesma solicitação com o mesmo identificador não consome outra cota. Não reutilize o identificador para outro conteúdo. A cota mensal existente continua valendo, inclusive tentativas que falhem na geração. Consulte account_status antes de gerar. O roteiro próprio dispensa Gemini, mas Pixabay e o serviço de voz precisam estar disponíveis.

Os vídeos são montados com clipes do Pixabay e narração; esta integração não sintetiza filmagens novas nem publica no YouTube. URLs de download assinadas expiram em 30 minutos e podem ser usadas por quem tiver o link até a expiração. A revogação de uma chave não invalida links já emitidos; eles expiram normalmente.

O login Google e os cookies do site não autorizam o endpoint MCP. Os tokens ficam vinculados ao usuário e ao e-mail proprietário configurado no servidor. Alterar ALLOWED_LOGIN_EMAIL invalida o acesso de um proprietário anterior.

Pacote privado: integrations/videogenius. Não incluir credenciais no ZIP.
