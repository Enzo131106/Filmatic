<?php
// profile.php
require_once __DIR__ . '/security.php';
require_once 'db.php';

// Sécurité : On bloque l'accès si l'utilisateur n'est pas connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Récupération des informations de l'utilisateur
$stmtUser = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

// 2. Récupération des événements pour lesquels l'utilisateur a voté
$stmtVotes = $pdo->prepare("
    SELECT e.id, e.title, e.event_date, e.status 
    FROM events e 
    JOIN votes v ON e.id = v.event_id 
    WHERE v.user_id = ? 
    GROUP BY e.id 
    ORDER BY e.event_date DESC
");
$stmtVotes->execute([$user_id]);
$voted_events = $stmtVotes->fetchAll();

// 3. Récupération des événements créés par l'utilisateur (s'il est organisateur ou admin)
$created_events = [];
if ($user['role'] === 'organizer' || $user['role'] === 'admin') {
    $stmtCreated = $pdo->prepare("SELECT id, title, event_date, status FROM events WHERE organizer_id = ? ORDER BY event_date DESC");
    $stmtCreated->execute([$user_id]);
    $created_events = $stmtCreated->fetchAll();
}

// Fonction pour traduire le statut en un badge visuel
function getStatusBadge($status) {
    switch($status) {
        case 'voting': return '<span class="badge bg-warning text-dark">Votes en cours</span>';
        case 'planned': return '<span class="badge bg-success">Planifié</span>';
        case 'archived': return '<span class="badge bg-secondary">Terminé</span>';
        default: return '<span class="badge bg-light text-dark">Inconnu</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - Mon Profil</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <img src="assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="events.php">Événements</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="proposals.php">Propositions</a></li>
                <li class="nav-item"><a class="nav-link px-3 active text-luigi-gold" href="profile.php">Mon Profil</a></li>
                <?php if ($user['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="btn btn-outline-info btn-sm ms-2" href="admin/dashboard.php">Panel Admin</a></li>
                <?php endif; ?>
                <li class="nav-item"><form method="POST" action="logout.php" class="d-inline"><?= csrfField() ?><button type="submit" class="btn btn-outline-danger btn-sm ms-2">Déconnexion</button></form></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card text-center shadow-lg border-0">
                <div class="mb-3 mt-3">
                    <img src="https://api.dicebear.com/6.x/bottts/svg?seed=<?= urlencode($user['username']) ?>" alt="Avatar" width="120" class="rounded-circle border border-3 border-success bg-dark p-2">
                </div>
                <h3 class="mb-1 text-white"><?= htmlspecialchars($user['username']) ?></h3>
                <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>
                
                <div class="mb-4">
                    <span class="badge border border-success text-success px-3 py-2 text-uppercase" style="background: rgba(0, 230, 118, 0.1);">
                        Rôle : <?= htmlspecialchars($user['role']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            
            <div class="card mb-4 shadow-sm border-0">
                <h4 class="mb-4 text-gradient-green">Historique de mes votes</h4>
                <?php if (count($voted_events) > 0): ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($voted_events as $event): ?>
                            <a href="events.php?id=<?= $event['id'] ?>" class="list-group-item list-group-item-action bg-transparent border-secondary text-white d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($event['title']) ?></h6>
                                    <small class="text-muted">Prévu le : <?= date('d/m/Y', strtotime($event['event_date'])) ?></small>
                                </div>
                                <div><?= getStatusBadge($event['status']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Vous n'avez participé à aucun scrutin pour le moment.</p>
                    <a href="events.php" class="btn btn-sm btn-outline-light mt-2">Voir les événements en cours</a>
                <?php endif; ?>
            </div>

            <?php if ($user['role'] === 'organizer' || $user['role'] === 'admin'): ?>
                <div class="card shadow-sm border-0">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0 text-gradient-green">Événements que j'ai organisés</h4>
                        <a href="create-event.php" class="btn btn-sm btn-primary text-dark fw-bold">Créer +</a>
                    </div>
                    
                    <?php if (count($created_events) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Titre</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th class="text-end" style="min-width: 220px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($created_events as $event): ?>
                                        <tr>
                                            <td class="fw-medium"><?= htmlspecialchars($event['title']) ?></td>
                                            <td class="text-muted small"><?= date('d/m/Y', strtotime($event['event_date'])) ?></td>
                                            <td><?= getStatusBadge($event['status']) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <a href="events.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-light px-2 py-1">Voir</a>
                                                    
                                                    <a href="edit-event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-warning px-2 py-1">Modifier</a>
                                                    
                                                    <form action="events.php" method="POST" onsubmit="return confirm('Confirmer la suppression définitive de cet événement ?');" class="d-inline m-0">
                    <?= csrfField() ?>
                                                        <input type="hidden" name="delete_event_id" value="<?= $event['id'] ?>">
                                                        <button type="submit" name="action_delete_event" class="btn btn-sm btn-outline-danger px-2 py-1">Supprimer</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Vous n'avez pas encore organisé de rassemblement.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>