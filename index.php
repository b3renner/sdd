<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

enforce_https();
session_start_secure();

$error = null;
$info  = null;
$email = '';

// Sessão expirada (edge case)
if (isset($_GET['expired'])) {
    $info = MSG_SESSION_EXPIRED;
}

// Já autenticado → vai direto ao dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        // Fallback server-side para quando JS estiver desabilitado.
        // Campos vazios NÃO contam como tentativa falha de credenciais.
        // Mensagem genérica: não especifica o campo (consistente com o critério 3).
        $error = MSG_INVALID;
    } else {
        $result = attempt_login($email, $password);

        if ($result['ok']) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $result['user_id'];
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php');
            exit;
        }

        if ($result['blocked']) {
            $error = blocked_message((int)$result['remaining_seconds']);
        } else {
            // Critério 3: mensagem fixa, sem indicar o campo incorreto
            $error = MSG_INVALID;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
    <main class="login-card" role="main">
        <h1 class="login-title">Entrar</h1>
        <p class="login-subtitle">Acesse sua conta para continuar.</p>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($info !== null): ?>
            <div class="alert alert-info" role="status"><?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="alert alert-error" id="client-error" role="alert" hidden></div>

        <form id="login-form" method="post" action="index.php" novalidate>
            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email"
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="field">
                <label for="password">Senha</label>
                <input id="password" name="password" type="password"
                       autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>
    </main>

    <script src="assets/validation.js" defer></script>
</body>
</html>
