<?php
require_once __DIR__ . '/security.php';

$configPath = __DIR__ . '/config.local.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Configuration manquante. Consultez le README pour installer Filmatic.');
}
$config = require $configPath;

try {
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'] . ';charset=utf8mb4',
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(503);
    exit('Connexion à la base de données impossible. Vérifiez la configuration locale.');
}

if (isset($_SESSION['user_id'])) {
    $stmtSession = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ?');
    $stmtSession->execute([$_SESSION['user_id']]);
    $sessionUser = $stmtSession->fetch();
    if (!$sessionUser) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['user_id'] = (int) $sessionUser['id'];
        $_SESSION['username'] = $sessionUser['username'];
        $_SESSION['role'] = $sessionUser['role'];
    }
}
