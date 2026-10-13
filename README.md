> **Atualização:** este projeto agora usa Supabase (Postgres), Backblaze B2 e Vercel. Veja `MIGRACAO_SUPABASE_B2_VERCEL.md`. As instruções de MySQL abaixo são históricas.

# Cartão digital 100% PHP + MySQL

Versão sem Supabase, sem JavaScript de front-end e sem dependências externas:
tudo — página pública e painel admin — é renderizado pelo próprio PHP,
com MySQL como banco de dados.

## ⚠️ Sobre hospedar isso no Vercel

Esse era o plano original, mas vale um alerta antes de você tentar:
**Vercel não tem suporte oficial a PHP.** Existe um runtime da comunidade
(`vercel-php`) que funciona, mas com limitações importantes para este
projeto: o sistema de arquivos é temporário a cada execução (então nada
pode depender de gravar arquivo em disco) e você precisaria de um MySQL
externo, já que a Vercel não hospeda banco de dados (ex.: PlanetScale,
Railway, Aiven). O login já foi feito pensando nisso — ele usa cookie +
tabela no banco em vez do sistema de sessão em arquivo do PHP — então
tecnicamente funcionaria, mas é uma configuração não trivial e fora do
caminho comum da Vercel.

**O caminho mais simples e mais barato para PHP + MySQL de verdade é uma
hospedagem tradicional**: Hostinger, KingHost, Locaweb, HostGator, ou uma
VPS (DigitalOcean, Railway, Render). Praticamente todas rodam PHP + MySQL
via cPanel com poucos cliques, sem runtime experimental nenhum.

## Estrutura

```
install.php               → instalador (banco + nome/sigla + admin), se autoapaga no fim
index.php                 → cartão público (100% renderizado no servidor)
admin/login.php            → tela de login
admin/logout.php           → encerra a sessão
admin/index.php             → painel: perfil, aparência e links
includes/config.php          → credenciais do banco (o instalador escreve isso sozinho)
includes/db.php              → conexão PDO
includes/auth.php            → login/logout/CSRF (sessão via cookie + tabela)
includes/functions.php       → leitura de dados e ícones dos links
assets/css/style.css         → todo o visual (cartão + admin + instalador)
sql/schema.sql               → cria todas as tabelas no MySQL
```

## Instalação (com o instalador)

Não precisa editar nenhum arquivo à mão. Depois de subir os arquivos para
a hospedagem:

1. Crie um banco de dados MySQL vazio (via cPanel ou similar) — só
   precisa existir, sem tabelas.
2. Acesse `seusite.com/install.php` no navegador.
3. **Passo 1 — Banco:** informe host, nome do banco, usuário e senha. O
   instalador testa a conexão antes de avançar.
4. **Passo 2 — Nome e sigla:** seu nome (ou o nome do cartão) e, opcional,
   uma sigla de até 4 letras para o avatar (ex.: "JS") — usada enquanto
   você não tiver uma foto.
5. **Passo 3 — Admin:** e-mail e senha para logar no painel.

Ao confirmar, o instalador:
- grava `includes/config.php` com os dados do banco;
- roda `sql/schema.sql` e cria todas as tabelas;
- salva seu nome/sigla no perfil;
- cria seu usuário administrador;
- **apaga a si mesmo** (`install.php`) do servidor, por segurança.

Depois disso é só acessar `seusite.com/admin/login.php`.

Se o arquivo não conseguir se autoapagar (algumas hospedagens bloqueiam
isso), a própria tela final avisa — nesse caso, apague-o manualmente.

## Instalação manual (sem o instalador)

Se preferir não usar o `install.php`, ainda dá pra fazer do jeito
tradicional:

1. Importe `sql/schema.sql` no seu banco (phpMyAdmin, Adminer, ou
   `mysql -u usuario -p banco < sql/schema.sql`).
2. Preencha `includes/config.php` com host/nome/usuário/senha do banco.
3. Rode manualmente, no seu banco:
   ```sql
   UPDATE profile SET name = 'Seu Nome', initials_override = 'SN' WHERE id = 1;
   ```
4. Para criar o admin, gere um hash de senha
   (`php -r "echo password_hash('sua_senha', PASSWORD_DEFAULT);"`) e
   insira manualmente em `admin_users`.

## Testado

Este projeto foi rodado de ponta a ponta num ambiente local (PHP 8.3 +
MySQL 8) antes da entrega: as 3 etapas do instalador (banco → nome/sigla →
admin), incluindo os casos de erro (senha do banco errada, senhas de admin
não coincidindo), autodestruição do `install.php`, bloqueio de reinstalação,
login/logout, edição de perfil e aparência, e
adicionar/editar/reordenar/desativar/excluir link — tudo conferido
refletindo corretamente na página pública.

## Personalizando pelo painel

Igual à versão anterior: a seção **Aparência** deixa trocar cores, fontes,
formato do avatar, estilo dos botões e o layout (cartão flutuante ou
página cheia estilo Linktree) — tudo salvo no MySQL, sem editar código.

**Ícones dos links:** palavra-chave reconhecida (`instagram`, `whatsapp`,
`linkedin`, `github`, `email`, `twitter`/`x`, `youtube`, `telegram`,
`tiktok`, `pix`) ou cole a URL de uma imagem para usar como ícone
personalizado.

## Segurança

- Senhas ficam com hash bcrypt (`password_hash`), nunca em texto puro.
- Login usa token aleatório de 32 bytes guardado em `admin_sessions`,
  validado a cada acesso — não depende do diretório de sessões do PHP,
  então funciona igual em qualquer hospedagem.
- Todos os formulários do admin usam token CSRF (cookie + campo oculto).
- `includes/` e `sql/` têm `.htaccess` bloqueando acesso direto pelo
  navegador (funciona em Apache; se sua hospedagem usa Nginx, peça ao
  suporte para bloquear essas duas pastas, ou mova-as para fora da pasta
  pública do site).
- Não existe cadastro público de admin: só existe o `install.php`
  (autodestrutivo) e, depois dele, o login normal.
