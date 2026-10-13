<?php
/**
 * INSTALADOR DO PORTFOLIO CARD PHP
 * 
 * Acesse: http://seu-site.com/install.php
 * Configure banco de dados, prefixo e crie o usuário admin
 */

define('INSTALL_COMPLETE_FLAG', __DIR__ . '/.installed');

@set_time_limit(120);

/** Opções PDO: erros como exceção e timeout curto (nunca fica pendurado esperando o servidor). */
function pdo_opts(): array {
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ];
}

/**
 * Conecta ao Supabase (PostgreSQL). O banco "postgres" já existe no projeto,
 * então não há CREATE DATABASE: se a conexão falhar, o erro é mostrado como está.
 */
function connect_db(string $host, string $name, string $user, string $pass, string $port = '5432', string $sslmode = 'require'): PDO {
    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('A extensão PHP pdo_pgsql não está ativada nesta hospedagem.');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('O nome do banco só pode ter letras, números e underscore ( _ ).');
    }
    $dsn = 'pgsql:host=' . $host . ';port=' . (int) $port . ';dbname=' . $name . ';sslmode=' . $sslmode . ';connect_timeout=8';
    $opts = pdo_opts();
    if ((int) $port === 6543) $opts[PDO::ATTR_EMULATE_PREPARES] = true; // pooler "transaction"
    try {
        return new PDO($dsn, $user, $pass, $opts);
    } catch (PDOException $e) {
        throw new RuntimeException($e->getMessage());
    }
}

/**
 * Divide um script SQL em comandos individuais. Respeita textos entre aspas/crases
 * (um ponto e vírgula dentro de texto não encerra o comando) e ignora comentários.
 */
