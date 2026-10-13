# Migração: Supabase + Backblaze B2 + Vercel (via GitHub)

Arquitetura final: **GitHub → Vercel** (PHP) · **Supabase** (banco Postgres) · **Backblaze B2** (imagens e vídeos).

## 1. Supabase (banco)

1. Crie o projeto em supabase.com (região `South America (São Paulo)` se o público for BR).
2. **SQL Editor** → cole o conteúdo de `sql/schema_moc.sql` → Run.
   (Se preferir outro prefixo de tabelas, use `sql/schema.sql` e troque `"jf_` pelo seu prefixo.)
3. **Project Settings → Database → Connection string**. Para o Vercel use o **Transaction pooler** (porta **6543**):
   - Host: `aws-0-<região>.pooler.supabase.com`
   - Usuário: `postgres.<project-ref>`
   - Senha: a que você definiu ao criar o projeto

> O schema já liga o RLS em todas as tabelas, sem policies. Isso bloqueia a API pública do Supabase; o PHP continua acessando direto pelo Postgres. **Não use a chave `anon`/`service_role` neste projeto.**

## 2. Backblaze B2 (imagens)

1. Crie um bucket **Público** (ex.: `meu-card`).
2. **Lifecycle Settings** do bucket → "Keep only the last version of the file". Sem isso, arquivos apagados continuam ocupando (e cobrando) espaço.
3. **Application Keys → Add** → restrinja ao bucket, permissão *Read and Write*. Anote `keyID` e `applicationKey` (a chave só aparece uma vez).
4. Veja o **Endpoint** do bucket (ex.: `s3.us-west-004.backblazeb2.com`): a região é `us-west-004`.

## 3. Migrar os dados antigos (no seu computador)

Precisa de PHP 8.1+ com `pdo_pgsql`, `pdo_mysql` e `curl`, e da pasta `uploads/` original ao lado do projeto.

```bash
export DB_HOST=aws-0-sa-east-1.pooler.supabase.com DB_PORT=5432 DB_NAME=postgres \
       DB_USER=postgres.SEU_REF DB_PASS='senha' APP_PREFIX=moc_ \
       B2_KEY_ID=... B2_APP_KEY=... B2_BUCKET=meu-card B2_REGION=us-west-004 \
       MYSQL_HOST=127.0.0.1 MYSQL_NAME=seu_banco MYSQL_USER=root MYSQL_PASS='...' MYSQL_PREFIX=moc_

php tools/migrate_to_supabase_b2.php --dry-run   # simulação: não grava nada
php tools/migrate_to_supabase_b2.php             # migração de verdade
```

Fora do Vercel use a porta **5432** (Session pooler). O script pode ser repetido sem duplicar nada.
Sessões de login antigas não são copiadas: é só entrar de novo.

Banco novo (sem dados antigos)? Pule este passo e crie o admin:

```bash
php tools/create_admin.php voce@email.com 'senha-forte-123'
```

## 4. GitHub → Vercel

1. Suba o projeto para o GitHub. O `.gitignore` já exclui `uploads/`, `.env` e `includes/config.local.php`. **Nunca** commite senhas.
2. No Vercel: **Add New → Project** → importe o repositório. Framework: *Other*. Sem build command.
3. **Settings → Environment Variables** (Production e Preview):

| Variável | Valor |
|---|---|
| `DB_HOST` | `aws-0-<região>.pooler.supabase.com` |
| `DB_PORT` | `6543` |
| `DB_NAME` | `postgres` |
| `DB_USER` | `postgres.<project-ref>` |
| `DB_PASS` | senha do banco |
| `APP_PREFIX` | `moc_` |
| `B2_KEY_ID` / `B2_APP_KEY` | da Application Key |
| `B2_BUCKET` / `B2_REGION` | nome do bucket / `us-west-004` |
| `B2_PUBLIC_URL` | (opcional) domínio/CDN próprio das imagens |

4. Deploy. O painel fica em `https://seu-projeto.vercel.app/admin/`.

O `vercel.json` usa o runtime comunitário `vercel-php@0.7.4` (PHP 8.3, inclui `pdo_pgsql`, `curl` e `mbstring`). Todas as URLs passam por `api/index.php`, que só serve as páginas e os arquivos de `assets/` e `icons/`; o resto (`includes/`, `sql/`, `tools/`) nunca é acessível.

## Limitações do Vercel

- **Upload de até ~4 MB por requisição** (limite do Vercel). O app já limita a 4 MB quando roda lá. Para vídeo maior, envie o arquivo pelo site do Backblaze e cole a URL no campo de vídeo do painel.
- O disco é somente leitura: por isso `install.php` e `upgrade.php` não vão para o Vercel (estão no `.vercelignore`). Use `schema_moc.sql` e `tools/create_admin.php`.
- Mantenha Supabase e Vercel na mesma região, se possível, para reduzir a latência.

## Depois de conferir tudo

- A pasta `uploads/` local pode ser apagada (guarde um backup).
- Troque a senha antiga do MySQL: o zip original tinha ela em texto puro.
