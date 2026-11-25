<?php
// proposals.php
require_once __DIR__ . '/security.php';
require_once 'db.php';

// On vérifie si l'utilisateur est connecté pour lui permettre de proposer
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

$success = null;
$error = null;

// Traitement de la soumission d'une proposition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proposal'])) {
    if (!$is_logged_in) {
        $error = "Vous devez être connecté pour proposer une idée.";
    } else {
        $type = $_POST['type'] ?? ''; // 'film' ou 'location'
        $title_name = trim($_POST['title_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($title_name) || strlen($title_name) > 150 || !in_array($type, ['film', 'location'], true)) {
            $error = "Le titre ou le nom est obligatoire.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO proposals (user_id, type, title_name, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $type, $title_name, $description]);
                $success = "Votre suggestion a été ajoutée à la boîte aux idées !";
            } catch (Exception $e) {
                $error = "Erreur lors de l'enregistrement.";
            }
        }
    }
}

// Récupération de toutes les propositions avec le nom de l'auteur
$stmt = $pdo->query("
    SELECT p.*, u.username 
    FROM proposals p 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
");
$all_proposals = $stmt->fetchAll();

// Séparation des propositions par type pour l'affichage
$film_proposals = [];
$location_proposals = [];

foreach ($all_proposals as $prop) {
    if ($prop['type'] === 'film') {
        $film_proposals[] = $prop;
    } else {
        $location_proposals[] = $prop;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - Boîte à Idées</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <img src="assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="events.php">Événements</a></li>
                <li class="nav-item"><a class="nav-link px-3 active text-luigi-gold" href="proposals.php">Propositions</a></li>
                <?php if($is_logged_in): ?>
                    <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="profile.php">Mon Profil</a></li>
                    <li class="nav-item"><form method="POST" action="logout.php" class="d-inline"><?= csrfField() ?><button type="submit" class="btn btn-outline-danger btn-sm ms-2">Déconnexion</button></form></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="login.php">Connexion</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-primary btn-sm text-dark" href="register.php">S'inscrire</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="text-center mb-5">
        <h1 class="display-5 text-gradient-green mb-2">La Boîte à Idées</h1>
        <p class="text-muted">Un espace collaboratif pour souffler des films ou des lieux insolites avant les votes officiels.</p>
    </div>

    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card shadow-lg">
                <h4 class="mb-3 text-white">Soumettre une suggestion</h4>
                
                <?php if ($error): ?><div class="alert alert-danger bg-danger text-white border-0 py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success bg-success text-dark border-0 py-2 small fw-bold"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <?php if ($is_logged_in): ?>
                    <form action="proposals.php" method="POST">
                    <?= csrfField() ?>
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-medium">Catégorie</label>
                                <select name="type" class="form-control bg-dark text-white border-secondary" required style="height: 48px;">
                                    <option value="film">🎬 Film</option>
                                    <option value="location">📍 Lieu</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-muted small fw-medium">Nom de l'idée</label>
                                <input type="text" name="title_name" class="form-control" required placeholder="Ex: Shutter Island / Chez Loïc" style="height: 48px;">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="submit_proposal" class="btn btn-primary w-100 text-dark fw-bold" style="height: 48px;">Ajouter l'idée</button>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label text-muted small fw-medium">Note / Argumentaire (Optionnel)</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Pourquoi ce choix ? Genre, capacité, ambiance..."></textarea>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-3">
                        <p class="text-secondary mb-3">Vous devez être connecté pour enrichir la boîte à idées.</p>
                        <a href="login.php" class="btn btn-outline-light btn-sm px-4">Se connecter</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <h3 class="mb-4 text-white d-flex align-items-center gap-2">Idées de Films <span class="badge bg-dark border border-secondary text-muted fs-6"><?= count($film_proposals) ?></span></h3>
            
            <?php if (empty($film_proposals)): ?>
                <p class="text-muted small">Aucune idée de film pour l'instant. Soyez le premier !</p>
            <?php else: ?>
                <?php foreach ($film_proposals as $prop): ?>
                    <div class="card mb-3 p-3 bg-card border-subtle">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="text-white mb-1"><?= htmlspecialchars($prop['title_name']) ?></h5>
                            <span class="text-muted small" style="font-size: 0.75rem;"><?= date('d/m H:i', strtotime($prop['created_at'])) ?></span>
                        </div>
                        <p class="text-secondary small mb-2"><?= htmlspecialchars($prop['description'] ?: 'Aucune description fournie.') ?></p>
                        <div class="text-muted small mt-1" style="font-size: 0.8rem;">Suggéré par <strong class="text-white"><?= htmlspecialchars($prop['username'] ?? 'Anonyme') ?></strong></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-md-6 mb-4">
            <h3 class="mb-4 text-white d-flex align-items-center gap-2">Idées de Lieux <span class="badge bg-dark border border-secondary text-muted fs-6"><?= count($location_proposals) ?></span></h3>
            
            <?php if (empty($location_proposals)): ?>
                <p class="text-muted small">Aucune idée de lieu pour l'instant. Proposez votre salon !</p>
            <?php else: ?>
                <?php foreach ($location_proposals as $prop): ?>
                    <div class="card mb-3 p-3 bg-card border-subtle">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="text-white mb-1"><?= htmlspecialchars($prop['title_name']) ?></h5>
                            <span class="text-muted small" style="font-size: 0.75rem;"><?= date('d/m H:i', strtotime($prop['created_at'])) ?></span>
                        </div>
                        <p class="text-secondary small mb-2"><?= htmlspecialchars($prop['description'] ?: 'Aucune description fournie.') ?></p>
                        <div class="text-muted small mt-1" style="font-size: 0.8rem;">Suggéré par <strong class="text-white"><?= htmlspecialchars($prop['username'] ?? 'Anonyme') ?></strong></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<footer class="text-center text-white py-4 shadow rounded footer">
    <div class="d-flex flex-column text-center pt-5 mt-5">
    </div>
    <div class="text-center">
        <h3><a href="Mentions-legales.php" class="text-decoration-none link text-luigi-gold">Mentions légales</a></h3>
        <p>&copy; 2026 Filmatic. Tous droits réservés.</p>
    </div>
</footer>
</html>