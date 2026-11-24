<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execute via CLI.\n");
}

$email    = strtolower($argv[1] ?? 'demo@sdd.test');
$password = $argv[2] ?? 'SenhaForte!123';

if ($email === '' || $password === '') {
    fwrite(STDERR, "Uso: php seed_user.php <email> <senha>\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = db()->prepare(
    'INSERT INTO users (email, password_hash) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE password_hash = ?'
);
$stmt->execute([$email, $hash, $hash]);

echo "Usuário criado/atualizado: {$email}\n";
