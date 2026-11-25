<?php
// admin/dashboard.php
require_once __DIR__ . '/../security.php';
require_once '../db.php';

// Sécurité : Seul un admin peut entrer ici
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Récupération des statistiques (Ajuste les noms de tables si nécessaire)
$nb_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nb_apps = $pdo->query("SELECT COUNT(*) FROM role_applications WHERE status = 'pending'")->fetchColumn();

// Bloc try/catch au cas où tes tables "proposals" et "events" ont des noms légèrement différents
try { $nb_proposals = $pdo->query("SELECT COUNT(*) FROM proposals")->fetchColumn(); } catch(Exception $e) { $nb_proposals = 0; }
try { $nb_events = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(); } catch(Exception $e) { $nb_events = 0; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Admin Dashboard</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-5">
    <div class="container">
        <img src="../assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="../index.php">FILM<span class="text-luigi-gold">ATIC.</span> <small class="text-muted fs-6">[ADMIN]</small></a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link active px-3 text-luigi-gold" href="dashboard.php">Vue d'ensemble</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="users.php">Utilisateurs</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="proposals.php">Propositions</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="events.php">Événements</a></li>
                <li class="nav-item"><form method="POST" action="../logout.php" class="d-inline"><?= csrfField() ?><button type="submit" class="btn btn-outline-danger btn-sm ms-3">Déconnexion</button></form></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="mb-5">
        <h1 class="text-gradient-green mb-1">Panneau d'Administration</h1>
        <p class="text-muted">Gestion globale des utilisateurs, des films et du contenu du manoir Filmatic.</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card text-center">
                <h3 class="text-muted small text-uppercase fw-semibold tracking-wider mb-2">Utilisateurs</h3>
                <div class="display-5 fw-bold text-white"><?= $nb_users ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border-color: rgba(0, 176, 255, 0.3);">
                <h3 class="text-muted small text-uppercase fw-semibold tracking-wider mb-2">Propositions</h3>
                <div class="display-5 fw-bold" style="color: var(--ghost-blue);"><?= $nb_proposals ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <h3 class="text-muted small text-uppercase fw-semibold tracking-wider mb-2">Événements</h3>
                <div class="display-5 fw-bold text-white"><?= $nb_events ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border-color: rgba(0, 230, 118, 0.3);">
                <h3 class="text-muted small text-uppercase fw-semibold tracking-wider mb-2">Recrutements</h3>
                <div class="display-5 fw-bold" style="color: var(--luigi-green);"><?= $nb_apps ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <h2 class="h5 text-white mb-3">⚡ Actions de Modération</h2>
                <p class="text-muted small mb-4">Modifiez à la volée les rôles des membres de la communauté ou nettoyez les comptes inactifs.</p>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-primary text-dark">Gérer les membres</a>
                    <a href="../recruitements/dashboard.php" class="btn btn-outline-light">Voir les candidatures (<?= $nb_apps ?>)</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <h2 class="h5 text-white mb-3">🎬 Gestion du Contenu Cine</h2>
                <p class="text-muted small mb-4">Validez les propositions de films soumises par les spectateurs ou organisez la programmation.</p>
                <div class="d-flex gap-2">
                    <a href="proposals.php" class="btn btn-outline-light me-2">Trier les propositions</a>
                    <a href="events.php" class="btn btn-outline-light">Planifier une session</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>