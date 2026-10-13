# 🔧 Guia de Mudanças e Migração

## Resumo das Correções Realizadas

Este guia explica todas as mudanças feitas no sistema para corrigir os problemas identificados.

---

## ❌ Problemas Identificados → ✅ Soluções Implementadas

### 1. **Conflito de Sessão com Outros Sistemas**

**Problema:** A sessão PHP usava nomes genéricos (`admin_token`, `csrf_token`) que conflitavam com outros sistemas no mesmo servidor.

**Solução:** 
- Adicionado prefixo único `jf_` para todas as tabelas e cookies
- `config.php` agora define: `define('APP_PREFIX', 'jf_');`
- Todos os nomes de cookies usam agora: `jf_admin_token`, `jf_csrf_token`
- Função helper `table()` em `db.php` facilita uso do prefixo

```php
// Antes:
SELECT * FROM profile WHERE id = 1;
setcookie('admin_token', ...);

// Depois:
SELECT * FROM jf_profile WHERE id = 1;
setcookie('jf_admin_token', ...);
```

---

### 2. **Prefixo nas Tabelas (jf_profile, jf_settings, etc)**

**Problema:** As tabelas não tinha prefixo, o que causava conflitos: `profile` virava só `profile` em vez de `jf_profile`.

**Solução:**
- `schema.sql` atualizado com prefixo em todas as tabelas:
  - `jf_admin_users`
  - `jf_admin_sessions`
  - `jf_profile`
  - `jf_settings`
  - `jf_links`

- Todos os arquivos PHP atualizados para usar `table()` ou strings diretas com prefixo
- Função `table('profile')` retorna `'jf_profile'`

**Como migrar:**
```sql
-- Rode no seu phpMyAdmin ou terminal:
-- Renomear tabelas existentes
RENAME TABLE profile TO jf_profile;
RENAME TABLE settings TO jf_settings;
RENAME TABLE links TO jf_links;
RENAME TABLE admin_users TO jf_admin_users;
RENAME TABLE admin_sessions TO jf_admin_sessions;
```

---

### 3. **Permissão de Upload de Fotos**

**Problema:** O painel não tinha opção de fazer upload direto de fotos, apenas URL.

**Solução:**
- Novo campo `avatar_file` na tabela `jf_profile`
- Painel agora tem dois campos:
  - **Upload direto** de JPG, PNG, WebP, GIF (até 5MB)
  - **URL da foto** (opcional, como fallback)
- Função `upload_image()` em `functions.php` trata o upload
- Cria pasta `/uploads` automaticamente

**Mudanças no banco:**
```sql
ALTER TABLE jf_profile ADD COLUMN avatar_file VARCHAR(255);
```

**Campo no form:**
```html
<label for="p-avatar-upload">Foto do avatar</label>
<input type="file" name="avatar_upload" accept="image/*">
```

---

### 4. **Mais Ícones Disponíveis**

**Problema:** Faltavam ícones como Discord, Twitch, Threads, Bluesky, Spotify, Apple, etc.

**Solução:**
- Função `icon_markup()` em `functions.php` agora tem **20+ ícones**:
  - ✅ Instagram, WhatsApp, LinkedIn, GitHub
  - ✅ Email, Twitter (X), YouTube, Telegram, TikTok
  - ✅ **Novo:** Discord, Twitch, Threads, Bluesky, Spotify, Apple, Pix

**Como usar:**
Basta digitar no campo "Ícone" do painel:
- `discord` → ícone do Discord
- `twitch` → ícone do Twitch
- `threads` → ícone do Threads
- Ou continue usando URLs para ícones customizados

---

### 5. **Fundo do Cartão (Cor + Imagem)**

**Problema:** Não havia opção para customizar o fundo do cartão com cor ou imagem.

**Solução:**
- Novo campo `bg_image` na tabela `jf_settings`
- Painel agora tem seção **"Cores"** com:
  - Cor de fundo (já existia)
  - **Novo:** URL de imagem de fundo (opcional)

