<?php 
require_once __DIR__ . '/security.php'; 
require_once 'db.php'; // Requis pour interroger la base de données

$stmtCarousel = $pdo->query("
    SELECT e.*, u.username,
           (SELECT AVG(rating) FROM event_ratings WHERE event_id = e.id) as avg_rating,
           (SELECT COUNT(*) FROM event_ratings WHERE event_id = e.id) as total_ratings
    FROM events e 
    LEFT JOIN users u ON e.organizer_id = u.id 
    ORDER BY e.event_date DESC 
    LIMIT 5
");

$carousel_events = $stmtCarousel->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Accueil</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Ajustements mineurs pour intégrer proprement le carrousel dans le thème sombre */
        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            filter: invert(0) grayscale(100);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
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
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="profile.php">Mon Profil</a></li>
                    <li class="nav-item"><form method="POST" action="logout.php" class="d-inline"><?= csrfField() ?><button type="submit" class="btn btn-outline-danger btn-sm ms-2">Déconnexion</button></form></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="login.php">Connexion</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-primary btn-sm text-dark" href="register.php">S'inscrire</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link px-3 fw-semibold text-luigi-gold" href="recruitements/dashboard.php">
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            Gestion Rôles
                        <?php else: ?>
                            Recrutements
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5 pt-5">
    <div class="row justify-content-center text-center">
        <div class="col-lg-8">
            <h1 class="display-3 text-gradient-green mb-4">Décidez ensemble du prochain frisson.</h1>
            <p class="lead text-muted mb-5 fs-5">Une plateforme moderne pour organiser vos soirées cinéma. Proposez des films, votez intelligemment avec le système Borda et planifiez vos sessions.</p>
            <div class="d-flex justify-content-center gap-3 mb-5">
                <a href="events.php" class="btn btn-primary btn-lg px-4 text-dark fw-bold">Découvrir les événements</a>
                <a href="create-event.php" class="btn btn-outline-light btn-lg px-4">Créer une session</a>
            </div>
            
            <?php if (!empty($carousel_events)): ?>
                <div id="eventsCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($carousel_events as $index => $ev): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <!-- On crée un conteneur visible partout avec une hauteur minimale de 300px -->
                                <div class="d-flex flex-column justify-content-center align-items-center bg-dark bg-opacity-75 rounded p-5 border border-secondary border-opacity-25" style="min-height: 300px;">
                                    
                                    <h2 class="text-gradient-green mb-2"><?= htmlspecialchars($ev['title']) ?></h2>
                                    <p class="text-muted small mb-3">Le <?= date('d/m/Y', strtotime($ev['event_date'])) ?></p>

                                    <!-- AFFICHAGE DE LA NOTE -->
                                    <?php if ($ev['avg_rating'] !== null): ?>
                                        <div class="mb-4">
                                            <span class="text-warning fs-5 fw-bold">★ <?= round($ev['avg_rating'], 1) ?></span><span class="text-white-50">/5</span>
                                            <span class="text-muted small ms-1">(<?= $ev['total_ratings'] ?> <?= $ev['total_ratings'] > 1 ? 'avis' : 'avis' ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-4 text-muted small fst-italic">
                                            Pas encore d'évaluations
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="events.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm text-dark fw-bold px-4">
                                        <?= $ev['status'] === 'voting' ? 'Participer au vote' : 'Voir les détails' ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Flèches de navigation -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#eventsCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#eventsCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            <?php else: ?>
                <!-- Petit message de secours pour toi si ta base de données est vide -->
                <div class="alert alert-secondary bg-dark text-muted border-secondary border-opacity-25 py-4">
                    Aucun événement disponible pour le moment.
                </div>
            <?php endif; ?>
            </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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