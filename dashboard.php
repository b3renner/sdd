<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

enforce_https();
session_start_secure();

// Não autenticado → sessão expirada/inválida (edge case)
if (empty($_SESSION['user_id'])) {
    header('Location: index.php?expired=1');
    exit;
}

// Inatividade
$now = time();
if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > SESSION_IDLE_MINUTES * 60) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = $now;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="dashboard-body">
    <main class="dashboard" role="main">
        <h1 class="dashboard-title">isso é SDD tchê</h1>
    </main>
</body>
</html>