**Mudanças no banco:**
```sql
ALTER TABLE jf_settings ADD COLUMN bg_image VARCHAR(500);
```

**No painel:**
```html
<label for="t-bg-image">URL da imagem de fundo (opcional)</label>
<input type="url" name="bg_image" placeholder="https://...">
```

---

### 6. **Painel Admin Responsivo para Desktop**

**Problema:** O painel não ficava bom em computadores (responsividade ruim).

**Solução:**
- **Reescrito `style.css`** com:
  - `max-width: 900px` em vez de `640px` (mais espaço em desktop)
  - Grid CSS responsivo para formulários
  - Breakpoints: 480px, 640px, 768px, 1024px
  - Melhor espaçamento e padding em telas grandes
  - Links em tabela agora se reorganizam em desktop

**Mudanças principais:**
```css
/* Desktop: 2 colunas lado a lado */
@media (min-width: 768px) {
  .row {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Tabela de links em grid responsivo */
@media (min-width: 768px) {
  .link-row {
    grid-template-columns: auto 1fr 1fr 2fr auto;
  }
}
```

**Resultado:**
- ✅ Mobile: layout empilhado (1 coluna)
- ✅ Tablet: layout intermediário (adaptável)
- ✅ Desktop: layout 2 colunas, muito melhor uso de espaço

---

## 📋 Guia de Migração Passo a Passo

### Se você já tem o sistema instalado:

#### **Passo 1: Backup**
```bash
# Faça backup do banco e dos arquivos
mysqldump -u seu_usuario -p seu_banco > backup.sql
cp -r /caminho/portfolio-card-php /caminho/portfolio-card-php.backup
```

#### **Passo 2: Renomear tabelas**
```sql
RENAME TABLE profile TO jf_profile;
RENAME TABLE settings TO jf_settings;
RENAME TABLE links TO jf_links;
RENAME TABLE admin_users TO jf_admin_users;
RENAME TABLE admin_sessions TO jf_admin_sessions;

-- Adicionar novos campos
ALTER TABLE jf_profile ADD COLUMN avatar_file VARCHAR(255);
ALTER TABLE jf_settings ADD COLUMN bg_image VARCHAR(500);
```

#### **Passo 3: Substituir arquivos**
```bash
# Copie os arquivos corrigidos:
cp -r portfolio-card-php-corrigido/* /caminho/portfolio-card-php/

# Ou manualmente:
# - includes/config.php
# - includes/db.php
# - includes/functions.php
# - includes/auth.php
# - assets/css/style.css
# - admin/index.php
# - sql/schema.sql
```

#### **Passo 4: Criar pasta de uploads**
```bash
mkdir -p /caminho/portfolio-card-php/uploads
chmod 755 /caminho/portfolio-card-php/uploads
```

#### **Passo 5: Testar**
1. Acesse http://seu-site.com/admin/
2. Faça login
3. Teste upload de foto
4. Teste novos campos de fundo e ícones
5. Verifique se o painel ficou bom no desktop

---

## 📁 Estrutura de Arquivos Nova/Modificada

```
portfolio-card-php/
├── includes/
│   ├── config.php          ✅ ATUALIZADO (prefixo jf_)
│   ├── db.php              ✅ ATUALIZADO (função table())
│   ├── functions.php       ✅ ATUALIZADO (upload, mais ícones)
│   ├── auth.php            ✅ ATUALIZADO (prefixo jf_)
│   └── ...
├── admin/
│   ├── index.php           ✅ ATUALIZADO (upload, novos campos)
│   ├── login.php           (sem mudanças)
│   └── logout.php          (sem mudanças)
├── assets/
│   └── css/
│       └── style.css       ✅ REESCRITO (responsivo para desktop)
├── sql/
│   └── schema.sql          ✅ ATUALIZADO (prefixo jf_)
├── uploads/                ✨ NOVO (pasta para fotos)
└── ...
```

---