function split_sql(string $sql): array {
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $n = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\' && $quote !== '`' && $i + 1 < $len) { $buf .= $sql[++$i]; continue; }
            if ($c === $quote) {
                if ($n === $quote) { $buf .= $sql[++$i]; continue; }
                $quote = null;
            }
            continue;
        }
        // Texto entre $$ ... $$ (ou $tag$ ... $tag$): ponto e vírgula dentro dele não encerra o comando
        if ($c === '$' && preg_match('/\G\$([A-Za-z_]*)\$/', $sql, $m, 0, $i)) {
            $endTag = $m[0];
            $endPos = strpos($sql, $endTag, $i + strlen($endTag));
            $stop   = $endPos === false ? $len : $endPos + strlen($endTag);
            $buf   .= substr($sql, $i, $stop - $i);
            $i      = $stop - 1;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $buf .= $c; continue; }
        if ($c === '-' && $n === '-') {
            while ($i < $len && $sql[$i] !== "\n") $i++;
            $buf .= "\n";
            continue;
        }
        if ($c === '/' && $n === '*') {
            $end = strpos($sql, '*' . '/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }
        if ($c === ';') {
            if (trim($buf) !== '') $out[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') $out[] = trim($buf);
    return $out;
}

// Se já foi instalado, redireciona
if (file_exists(INSTALL_COMPLETE_FLAG)) {
    header('Location: admin/login.php');
    exit;
}

// Processa o POST
// Sempre inteiro: vindo de $_GET seria texto ("2") e as comparações estritas (=== / !==)
// do HTML nunca bateriam, deixando todos os painéis escondidos.
$step = (int) ($_GET['step'] ?? 1);
if ($step < 1 || $step > 4) $step = 1;
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'config') {
        // Validação básica
        $db_host = trim($_POST['db_host'] ?? '');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        $db_port = trim($_POST['db_port'] ?? '5432');
        $app_prefix = trim($_POST['app_prefix'] ?? 'jf_');

        if (!ctype_digit($db_port)) $errors[] = 'Porta inválida (use 5432 ou 6543)';
        if (!$db_host) $errors[] = 'Host do banco é obrigatório';
        if (!$db_name) $errors[] = 'Nome do banco é obrigatório';
        if (!$db_user) $errors[] = 'Usuário do banco é obrigatório';
        
        // Validar prefixo (só letras, números, underscore)
        if (!preg_match('/^[a-z0-9_]{1,10}$/', $app_prefix)) {
            $errors[] = 'Prefixo inválido. Use apenas letras minúsculas, números e underscore (ex: jf_)';
        }

        if (empty($errors)) {
            // Tenta conectar
            try {
                // Conecta (cria o banco só se ele não existir)
                connect_db($db_host, $db_name, $db_user, $db_pass, $db_port);

                // Valores gravados com var_export: aspas ou barras na senha não quebram o config.php
                $q_host = var_export($db_host, true);
                $q_name = var_export($db_name, true);
                $q_user = var_export($db_user, true);
                $q_pass = var_export($db_pass, true);
                $q_port = var_export($db_port, true);
                $q_prefix = var_export($app_prefix, true);

                // Salva configuração
                $config_content = <<<PHP
<?php
// ---------------------------------------------------------------
// Gerado pelo instalador. Contém segredos: NÃO versionar este arquivo.
// Configurações do Backblaze B2 (B2_*) podem ser adicionadas aqui
// com define('B2_KEY_ID', '...') ou via variáveis de ambiente.
// ---------------------------------------------------------------
define('DB_HOST', {$q_host});
define('DB_PORT', {$q_port});
define('DB_NAME', {$q_name});
define('DB_USER', {$q_user});
define('DB_PASS', {$q_pass});
define('DB_SSLMODE', 'require');
define('DB_POOLER_TRANSACTION', {$q_port} === '6543');
define('APP_PREFIX', {$q_prefix});
PHP;
                
                if (!file_put_contents(__DIR__ . '/includes/config.local.php', $config_content)) {
                    $errors[] = 'Não consegui salvar o includes/config.local.php. Verifique as permissões.';
                } else {
                    // Próximo passo
                    header('Location: install.php?step=2&prefix=' . urlencode($app_prefix) . '&db_host=' . urlencode($db_host) . '&db_name=' . urlencode($db_name));
                    exit;
                }
            } catch (Throwable $e) {
                $errors[] = 'Erro ao conectar no banco: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'create_tables') {
        try {
            require __DIR__ . '/includes/config.php';
            $pdo = connect_db(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SSLMODE);

            $schema = @file_get_contents(__DIR__ . '/sql/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('Não consegui ler sql/schema.sql.');
            }
            $schema = preg_replace('/^\xEF\xBB\xBF/', '', $schema);

            // O schema.sql usa o prefixo padrão jf_ nos nomes (sempre entre aspas duplas).
            // Troca pelo prefixo escolhido no passo 1 (o mesmo gravado em APP_PREFIX).
            $schema = str_replace('"jf_', '"' . APP_PREFIX, $schema);

            // Executa UM COMANDO POR VEZ; se algum falhar, diz exatamente qual.
            foreach (split_sql($schema) as $i => $cmd) {
                try {
                    $pdo->exec($cmd);
                } catch (Throwable $e) {
                    $preview = preg_replace('/\s+/', ' ', substr($cmd, 0, 80));
                    throw new RuntimeException('Falha no comando #' . ($i + 1) . ' (' . $preview . '…): ' . $e->getMessage());
                }
            }

            header('Location: install.php?step=3');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erro ao criar tabelas: ' . $e->getMessage();
            $step = 2; // continua na tela do passo 2 mostrando o erro
        }
    }

    if ($action === 'create_admin') {
        $email = trim($_POST['admin_email'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $password_confirm = $_POST['admin_password_confirm'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Senha deve ter pelo menos 8 caracteres';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'Senhas não conferem';
        }

        if (empty($errors)) {
            try {
                require __DIR__ . '/includes/config.php';
                $pdo = connect_db(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SSLMODE);

                $stmt = $pdo->prepare('INSERT INTO ' . APP_PREFIX . 'admin_users (email, password_hash) VALUES (?, ?)');
                $stmt->execute([
                    $email,
                    password_hash($password, PASSWORD_BCRYPT)
                ]);

                // Marca como instalado
                touch(INSTALL_COMPLETE_FLAG);

                header('Location: install.php?step=4');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Erro ao criar admin: ' . $e->getMessage();
            }
        }
    }
}

// Recupera dados do POST anterior (para pré-preencher)
$db_host = $_POST['db_host'] ?? 'aws-0-sa-east-1.pooler.supabase.com';
$db_port = $_POST['db_port'] ?? '5432';
$db_name = $_POST['db_name'] ?? 'postgres';
$db_user = $_POST['db_user'] ?? '';
$db_pass = $_POST['db_pass'] ?? '';
$app_prefix = $_POST['app_prefix'] ?? 'jf_';
$admin_email = $_POST['admin_email'] ?? '';

// Recupera do GET também
if (isset($_GET['prefix'])) {
    $app_prefix = $_GET['prefix'];
}
if (isset($_GET['db_host'])) {
    $db_host = $_GET['db_host'];
}
if (isset($_GET['db_name'])) {
    $db_name = $_GET['db_name'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalador — Portfolio Card PHP</title>
<link rel="stylesheet" media="print" onload="this.media='all'" href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Public+Sans:wght@400;500;600;700&display=swap">
<style>
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: "Public Sans", -apple-system, sans-serif;
  background: linear-gradient(160deg, #0F3D3E 0%, #0A2B2C 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: #1B2A28;
}

.install-wrapper {
  width: 100%;
  max-width: 600px;
  background: #FAF6ED;
  border-radius: 22px;
  box-shadow: 0 30px 60px -20px rgba(0,0,0,0.55);
  padding: 40px 32px;
}

.install-header {
  text-align: center;
  margin-bottom: 32px;
}

.install-header h1 {
  font-family: "Newsreader", Georgia, serif;
  font-size: 28px;
  font-weight: 500;
  margin: 0 0 8px;
  color: #1B2A28;
}

.install-header p {
  font-size: 14px;
  color: #4B5B58;
  margin: 0;
}

.step-indicator {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 32px;
}

.step-dot {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  background: #E9F2E8;
  color: #2F6B3A;
  border: 2px solid #D0E8CC;
}

.step-dot.active {
  background: #0F3D3E;
  color: #FAF6ED;
  border-color: #0F3D3E;
}

.step-dot.done {
  background: #2F6B3A;
  color: #fff;
  border-color: #2F6B3A;
}

.field {
  margin-bottom: 18px;
}

.field label {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: #4B5B58;
  margin-bottom: 6px;
}

.field input,
.field select {
  width: 100%;
  font-family: inherit;
  font-size: 14.5px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid rgba(27, 42, 40, 0.14);
  background: #FBFAF7;
  color: #1B2A28;
}

.field input:focus,
.field select:focus {
  outline: 2px solid #B08D57;
  outline-offset: 1px;
}

.field input[type="password"] {
  font-family: monospace;
}

.small-note {
  font-size: 12px;
  color: #4B5B58;
  margin-top: 6px;
  line-height: 1.4;
}

.messages {
  margin-bottom: 20px;
}

.message {
  font-size: 13px;
  padding: 10px 12px;
  border-radius: 8px;
  margin-bottom: 10px;
  border: 1px solid;
}

.message.error {
  background: #FBEAE6;
  color: #B4432F;
  border-color: #F5C6BC;
}

.message.success {
  background: #E9F2E8;
  color: #2F6B3A;
  border-color: #D0E8CC;
}

.message.info {
  background: #E8F4F8;
  color: #0F6B7D;
  border-color: #B8E1ED;
}

.btn {
  font-family: inherit;
  font-weight: 600;
  font-size: 13.5px;
  border-radius: 8px;
  border: 1px solid rgba(27, 42, 40, 0.14);
  background: #0F3D3E;
  color: #fff;
  padding: 10px 20px;
  cursor: pointer;
  transition: background 0.15s ease;
  width: 100%;
}

.btn:hover {
  background: #0A2B2C;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.progress-bar {
  width: 100%;
  height: 4px;
  background: #E9F2E8;
  border-radius: 2px;
  margin-bottom: 32px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #2F6B3A;
  border-radius: 2px;
  transition: width 0.3s ease;
}

.success-box {
  text-align: center;
  padding: 20px;
  background: #E9F2E8;
  border-radius: 12px;
  border: 1px solid #D0E8CC;
  margin-bottom: 20px;
}

.success-box h2 {
  font-family: "Newsreader", Georgia, serif;
  font-size: 22px;
  color: #2F6B3A;
  margin: 0 0 10px;
}

.success-box p {
  font-size: 14px;
  color: #2F6B3A;
  margin: 0;
}

.button-group {
  display: flex;
  gap: 10px;
}

.button-group .btn {
  flex: 1;
  background: #0F3D3E;
  color: #fff;
}

.button-group .btn.secondary {
  background: #fff;
  border-color: #B08D57;
  color: #0F3D3E;
}

.hidden { display: none; }

@media (max-width: 480px) {
  .install-wrapper {
    padding: 24px 16px;
  }
  
  .install-header h1 {
    font-size: 22px;
  }
}
</style>
</head>
<body>

<div class="install-wrapper">
  <div class="install-header">
    <h1>🎨 Portfolio Card</h1>
    <p>Instalador de Configuração</p>
  </div>

  <div class="progress-bar">
    <div class="progress-fill" style="width: <?= ($step / 4) * 100 ?>%"></div>
  </div>

  <div class="step-indicator">
    <div class="step-dot <?= $step >= 1 ? ($step === 1 ? 'active' : 'done') : '' ?>">1</div>
    <div class="step-dot <?= $step >= 2 ? ($step === 2 ? 'active' : 'done') : '' ?>">2</div>
    <div class="step-dot <?= $step >= 3 ? ($step === 3 ? 'active' : 'done') : '' ?>">3</div>
    <div class="step-dot <?= $step >= 4 ? 'done' : '' ?>">4</div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="messages">
      <?php foreach ($errors as $error): ?>
        <div class="message error">❌ <?= htmlspecialchars($error) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="messages">
      <div class="message success">✓ <?= htmlspecialchars($success) ?></div>
    </div>
  <?php endif; ?>

  <!-- STEP 1: Configuração do Banco -->
  <div class="<?= $step !== 1 ? 'hidden' : '' ?>">
    <h2 style="font-family: 'Newsreader', Georgia, serif; font-size: 20px; margin: 0 0 20px; color: #1B2A28;">
      Passo 1: Banco de Dados
    </h2>

    <form method="post" action="install.php">
      <input type="hidden" name="action" value="config">

      <div class="field">
        <label for="db-host">Host do Banco</label>
        <input type="text" id="db-host" name="db_host" value="<?= htmlspecialchars($db_host) ?>" placeholder="aws-0-sa-east-1.pooler.supabase.com" required>
        <p class="small-note">Supabase → Project Settings → Database → Connection string (Session pooler)</p>
      </div>

      <div class="field">
        <label for="db-port">Porta</label>
        <input type="text" id="db-port" name="db_port" value="<?= htmlspecialchars($db_port) ?>" placeholder="5432" required>
        <p class="small-note">5432 = Session pooler (hospedagem comum/VPS). 6543 = Transaction pooler (serverless).</p>
      </div>

      <div class="field">
        <label for="db-name">Nome do Banco</label>
        <input type="text" id="db-name" name="db_name" value="<?= htmlspecialchars($db_name) ?>" placeholder="postgres" required>
        <p class="small-note">Será criado automaticamente se não existir</p>
      </div>

      <div class="field">
        <label for="db-user">Usuário</label>
        <input type="text" id="db-user" name="db_user" value="<?= htmlspecialchars($db_user) ?>" placeholder="postgres.SEU_PROJECT_REF" required>
      </div>

      <div class="field">
        <label for="db-pass">Senha</label>
        <input type="password" id="db-pass" name="db_pass" value="<?= htmlspecialchars($db_pass) ?>" placeholder="sua_senha">
      </div>

      <div class="field">
        <label for="app-prefix">Prefixo das Tabelas</label>
        <input type="text" id="app-prefix" name="app_prefix" value="<?= htmlspecialchars($app_prefix) ?>" placeholder="jf_" required>
        <p class="small-note">✓ Recomendado: <code>jf_</code> (evita conflitos). Formato: letras + underscore</p>
      </div>

      <div class="message info">
        ℹ️ As tabelas serão criadas como: <code><?= htmlspecialchars($app_prefix) ?>admin_users</code>, <code><?= htmlspecialchars($app_prefix) ?>profile</code>, etc.
      </div>

      <button type="submit" class="btn">Próximo →</button>
    </form>
  </div>

  <!-- STEP 2: Criar Tabelas -->
  <div class="<?= $step !== 2 ? 'hidden' : '' ?>">
    <h2 style="font-family: 'Newsreader', Georgia, serif; font-size: 20px; margin: 0 0 20px; color: #1B2A28;">
      Passo 2: Criar Tabelas
    </h2>

    <div class="message info">
      ℹ️ Criando tabelas com prefixo <code><?= htmlspecialchars($app_prefix) ?></code>...
    </div>

    <form method="post" action="install.php">
      <input type="hidden" name="action" value="create_tables">
      <input type="hidden" name="prefix" value="<?= htmlspecialchars($app_prefix) ?>">

      <p>As seguintes tabelas serão criadas:</p>
      <ul style="font-size: 13px; color: #4B5B58; line-height: 1.8;">
        <li><code><?= htmlspecialchars($app_prefix) ?>admin_users</code> - Usuários admin</li>
        <li><code><?= htmlspecialchars($app_prefix) ?>admin_sessions</code> - Sessões</li>
        <li><code><?= htmlspecialchars($app_prefix) ?>profile</code> - Perfil do cartão</li>
        <li><code><?= htmlspecialchars($app_prefix) ?>settings</code> - Aparência e configurações</li>
        <li><code><?= htmlspecialchars($app_prefix) ?>links</code> - Links do cartão</li>
      </ul>

      <button type="submit" class="btn">Criar Tabelas →</button>
    </form>
  </div>

  <!-- STEP 3: Criar Admin -->
  <div class="<?= $step !== 3 ? 'hidden' : '' ?>">
    <h2 style="font-family: 'Newsreader', Georgia, serif; font-size: 20px; margin: 0 0 20px; color: #1B2A28;">
      Passo 3: Usuário Admin
    </h2>

    <form method="post" action="install.php">
      <input type="hidden" name="action" value="create_admin">

      <div class="field">
        <label for="admin-email">Email do Admin</label>
        <input type="email" id="admin-email" name="admin_email" value="<?= htmlspecialchars($admin_email) ?>" placeholder="seu@email.com" required>
      </div>

      <div class="field">
        <label for="admin-password">Senha (mínimo 8 caracteres)</label>
        <input type="password" id="admin-password" name="admin_password" placeholder="••••••••" required>
      </div>

      <div class="field">
        <label for="admin-password-confirm">Confirmar Senha</label>
        <input type="password" id="admin-password-confirm" name="admin_password_confirm" placeholder="••••••••" required>
      </div>

      <div class="message info">
        💡 Depois de instalar, você acessará o painel em <code>/admin</code> com essas credenciais.
      </div>

      <button type="submit" class="btn">Finalizar Instalação →</button>
    </form>
  </div>

  <!-- STEP 4: Sucesso -->
  <div class="<?= $step !== 4 ? 'hidden' : '' ?>">
    <div class="success-box">
      <h2>✓ Instalação Completa!</h2>
      <p>Seu Portfolio Card PHP está pronto para usar.</p>
    </div>

    <div class="message success">
      <strong>Configuração Salva:</strong>
      <ul style="margin: 10px 0; padding-left: 20px; font-size: 12px;">
        <li>Banco de dados criado e conectado</li>
        <li>Todas as tabelas criadas com prefixo <code><?= htmlspecialchars($app_prefix) ?></code></li>
        <li>Usuário admin criado com sucesso</li>
        <li>Pasta <code>/uploads</code> pronta para fotos</li>
      </ul>
    </div>

    <h3 style="font-family: 'Newsreader', Georgia, serif; font-size: 16px; margin: 24px 0 12px;">Próximos Passos:</h3>
    <ol style="font-size: 13px; color: #4B5B58; line-height: 1.8;">
      <li>Crie a pasta <code>/uploads</code> manualmente se não existir</li>
      <li>Rode: <code>chmod 755 uploads/</code> (permissão de escrita)</li>
      <li>Acesse: <code>http://seu-site.com/admin</code></li>
      <li>Faça login com o email e senha que cadastrou</li>
      <li>Customize seu cartão! 🎨</li>
    </ol>

    <div style="display: flex; gap: 10px; margin-top: 24px;">
      <a href="admin/login.php" class="btn" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">
        Acessar Admin →
      </a>
    </div>

    <p style="text-align: center; font-size: 12px; color: #7C9385; margin-top: 16px;">
      Este arquivo <code>install.php</code> pode ser deletado agora.
    </p>
  </div>

</div>

</body>
</html>
