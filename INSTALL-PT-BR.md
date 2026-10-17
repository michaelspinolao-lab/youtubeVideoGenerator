# VideoGenius — instalação pessoal

Esta edição implementa o fluxo **tema/roteiro → fila Laravel → clipes Pixabay → narração em português → MP4 privado para download**. É um fork separado do MaquinasPRO.

## Estado real

- Implementados: login Google restrito ao e-mail do proprietário em produção; formulário; fila e estados; narração PT-BR; montagem com vários clipes e áudio completo; download autorizado; exclusão de arquivos; limite mensal.
- Não implementados: publicação direta no YouTube, piloto automático, legendas, música, cobrança de assinaturas. Essas funções não são oferecidas na nova interface.
- Os arquivos e instruções n8n do projeto original são históricos. **Não exponha as rotas antigas de `routes/api.php`**. Esta edição não carrega esse arquivo e não depende de n8n.
- Testados localmente: build Vite, oito testes Laravel (23 asserções), montagem FFmpeg com clipes coloridos e áudio de cinco segundos, repetição dos clipes e proteção de URLs de download.
- Ainda dependem de validação com credenciais: login real Google, busca real Pixabay e geração Gemini/TTS. O container deve passar no workflow de CI e em um teste de inicialização antes do deploy. Não considerar esses testes como concluídos apenas porque o código compila.

## Configuração necessária

Não publique segredos no GitHub. Preencha no painel da hospedagem ou em `.env.personal` (ignorado pelo Git):

```dotenv
APP_NAME=VideoGenius
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SEU-DOMINIO
APP_KEY=base64:CHAVE_GERADA_PARA_ESTA_INSTALACAO
ALLOWED_LOGIN_EMAIL=SEU_EMAIL_GOOGLE
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://SEU-DOMINIO/auth/google/callback
PIXABAY_API_KEY=
GEMINI_API_KEY=
GEMINI_MODEL=
SESSION_SECURE_COOKIE=true
```

`APP_KEY`: gere uma vez com `php artisan key:generate --show` e guarde. Não troque a cada deploy.
No Google Cloud, configure um cliente OAuth Web com a URL de retorno exata. Apenas login, sem permissão de publicação YouTube. Escolha em `GEMINI_MODEL` um modelo disponível no seu projeto Google AI Studio. Roteiro próprio dispensa Gemini, mas ainda requer Pixabay e acesso ao serviço de voz gTTS. A voz depende de um serviço externo, sujeito a indisponibilidade; não há promessa de funcionamento ilimitado gratuito.

## Hospedagem

`Dockerfile.personal` inclui Apache na porta 10000, PHP 8.3, FFmpeg, Python e um worker. O banco SQLite, sessões e vídeos ficam em **`/var/www/html/storage`**, que precisa de volume/disco persistente. Uma única instância; não escale réplicas compartilhando SQLite. Faça backup do volume e da APP_KEY. Os arquivos de vídeo ocupam espaço até serem excluídos pelo painel.

No Render: criar um serviço Docker separado, apontar para `Dockerfile.personal`, health check `/up`, configurar variáveis acima e disco persistente nesse caminho. Não reutilizar o serviço ou banco do MaquinasPRO. Processamento e disco podem exigir plano pago: revisar o custo antes de criar. Não foi provisionado serviço pago por este trabalho.

Em servidor próprio com Docker e proxy HTTPS:

```sh
docker compose -f compose.personal.yaml up -d --build
```

O serviço local fica em `127.0.0.1:8080`. Configure o proxy HTTPS para esse endereço. A configuração antiga `docker-compose.yml` é somente desenvolvimento e contém padrões inseguros para produção.

## Verificação após configurar

1. Abrir `/up` e confirmar HTTP 200.
2. Entrar com o e-mail autorizado; testar recusa com outro e-mail.
3. Criar vídeo de 30 segundos com tema simples e roteiro curto; acompanhar os estados.
4. Baixar e conferir imagem, áudio inteiro e duração. A duração escolhida é aproximada e orienta o roteiro.
5. Criar outro vídeo sem roteiro para testar Gemini.
6. Reiniciar o serviço e conferir que banco e MP4 continuam presentes.
7. Para publicar, baixar o MP4, revisar e enviar pelo YouTube Studio. Nenhum upload automático acontece.

Cada solicitação aceita consome uma geração do limite mensal, mesmo se o provedor falhar. Falhas ficam visíveis; não há repetição automática que multiplique gastos. Os termos e a disponibilidade de uso dos clipes devem ser conferidos antes da publicação; a origem dos clipes fica registrada no arquivo privado `result.json` de cada vídeo.

## Desenvolvimento e testes

```sh
composer install
npm ci
npm run build
php vendor/bin/phpunit --filter VideoFlowTest
pip install -r scripts/requirements.txt
python -m unittest discover -s tests/python -v
```

O worker precisa de `php artisan queue:work --tries=1 --timeout=1800`; `retry_after` deve ser maior que 1800. No container pessoal isso já está configurado. Nunca configure `QUEUE_CONNECTION=sync` em produção.

O login demo compartilhado original é bloqueado em produção. As colunas de tokens são ocultas e criptografadas: se migrar um banco antigo com tokens em texto puro, reconecte as contas/limpe os tokens antigos antes de habilitar o acesso. Este fork foi preparado para uma instalação nova.
