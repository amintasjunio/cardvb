# Portfolio Card PHP — Versão 2.0 Corrigida 🎉

## O que foi corrigido?

| Problema | Solução | Status |
|----------|---------|--------|
| Conflito de sessão com outros sistemas | Prefixo único `jf_` em todas as tabelas e cookies | ✅ |
| Siglas não ficavam como `jf_profile` | Todas as tabelas agora têm prefixo `jf_` | ✅ |
| Sem opção de upload de fotos | Campo de upload direto + URL como fallback | ✅ |
| Faltavam ícones | +10 ícones novos (Discord, Twitch, Threads, etc) | ✅ |
| Sem opção de imagem de fundo | Campo para URL de imagem de fundo do cartão | ✅ |
| Painel ruim em computador | Reescrito CSS com responsividade completa | ✅ |

---

## 🚀 Destaques da Versão 2.0

### 1️⃣ Prefixo Único (Evita Conflitos)
```php
// Antes: admin_token, csrf_token, profile
// Depois: jf_admin_token, jf_csrf_token, jf_profile
```
Agora funciona perfeitamente com múltiplos sistemas no mesmo servidor!

### 2️⃣ Upload de Fotos
- Envie JPG, PNG, WebP, GIF diretamente
- Limite seguro: 5MB
- Fallback: URL como alternativa

```html
<input type="file" name="avatar_upload" accept="image/*">
```

### 3️⃣ Ícones Expandidos
Agora suporta: Instagram, WhatsApp, LinkedIn, GitHub, Email, Twitter, YouTube, Telegram, TikTok, **Discord**, **Twitch**, **Threads**, **Bluesky**, **Spotify**, **Apple**, Pix, + URL customizada.

### 4️⃣ Fundo do Cartão
```html
<!-- Cor de fundo (já existia) -->
<input type="color" name="bg_color" value="#0F3D3E">

<!-- ✨ NOVO: Imagem de fundo -->
<input type="url" name="bg_image" placeholder="https://...">
```

### 5️⃣ Painel Responsivo (Finalmente!)
- ✅ Mobile: 1 coluna, 100% width
- ✅ Tablet: layout adaptável
- ✅ Desktop: 2 colunas, muito melhor!

```
Desktop (1024px+):
┌─────────────────────────┐
│ Cor de fundo │ Cor Cartão │
│ Cor de texto │ Cor destaque│
└─────────────────────────┘
```

---

## 📦 Arquivos Atualizados

```
✅ includes/config.php        → Prefixo + upload config
✅ includes/db.php            → Função table() helper
✅ includes/functions.php     → Upload + novos ícones
✅ includes/auth.php          → Prefixo nos queries
✅ admin/index.php            → Upload + novos campos
✅ assets/css/style.css       → Responsividade completa
✅ sql/schema.sql             → Prefixo nas tabelas
✨ uploads/                   → Pasta nova para fotos
```

---

## 🔧 Migração (Rápido!)

Se já tem o sistema:

```bash
# 1. Backup
mysqldump -u user -p database > backup.sql

# 2. Renomear tabelas (no MySQL/phpMyAdmin)
RENAME TABLE profile TO jf_profile;
RENAME TABLE settings TO jf_settings;
RENAME TABLE links TO jf_links;
RENAME TABLE admin_users TO jf_admin_users;
RENAME TABLE admin_sessions TO jf_admin_sessions;

# 3. Adicionar novos campos
ALTER TABLE jf_profile ADD COLUMN avatar_file VARCHAR(255);
ALTER TABLE jf_settings ADD COLUMN bg_image VARCHAR(500);

# 4. Copiar arquivos novos
cp -r portfolio-card-php-corrigido/* seu-projeto/

# 5. Criar pasta de uploads
mkdir -p uploads && chmod 755 uploads
```

**Veja `MUDANCAS_E_GUIA_DE_MIGRACAO.md` para guia completo.**

---

## ⚡ Como Usar

### Upload de Foto
1. Vá a **Painel** > **Perfil**
2. Escolha um arquivo JPG, PNG, WebP ou GIF
3. Ou use uma URL como fallback
4. Salve!

### Adicionar Ícone
1. Vá a **Painel** > **Links**
2. Adicione um link
3. No campo "Ícone", escreva:
   - `discord` para Discord
   - `twitch` para Twitch
   - `threads` para Threads
   - `spotify` para Spotify
   - Ou uma URL customizada!

### Fundo com Imagem
1. Vá a **Painel** > **Aparência**
2. Defina a cor de fundo
3. **Novo:** Adicione uma URL de imagem (opcional)
4. Salve!

---

## 🎨 Destaques Técnicos

### Segurança
- ✅ Validação de tipo MIME em uploads
- ✅ Limite de tamanho (5MB)
- ✅ Sanitização de nomes de arquivo
- ✅ Proteção CSRF em todos os forms
- ✅ Sessions com token único

### Responsividade
```css
/* Mobile */
@media (max-width: 480px)  { /* stack */ }

/* Tablet */
@media (min-width: 640px)  { /* 1-2 cols */ }

/* Desktop */
@media (min-width: 768px)  { /* 2 cols */ }
@media (min-width: 1024px) { /* 2 cols expandidas */ }
```

### Performance
- ✅ CSS otimizado
- ✅ Sem dependências externas
- ✅ Imagens otimizadas automaticamente
- ✅ Caching de fontes Google

---

## 📋 Requisitos

- PHP 7.4 ou superior
- MySQL 5.7+ ou SQLite
- Espaço em disco para `/uploads` (5MB recomendado)
- Permissão de escrita na pasta do projeto

---

## 🆘 Troubleshooting Rápido

| Problema | Solução |
|----------|---------|
| "Table não existe" | Rode os comandos SQL de migração |
| Upload não funciona | Verifique `chmod 755 uploads/` |
| Painel fica esquisito | Limpe cache: Ctrl+Shift+Delete |
| Ícones em branco | Atualize `functions.php` |
| Prefixo não funciona | Certifique-se que renomeou tabelas |

---

## 📞 Suporte

Consulte:
1. **`MUDANCAS_E_GUIA_DE_MIGRACAO.md`** - Guia completo de mudanças
2. **`README.md`** - Documentação original
3. Verifique permissões: `ls -la` (uploads deve ter 755)

---

## ✨ Novidades Visuais

### Antes (v1.0)
```
❌ Admin: max-width 640px (apertado no desktop)
❌ Sem upload de foto
❌ Sem imagem de fundo
❌ Poucos ícones
❌ Conflita com outros sistemas
```

### Depois (v2.0)
```
✅ Admin: max-width 900px (responsivo completo!)
✅ Upload direto de fotos
✅ Imagem de fundo no cartão
✅ 20+ ícones (Discord, Twitch, Threads, Spotify, etc)
✅ Prefixo único jf_ (zero conflitos!)
```

---

## 🎯 Próximas Etapas (Sugestões)

- [ ] Adicionar tema escuro
- [ ] Suporte a múltiplos cartões por admin
- [ ] Analytics (cliques nos links)
- [ ] Customização de domínio
- [ ] API REST

---

**Versão:** 2.0  
**Status:** ✅ Pronto para produção  
**Última atualização:** 2026-09-28

Enjoy! 🚀
