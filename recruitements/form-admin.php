<?php
// recruitements/form-admin.php
require_once __DIR__ . '/../security.php';
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'participant';
$success = null;
$error = null;

if ($user_role === 'admin') {
    $error = "Vous possédez déjà les droits d'Administrateur sur la plateforme.";
}

if (!$error) {
    $stmtCheck = $pdo->prepare("SELECT id FROM role_applications WHERE user_id = ? AND requested_role = 'admin' AND status = 'pending'");
    $stmtCheck->execute([$user_id]);
    if ($stmtCheck->fetch()) {
        $error = "Votre demande pour rejoindre l'administration est déjà en cours de révision.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $motivation = trim($_POST['motivation'] ?? '');

    if (empty($motivation)) {
        $error = "Le champ de motivation est obligatoire.";
    } elseif (strlen($motivation) < 30) {
        $error = "Une demande d'administration exige des arguments solides (minimum 30 caractères).";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO role_applications (user_id, requested_role, motivation) VALUES (?, 'admin', ?)");
            $stmt->execute([$user_id, $motivation]);
            $success = "Votre candidature a été déposée avec succès. L'équipe actuelle va étudier votre demande.";
        } catch (Exception $e) {
            $error = "Une erreur est survenue lors de l'insertion en base de données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Devenir Administrateur</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <img src="../assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="../index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <div class="ms-auto"><a href="dashboard.php" class="btn btn-outline-light btn-sm">← Retour au Bureau</a></div>
    </div>
</nav>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="text-center mb-4">
                <h1 class="text-white mb-2" style="color: var(--ghost-blue) !important;">Devenir Administrateur</h1>
                <p class="text-muted small">Demandez l'accès aux privilèges de modération et de gestion des utilisateurs.</p>
            </div>

            <div class="card" style="border-color: rgba(0, 176, 255, 0.3);">
                <?php if ($error): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 py-2 small mb-4 rounded">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success bg-success text-dark border-0 py-2 small fw-bold mb-4 rounded">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form action="form-admin.php" method="POST">
                    <?= csrfField() ?>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium mb-2">Motivation pour l'administration :</label>
                        <textarea name="motivation" class="form-control" rows="6" 
                                  placeholder="Expliquez votre expérience de modération et comment vous comptez gérer le site..." 
                                  <?= ($user_role === 'admin') ? 'disabled' : '' ?> required></textarea>
                    </div>

                    <button type="submit" class="btn btn-outline-light w-100 py-2" style="background-color: var(--ghost-blue); color: #000; border: none;"
                            <?= ($user_role === 'admin') ? 'disabled' : '' ?>>
                        Soumettre la candidature
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>