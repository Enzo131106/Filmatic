<?php
// recruitements/dashboard.php
require_once __DIR__ . '/../security.php';
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'participant';

// --- TRAITEMENT ADMIN : ACCEPTER / REFUSER UN RECRUTEMENT ---
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = intval($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $pdo->prepare("UPDATE role_applications SET status = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$status, $app_id]);

        if ($action === 'approve' && $stmt->rowCount() === 1) {
            $stmtGetTarget = $pdo->prepare("SELECT user_id, requested_role FROM role_applications WHERE id = ?");
            $stmtGetTarget->execute([$app_id]);
            $target = $stmtGetTarget->fetch();
            
            if ($target) {
                $stmtUpdateRole = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmtUpdateRole->execute([$target['requested_role'], $target['user_id']]);
            }
        }
        header("Location: dashboard.php");
        exit();
    }
}

// --- COLLECTE DES DONNÉES ---
$all_applications = [];
if ($user_role === 'admin') {
    $stmt = $pdo->query("
        SELECT ra.*, u.username, u.role AS user_role 
        FROM role_applications ra
        JOIN users u ON ra.user_id = u.id
        WHERE ra.status = 'pending'
        ORDER BY ra.created_at ASC
    ");
    $all_applications = $stmt->fetchAll();
}

$stmtMy = $pdo->prepare("SELECT * FROM role_applications WHERE user_id = ? ORDER BY created_at DESC");
$stmtMy->execute([$user_id]);
$my_applications = $stmtMy->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Recrutements</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <img src="../assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="../index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="../events.php">Événements</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="../proposals.php">Propositions</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="../profile.php">Mon Profil</a></li>
                <li class="nav-item"><form method="POST" action="../logout.php" class="d-inline"><?= csrfField() ?><button type="submit" class="btn btn-outline-danger btn-sm ms-2">Déconnexion</button></form></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="text-gradient-green mb-1">Espace Recrutements</h1>
            <p class="text-muted mb-0">Grade actuel : <span class="badge border border-success text-success px-2 py-1" style="background: rgba(0, 230, 118, 0.1);"><?= strtoupper($user_role) ?></span></p>
        </div>
        <a href="../index.php" class="btn btn-outline-light btn-sm px-4">Retour au site</a>
    </div>

    <?php if ($user_role === 'admin'): ?>
        <div class="card mb-5 border-success">
            <div class="d-flex align-items-center mb-4">
                <h2 class="h4 mb-0 text-white">Demandes de rôles en attente</h2>
            </div>
            
            <?php if (empty($all_applications)): ?>
                <div class="text-center py-4 text-muted small">
                    <p class="mb-0">Aucune candidature n'a été soumise pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 bg-transparent">
                        <thead>
                            <tr class="text-muted small">
                                <th>Candidat</th>
                                <th>Grade Visé</th>
                                <th>Lettre de Motivation</th>
                                <th class="text-end">Décision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong class="text-white d-block"><?= htmlspecialchars($app['username']) ?></strong>
                                        <span class="text-muted" style="font-size: 0.75rem;">Actuel: <?= htmlspecialchars($app['user_role']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-light font-monospace">
                                            <?= strtoupper($app['requested_role']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-secondary" style="max-width: 350px; white-space: pre-wrap; font-size: 0.85rem;"><?= htmlspecialchars($app['motivation']) ?></td>
                                    <td class="text-end">
                                        <form action="dashboard.php" method="POST" class="d-inline-gap">
                    <?= csrfField() ?>
                                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-primary px-3 me-2 text-dark">Accepter</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger px-3">Refuser</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100 d-flex flex-column justify-content-between">
                <div>
                    <h3 class="h5 text-white mb-3">Changer de Statut</h3>
                    <p class="text-muted small">Soumettez votre candidature pour débloquer de nouveaux privilèges sur Filmatic.</p>
                </div>
                <div class="d-grid gap-3 mt-4">
                    <a href="form-organizer.php" class="btn btn-primary text-dark text-start px-3 py-2">
                        Postuler comme Organisateur
                    </a>
                    <a href="form-admin.php" class="btn btn-outline-light text-start px-3 py-2">
                        Postuler comme Admin
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <h3 class="h5 text-white mb-4">Historique de vos requêtes</h3>
                
                <?php if (empty($my_applications)): ?>
                    <div class="text-center py-5 text-muted small">
                        <p class="mb-0">Vous n'avez envoyé aucune candidature pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0 bg-transparent">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Grade demandé</th>
                                    <th>Motivation</th>
                                    <th class="text-end">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_applications as $my_app): ?>
                                    <tr>
                                        <td class="fw-bold text-white"><?= strtoupper($my_app['requested_role']) ?></td>
                                        <td class="small text-muted text-truncate" style="max-width: 280px;">
                                            <?= htmlspecialchars($my_app['motivation']) ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($my_app['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark px-3 py-1">En attente</span>
                                            <?php elseif ($my_app['status'] === 'approved'): ?>
                                                <span class="badge bg-success text-dark px-3 py-1">Accepté</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger text-white px-3 py-1">Refusé</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>