## 🔒 Segurança: O que Mudou

### ✅ Melhorias
1. **Prefixo único:** Reduz risco de conflitos com outros sistemas
2. **Upload seguro:** Validação de tipo MIME, limite de tamanho
3. **Nomes de cookie únicos:** Dificulta ataques CSRF cross-site
4. **Mesma segurança:** SQLi, XSS, CSRF ainda protegidas

### ⚠️ Atenção
- Pasta `/uploads` deve ter permissões `755` (leitura+execução pública)
- Imagens são servidas pelo servidor web (seguro)
- Mantenha `httponly: true` nos cookies de admin

---

## 🆘 Troubleshooting

### "Erro ao salvar: Table não existe"
→ Você esqueceu de renomear as tabelas! Rode os comandos SQL do **Passo 2**.

### "Erro ao fazer upload: Nenhum arquivo foi enviado"
→ Verifique permissões da pasta `/uploads`. Rode: `chmod 755 uploads/`

### "Ícones não aparecem"
→ Certifique-se que `icon_markup()` em `functions.php` tem os novos ícones.

### "Painel fica distorcido no desktop"
→ Limpe cache do navegador. CSS foi reescrito!

---

## 📝 Changelog Completo

### `config.php`
- ✨ `APP_PREFIX = 'jf_'` (novo)
- ✨ `UPLOAD_DIR`, `UPLOAD_MAX_SIZE`, `UPLOAD_ALLOWED_TYPES` (novos)
- Cookies agora usam prefixo

### `db.php`
- ✨ Função `table()` para retornar nome com prefixo

### `functions.php`
- ✨ Campo `avatar_file` em `get_profile()`
- ✨ Função `upload_image()` para processar uploads
- ✨ Novos ícones: Discord, Twitch, Threads, Bluesky, Spotify, Apple
- ✨ Aliases de ícones: 'threads', 'discord', 'twitch', etc.

### `auth.php`
- Todos os queries usam `table()` com prefixo
- Nomes de cookie com prefixo `jf_`

### `admin/index.php`
- ✨ Campo `avatar_upload` (file upload)
- ✨ Campo `bg_image` (URL da imagem de fundo)
- ✨ Múltiplos forms para `avatar_file`
- ✨ Lista expandida de ícones suportados
- Queries atualizadas com prefixo
- Enctype `multipart/form-data` no form

### `style.css`
- 🔄 Reescrito completamente
- ✨ Grid responsivo para `.row`
- ✨ Breakpoints: 480px, 640px, 768px, 1024px
- ✨ `max-width: 900px` em `.admin-shell`
- ✨ `.link-row` redesenhado como grid
- ✨ Suporte a `.image-preview`
- ✨ Melhor padding/espaçamento para desktop

### `schema.sql`
- Todas as tabelas com prefixo `jf_`
- ✨ `avatar_file` em `jf_profile`
- ✨ `bg_image` em `jf_settings`

---

## ✅ Checklist Pós-Migração

- [ ] Backup realizado
- [ ] Tabelas renomeadas
- [ ] Campos novos adicionados (avatar_file, bg_image)
- [ ] Arquivos substituídos
- [ ] Pasta `/uploads` criada com chmod 755
- [ ] Cache do navegador limpo
- [ ] Login funciona com novo prefixo de cookie
- [ ] Upload de foto funciona
- [ ] Novos ícones aparecem (discord, twitch, threads)
- [ ] Painel fica bom no desktop
- [ ] Cartão aparece corretamente no front-end

---

## 📞 Suporte

Se encontrar problemas:
1. Verifique se todos os queries usam `table()` ou prefixo `jf_`
2. Verifique permissões de pasta `/uploads`
3. Verifique se banco tem os novos campos
4. Limpe cookies do navegador e faça login novamente

---

**Versão:** 2.0 (com correções)  
**Data:** 2026-09-28  
**Compatibilidade:** PHP 7.4+, MySQL 5.7+, SQLite
