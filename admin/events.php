<?php
// admin/events.php
require_once __DIR__ . '/../security.php';
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Suppression d'un événement avec nettoyage en cascade manuel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $e_id = intval($_POST['event_id']);
    try {
        $pdo->beginTransaction();

        // 1. Supprimer d'abord toutes les dépendances de l'événement
        $pdo->prepare("DELETE FROM event_ratings WHERE event_id = ?")->execute([$e_id]);
        $pdo->prepare("DELETE FROM votes WHERE event_id = ?")->execute([$e_id]);
        $pdo->prepare("DELETE FROM event_movies WHERE event_id = ?")->execute([$e_id]);
        $pdo->prepare("DELETE FROM event_locations WHERE event_id = ?")->execute([$e_id]);

        // 2. Supprimer enfin l'événement principal
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$e_id]);

        $pdo->commit();
    } catch(Exception $e){
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Tu peux décommenter la ligne suivante pour déboguer si nécessaire :
        // die("Erreur lors de la suppression.");
    }
    header("Location: events.php");
    exit();
}

// Collecte des événements
$events = [];
try {
    $events = $pdo->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Programmation</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-5">
    <div class="container">
        <img src="../assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="../index.php">FILM<span class="text-luigi-gold">ATIC.</span> <small class="text-muted fs-6">[ADMIN]</small></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="dashboard.php">Vue d'ensemble</a></li>
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="users.php">Utilisateurs</a></li>
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="proposals.php">Propositions</a></li>
                <li class="nav-item"><a class="nav-link active text-luigi-gold" href="events.php">Événements</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="mb-1">Séances Planifiées</h1>
            <p class="text-muted">Consultez et gérez les soirées cinéma projetées sur Filmatic.</p>
        </div>
        <a href="../create-event.php" class="btn btn-primary text-dark btn-sm px-4 fw-bold">Créer une session +</a>
    </div>

    <div class="card p-0 overflow-hidden shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 bg-transparent">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-4 py-3">Nom de l'événement</th>
                        <th class="py-3">Date de projection</th>
                        <th class="py-3">Lieu / Salon</th>
                        <th class="text-end pe-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted small">Aucune projection au calendrier pour le moment.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($events as $e): ?>
                        <tr>
                            <td class="ps-4 fw-semibold text-white"><?= htmlspecialchars($e['title'] ?? $e['name'] ?? '') ?></td>
                            <td class="text-info font-monospace small"><?= date('d/m/Y H:i', strtotime($e['event_date'] ?? $e['date'])) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($e['location'] ?? 'Salon principal') ?></td>
                            <td class="text-end pe-4">
                                <form action="events.php" method="POST" onsubmit="return confirm('Annuler définitivement cette séance ainsi que tous ses votes et propositions associés ?')">
                    <?= csrfField() ?>
                                    <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                                    <button type="submit" name="delete_event" class="btn btn-sm btn-outline-danger py-1">Annuler</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>