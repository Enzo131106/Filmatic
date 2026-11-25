<?php
// recruitements/form-organizer.php
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

if ($user_role === 'organizer' || $user_role === 'admin') {
    $error = "Votre compte possède déjà des privilèges équivalents ou supérieurs à ceux d'un Organisateur.";
}

if (!$error) {
    $stmtCheck = $pdo->prepare("SELECT id FROM role_applications WHERE user_id = ? AND requested_role = 'organizer' AND status = 'pending'");
    $stmtCheck->execute([$user_id]);
    if ($stmtCheck->fetch()) {
        $error = "Vous avez déjà une demande d'Organisateur en cours d'examen par l'administration.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $motivation = trim($_POST['motivation'] ?? '');

    if (empty($motivation)) {
        $error = "Veuillez rédiger une lettre de motivation pour valider votre demande.";
    } elseif (strlen($motivation) < 20) {
        $error = "Votre motivation est trop courte (minimum 20 caractères).";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO role_applications (user_id, requested_role, motivation) VALUES (?, 'organizer', ?)");
            $stmt->execute([$user_id, $motivation]);
            $success = "Votre candidature a été envoyée avec succès !";
        } catch (Exception $e) {
            $error = "Une erreur technique s'est produite lors de l'enregistrement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Devenir Organisateur</title>
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
                <h1 class="text-gradient-green mb-2">Devenir Organisateur</h1>
                <p class="text-muted small">Obtenez les droits pour créer et planifier des événements sur la plateforme.</p>
            </div>

            <div class="card">
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

                <form action="form-organizer.php" method="POST">
                    <?= csrfField() ?>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium mb-2">Pourquoi souhaitez-vous devenir Organisateur ?</label>
                        <textarea name="motivation" class="form-control" rows="6" 
                                  placeholder="Décrivez vos projets de soirées, le type de films ou les thèmes que vous aimeriez proposer..." 
                                  <?= ($user_role === 'organizer' || $user_role === 'admin') ? 'disabled' : '' ?> required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2" 
                            <?= ($user_role === 'organizer' || $user_role === 'admin') ? 'disabled' : '' ?>>
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