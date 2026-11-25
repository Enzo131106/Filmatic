<?php
// register.php
require_once __DIR__ . '/security.php';
require_once 'db.php';

// Si l'utilisateur est déjà connecté, on le redirige vers l'accueil
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || strlen($username) > 50) {
        $error = "Veuillez saisir un pseudo et une adresse email valides.";
    } elseif (strlen($password) < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) {
        $error = "Le mot de passe doit contenir entre 8 et 72 octets.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérification si l'utilisateur ou l'email existe déjà
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmtCheck->execute([$username, $email]);
        
        if ($stmtCheck->fetch() !== false) {
            $error = "Ce nom d'utilisateur ou cet email est déjà utilisé.";
        } else {
            // Hachage sécurisé du mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'participant')");
                $stmt->execute([$username, $email, $hashed_password]);
                $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
            } catch (Exception $e) {
                $error = "Erreur lors de l'inscription.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - Inscription</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="text-center mb-4">
                    <a href="index.php" class="text-decoration-none"><h2 class="text-gradient-green mb-0">FILMATIC.</h2></a>
                    <p class="text-muted small mt-2">Rejoignez la communauté.</p>
                </div>

                <?php if ($error): ?><div class="alert alert-danger bg-danger text-white border-0 py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success bg-success text-dark border-0 py-2 small fw-bold">
                        <?= htmlspecialchars($success) ?> <br> <a href="login.php" class="text-dark text-decoration-underline">Aller à la connexion</a>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Nom d'utilisateur</label>
                        <input type="text" name="username" class="form-control" required placeholder="Ex: CineFan99">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Adresse Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="contact@exemple.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Mot de passe</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium">Confirmer le mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3 py-2">Créer mon compte</button>
                    <p class="text-center text-muted small mb-0">Déjà inscrit ? <a href="login.php" class="text-decoration-none text-white">Se connecter</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>