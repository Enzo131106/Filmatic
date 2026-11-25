<?php
// login.php
require_once __DIR__ . '/security.php';
require_once 'db.php';

// Si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // On cherche l'utilisateur par son pseudo ou son email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // On vérifie que l'utilisateur existe ET que le mot de passe correspond au hash
        if ($user && password_verify($password, $user['password'])) {
            // Création de la session
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: index.php"); // Redirection vers l'accueil
            exit();
        } else {
            $error = "Identifiants incorrects.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - Connexion</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0">
                <div class="text-center mb-4">
                    <a href="index.php" class="text-decoration-none"><h2 class="text-gradient-green mb-0">FILMATIC.</h2></a>
                    <p class="text-muted small mt-2">Accédez à votre espace.</p>
                </div>

                <?php if ($error): ?><div class="alert alert-danger bg-danger text-white border-0 py-2 small text-center"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <form action="login.php" method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Pseudo ou Email</label>
                        <input type="text" name="username" class="form-control" required placeholder="Votre identifiant">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium">Mot de passe</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3 py-2">Se connecter</button>
                    <p class="text-center text-muted small mb-0">Pas encore de compte ? <a href="register.php" class="text-decoration-none text-white">S'inscrire</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>