<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

function validRanking(array $submitted, array $items): bool
{
    foreach ($submitted as $id) {
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            return false;
        }
    }
    $actual = array_map('intval', $submitted);
    $expected = array_map('intval', array_column($items, 'id'));
    sort($actual, SORT_NUMERIC);
    sort($expected, SORT_NUMERIC);
    return $actual === $expected;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Requête non autorisée. Rechargez la page et réessayez.');
    }
    $arrayFields = ['movie_titles', 'movie_genres', 'movie_langs', 'loc_names', 'loc_mins', 'loc_maxs'];
    foreach ($_POST as $name => $value) {
        if (in_array($name, $arrayFields, true)) {
            if (!is_array($value) || count($value) > 10 || count(array_filter($value, 'is_string')) !== count($value)) {
                http_response_code(400);
                exit('Données du formulaire invalides.');
            }
        } elseif (!is_string($value)) {
            http_response_code(400);
            exit('Données du formulaire invalides.');
        }
    }
}
