<?php
// edit-event.php
require_once __DIR__ . '/security.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'guest';

if (!isset($_GET['id'])) {
    header("Location: profile.php");
    exit();
}

$event_id = intval($_GET['id']);

// Charger l'événement
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die("Événement introuvable.");
}

// Sécurité : Seul l'admin ou le créateur de la ligne peut modifier
if ($user_role !== 'admin' && ($user_role !== 'organizer' || (int) $event['organizer_id'] !== $user_id)) {
    die("Accès refusé : vous n'êtes pas l'organisateur de cette session.");
}

$error = null; $success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $status = $_POST['status'] ?? '';

    $submittedDate = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);
    if (empty($title) || strlen($title) > 100 || !in_array($status, ['voting', 'planned', 'archived'], true) || !$submittedDate || $submittedDate->format('Y-m-d\TH:i') !== $event_date) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE events SET title = ?, event_date = ?, status = ? WHERE id = ?");
        if ($stmtUpdate->execute([$title, $event_date, $status, $event_id])) {
            $success = "Session mise à jour avec succès !";
            // Recharger les données fraîches
            $event['title'] = $title; $event['event_date'] = $event_date; $event['status'] = $status;
        } else {
            $error = "Une erreur est survenue lors de l'enregistrement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - Modifier la session</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container my-5" style="max-width: 600px;">
    <div class="mb-4">
        <h1 class="text-gradient-green h2">Modifier la session</h1>
        <p class="text-muted">Ajustez les paramètres globaux de votre événement.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-danger bg-danger text-white border-0 small mb-4"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success bg-success text-dark fw-bold border-0 small mb-4"><?= $success ?></div><?php endif; ?>

    <form method="POST" class="card p-4">
                    <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label text-muted small">Nom de la soirée</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($event['title']) ?>" required>
        </div>
        
        <div class="mb-3">
            <label class="form-label text-muted small">Date et Heure</label>
            <input type="datetime-local" name="event_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($event['event_date'])) ?>" required>
        </div>

        <div class="mb-4">
            <label class="form-label text-muted small">Statut de la session</label>
            <select name="status" class="form-select">
                <option value="voting" <?= $event['status'] === 'voting' ? 'selected' : '' ?>>Scrutin ouvert (Borda actif)</option>
                <option value="planned" <?= $event['status'] === 'planned' ? 'selected' : '' ?>>Séance validée / confirmée</option>
                <option value="archived" <?= $event['status'] === 'archived' ? 'selected' : '' ?>>Séance passée / archivée</option>
            </select>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary text-dark fw-bold w-100">Enregistrer les modifications</button>
            <a href="profile.php" class="btn btn-outline-secondary">Retour</a>
        </div>
    </form>
</div>
</body>
</html>