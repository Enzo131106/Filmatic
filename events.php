<?php
// events.php
require_once __DIR__ . '/security.php';
require_once 'db.php';

// --- 1. AUTO-ARCHIVAGE AUTOMATIQUE ---
try {
    $sqlAutoArchive = "UPDATE events 
                       SET status = 'archived' 
                       WHERE event_date < NOW() - INTERVAL 1 DAY 
                       AND status != 'archived'";
    $pdo->exec($sqlAutoArchive);
    $pdo->exec("UPDATE events SET status = 'planned' WHERE status = 'voting' AND event_date <= NOW()");
} catch (Exception $e) {}

// --- 2. VÉRIFICATION DE LA CONNEXION ---
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? intval($_SESSION['user_id']) : null;
$user_role = $_SESSION['role'] ?? 'guest';  

// ==========================================
// TRAITEMENT : SUPPRESSION D'UN ÉVÉNEMENT (ADMIN OU CRÉATEUR)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_event'])) {
    if (!$is_logged_in) {
        die("Action non autorisée.");
    }
    
    $delete_id = intval($_POST['delete_event_id']);

    $stmtCheck = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
    $stmtCheck->execute([$delete_id]);
    $event_to_del = $stmtCheck->fetch();

    if ($event_to_del) {
        if ($user_role === 'admin' || ($user_role === 'organizer' && $user_id === intval($event_to_del['organizer_id']))) {
            try {
                $pdo->beginTransaction();
                // Nettoyage en cascade
                $pdo->prepare("DELETE FROM event_ratings WHERE event_id = ?")->execute([$delete_id]);
                $pdo->prepare("DELETE FROM votes WHERE event_id = ?")->execute([$delete_id]);
                $pdo->prepare("DELETE FROM event_movies WHERE event_id = ?")->execute([$delete_id]);
                $pdo->prepare("DELETE FROM event_locations WHERE event_id = ?")->execute([$delete_id]);
                $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$delete_id]);
                $pdo->commit();

                header("Location: events.php?msg=deleted");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                die("Erreur technique de suppression.");
            }
        }
    }
}

// ==========================================
// CAS 1 : AFFICHAGE D'UN ÉVÉNEMENT PRÉCIS (VOTE OU RATING)
// ==========================================
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']);

    $stmt = $pdo->prepare("SELECT e.*, u.username FROM events e LEFT JOIN users u ON e.organizer_id = u.id WHERE e.id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die("Événement introuvable. <a href='events.php'>Retour à la liste</a>");
    }

    $stmtFilms = $pdo->prepare("SELECT * FROM event_movies WHERE event_id = ?");
    $stmtFilms->execute([$event_id]); $films = $stmtFilms->fetchAll();

    $stmtLocations = $pdo->prepare("SELECT * FROM event_locations WHERE event_id = ?");
    $stmtLocations->execute([$event_id]); $locations = $stmtLocations->fetchAll();

    $has_voted_before = false;
    $user_rating = 0;

    if ($is_logged_in) {
        $stmtCheckVote = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE event_id = ? AND user_id = ?");
        $stmtCheckVote->execute([$event_id, $user_id]);
        $has_voted_before = ($stmtCheckVote->fetchColumn() > 0);

        $stmtUserRating = $pdo->prepare("SELECT rating FROM event_ratings WHERE event_id = ? AND user_id = ?");
        $stmtUserRating->execute([$event_id, $user_id]);
        $user_rating = $stmtUserRating->fetchColumn() ?: 0;
    }

    $stmtStats = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM event_ratings WHERE event_id = ?");
    $stmtStats->execute([$event_id]);
    $rating_stats = $stmtStats->fetch();
    $avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : null;
    $total_ratings = $rating_stats['total_ratings'] ?? 0;

    // --- REQUÊTES POUR LES STATISTIQUES ---
    $stmtFilmStats = $pdo->prepare("
        SELECT
            em.movie_title,
            em.genre,
            em.language,
            IFNULL(SUM(v.points), 0) as total_points
        FROM event_movies em
        LEFT JOIN votes v
            ON em.id = v.item_id
            AND v.item_type = 'film' AND v.event_id = em.event_id
        WHERE em.event_id = ?
        GROUP BY em.id, em.movie_title, em.genre, em.language
        ORDER BY total_points DESC, em.movie_title ASC
    ");
    $stmtFilmStats->execute([$event_id]);
    $film_stats = $stmtFilmStats->fetchAll();

    $stmtLocStats = $pdo->prepare("
        SELECT el.location_name, IFNULL(SUM(v.points), 0) as total_points
        FROM event_locations el
        LEFT JOIN votes v ON el.id = v.item_id AND v.item_type = 'location' AND v.event_id = el.event_id
        WHERE el.event_id = ?
        GROUP BY el.id, el.location_name
        ORDER BY total_points DESC, el.location_name ASC
    ");
    $stmtLocStats->execute([$event_id]);
    $location_stats = $stmtLocStats->fetchAll();

    // --- DÉTERMINATION DES GAGNANTS (AVEC TIRAGE AU SORT DÉTERMINISTE SI ÉGALITÉ) ---
    $winner_film = null; $is_film_tie = false;
    if (!empty($film_stats)) {
        $max_f_pts = $film_stats[0]['total_points'];
        $tied_f = array_filter($film_stats, fn($f) => $f['total_points'] == $max_f_pts);
        if (count($tied_f) > 1) {
            $is_film_tie = true;
            // Tirage au sort lié à l'ID pour que le vainqueur ne change pas à chaque F5
            $idx = hexdec(substr(md5($event_id . 'film'), 0, 8)) % count($tied_f);
            $tied_f = array_values($tied_f);
            $winner_film = $tied_f[$idx];
        } else {
            $winner_film = $film_stats[0];
        }
    }

    $winner_loc = null; $is_loc_tie = false;
    if (!empty($location_stats)) {
        $max_l_pts = $location_stats[0]['total_points'];
        $tied_l = array_filter($location_stats, fn($l) => $l['total_points'] == $max_l_pts);
        if (count($tied_l) > 1) {
            $is_loc_tie = true;
            $idx = hexdec(substr(md5($event_id . 'loc'), 0, 8)) % count($tied_l);
            $tied_l = array_values($tied_l);
            $winner_loc = $tied_l[$idx];
        } else {
            $winner_loc = $location_stats[0];
        }
    }

    $vote_success = false; $vote_error = null;
    $rating_success = false; $rating_error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
        if (!$is_logged_in || !$has_voted_before || $event['status'] === 'voting') {
            $rating_error = "Action non autorisée. Seuls les votants de cette session peuvent la noter une fois close.";
        } else {
            $rating_val = intval($_POST['rating'] ?? 0);
            if ($rating_val >= 1 && $rating_val <= 5) {
                try {
                    $stmtRate = $pdo->prepare("INSERT INTO event_ratings (event_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
                    $stmtRate->execute([$event_id, $user_id, $rating_val, $rating_val]);
                    $rating_success = true;
                    $user_rating = $rating_val;

                    $stmtStats->execute([$event_id]);
                    $rating_stats = $stmtStats->fetch();
                    $avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : null;
                    $total_ratings = $rating_stats['total_ratings'] ?? 0;
                } catch (Exception $e) { $rating_error = "Erreur SQL."; }
            } else { $rating_error = "Veuillez sélectionner une note valide entre 1 et 5 étoiles."; }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_vote'])) {
        // 1. Vérification de la capacité max (si la colonne existe)
        $stmtCap = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM votes WHERE event_id = ?");
        $stmtCap->execute([$event_id]);
        $currentParticipants = (int) $stmtCap->fetchColumn();

        $stmtMax = $pdo->prepare("SELECT max_capacity FROM events WHERE id = ?");
        $stmtMax->execute([$event_id]);
        $maxParticipants = (int) $stmtMax->fetchColumn();

        // 2. Définition des erreurs
        if (!$is_logged_in) {
            $vote_error = "Vous devez être connecté pour soumettre un vote.";
        } elseif ($event['status'] !== 'voting') {
            $vote_error = "Les votes sont fermés pour cet événement.";
        } elseif ($has_voted_before) {
            $vote_error = "Vous avez déjà voté pour cet événement.";
        } elseif ($maxParticipants > 0 && $currentParticipants >= $maxParticipants) {
            $vote_error = "Le nombre maximum de participants est atteint. Les votes sont fermés.";
        }

        // 3. Exécution uniquement s'il n'y a PAS d'erreur
        if (empty($vote_error)) {
            $ordered_films = array_filter(explode(',', $_POST['film_order'] ?? ''));
            $ordered_locations = array_filter(explode(',', $_POST['location_order'] ?? ''));

            if (validRanking($ordered_films, $films) && validRanking($ordered_locations, $locations)) {
                try {
                    $pdo->beginTransaction();
                    $lock = $pdo->prepare("SELECT status, max_capacity, (event_date > NOW()) AS vote_open FROM events WHERE id = ? FOR UPDATE");
                    $lock->execute([$event_id]);
                    $lockedEvent = $lock->fetch();
                    $stmtCheckVote->execute([$event_id, $user_id]);
                    $stmtCap->execute([$event_id]);
                    if (!$lockedEvent || $lockedEvent['status'] !== 'voting' || !(int) $lockedEvent['vote_open'] || (int) $stmtCheckVote->fetchColumn() > 0 || ((int) $lockedEvent['max_capacity'] > 0 && (int) $stmtCap->fetchColumn() >= (int) $lockedEvent['max_capacity'])) {
                        throw new RuntimeException("Vote indisponible.");
                    }

                    $stmtVoteFilm = $pdo->prepare("INSERT INTO votes (user_id, event_id, item_id, item_type, points) VALUES (?, ?, ?, 'film', ?)");
                    foreach ($ordered_films as $index => $film_id) {
                        if ($index < 3) { $stmtVoteFilm->execute([$user_id, $event_id, intval($film_id), (3 - $index)]); }
                    }

                    $stmtVoteLoc = $pdo->prepare("INSERT INTO votes (user_id, event_id, item_id, item_type, points) VALUES (?, ?, ?, 'location', ?)");
                    foreach ($ordered_locations as $index => $loc_id) {
                        if ($index < 3) { $stmtVoteLoc->execute([$user_id, $event_id, intval($loc_id), (3 - $index)]); }
                    }

                    $pdo->commit(); 
                    $vote_success = true;
                    $has_voted_before = true; 

                    // Mise à jour des stats pour affichage immédiat
                    $stmtFilmStats->execute([$event_id]); $film_stats = $stmtFilmStats->fetchAll();
                    $stmtLocStats->execute([$event_id]); $location_stats = $stmtLocStats->fetchAll();

                } catch (Exception $e) { 
                    if ($pdo->inTransaction()) { $pdo->rollBack(); } 
                    $vote_error = "Erreur technique."; 
                }
            } else {
                $vote_error = "Erreur lors de la transmission du classement.";
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - <?= htmlspecialchars($event['title']) ?></title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .sortable-list { list-style: none; padding: 0; margin: 0; }
        .sortable-list li { cursor: move; transition: background 0.2s ease; }
        .sortable-list li:hover { background: rgba(255,255,255,0.05) !important; }
        
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: center; gap: 4px; }
        .rating-stars input { display: none; }
        .rating-stars label { cursor: pointer; font-size: 2.5rem; color: #343a40; transition: color 0.15s ease-in-out; }
        .rating-stars input:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label { color: #ffc107; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <img src="assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <div class="ms-auto"><a href="events.php" class="btn btn-outline-light btn-sm">← Revenir à la liste</a></div>
    </div>
</nav>

<div class="container my-4">
    <div class="text-center mb-5">
        <span class="badge bg-dark border border-secondary text-muted mb-2 px-3 py-2" style="border-radius: 20px;">Scrutin Borda Restreint (Top 3)</span>
        <h1 class="display-5 text-gradient-green mb-2"><?= htmlspecialchars($event['title']) ?></h1>
        <p class="text-muted small mb-1">Organisé par : <strong><?= htmlspecialchars($event['username'] ?? 'Anonyme') ?></strong> • Date : <?= date('d/m/Y à H:i', strtotime($event['event_date'])) ?></p>
        
        <p class="mb-2 small">Statut des inscriptions : <span id="countdown" class="text-warning fw-bold">Calcul en cours...</span></p>

        <?php if ($avg_rating): ?>
            <div class="text-warning small mb-3">
                <span class="fs-5 fw-bold">★ <?= $avg_rating ?></span>/5 <span class="text-muted">(<?= $total_ratings ?> avis)</span>
            </div>
        <?php endif; ?>
        
        <?php if ($is_logged_in && ($user_role === 'admin' || $user_id === intval($event['organizer_id']))): ?>
            <form action="events.php" method="POST" onsubmit="return confirm('Confirmer la suppression définitive de cet événement ?');" class="mt-2">
                    <?= csrfField() ?>
                <input type="hidden" name="delete_event_id" value="<?= $ev['id'] ?? $event['id'] ?>">
                <button type="submit" name="action_delete_event" class="btn btn-sm btn-outline-danger">Supprimer l'événement</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($vote_success): ?><div class="alert alert-success bg-success text-dark text-center border-0 fw-bold py-2 mb-4">Classement enregistré avec succès !</div><?php endif; ?>
    <?php if ($vote_error): ?><div class="alert alert-danger bg-danger text-white text-center border-0 py-2 mb-4"><?= $vote_error ?></div><?php endif; ?>
    <?php if ($rating_success): ?><div class="alert alert-success bg-success text-dark text-center border-0 fw-bold py-2 mb-4">Votre note a bien été enregistrée !</div><?php endif; ?>
    <?php if ($rating_error): ?><div class="alert alert-danger bg-danger text-white text-center border-0 py-2 mb-4"><?= $rating_error ?></div><?php endif; ?>

    <?php if (!$is_logged_in): ?>
        <div class="card text-center p-5 border-secondary bg-dark text-white max-width-600 mx-auto shadow">
            <h4 class="mb-2">Accès restreint</h4>
            <p class="text-muted small">Vous devez posséder un compte Filmatic et être connecté pour interagir avec les sessions.</p>
            <div class="mt-4">
                <a href="login.php" class="btn btn-primary text-dark fw-bold px-4">Se connecter</a>
            </div>
        </div>

    <?php elseif ($event['status'] !== 'voting'): ?>
        
        <!-- ==========================================
             AFFICHAGE EXCLUSIF DES RÉSULTATS (VOTE CLOS)
             ========================================== -->
        <div class="row justify-content-center g-4 mb-5">
            
            <!-- Colonne Résultats (Gagnants) -->
            <div class="col-md-6 d-flex">
                <div class="card w-100 p-4 border-warning bg-dark text-white text-center shadow">
                    <h3 class="text-luigi-gold mb-4">Résultats de la session</h3>
                    
                    <div class="mb-4">
                        <h5 class="text-muted small text-uppercase">Film Gagnant</h5>
                        <?php if ($winner_film && $winner_film['total_points'] > 0): ?>
                            <h4 class="text-white fw-bold mb-1">
                                <?= htmlspecialchars($winner_film['movie_title']) ?>
                            </h4>

                            <p class="text-muted small">
                                Genre : <?= htmlspecialchars($winner_film['genre']) ?>
                                • Version : <?= htmlspecialchars($winner_film['language']) ?>
                            </p>
                            <span class="badge bg-primary text-dark fw-bold px-3 py-2"><?= $winner_film['total_points'] ?> pts</span>
                            <?php if ($is_film_tie): ?>
                                <p class="text-warning small mt-2 mb-0"><em>Départagé par tirage au sort suite à une égalité.</em></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Aucun vote enregistré.</p>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="border-secondary w-50 mx-auto my-3">
                    
                    <div class="mt-3">
                        <h5 class="text-muted small text-uppercase">Lieu Gagnant</h5>
                        <?php if ($winner_loc && $winner_loc['total_points'] > 0): ?>
                            <h4 class="text-white fw-bold mb-1 pb-2"><?= htmlspecialchars($winner_loc['location_name']) ?></h4>
                            <span class="badge bg-info text-dark fw-bold px-3 py-2"><?= $winner_loc['total_points'] ?> pts</span>
                            <?php if ($is_loc_tie): ?>
                                <p class="text-warning small mt-2 mb-0"><em>Départagé par tirage au sort suite à une égalité.</em></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Aucun vote enregistré.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne Évaluation -->
            <div class="col-md-6 d-flex">
                <div class="card w-100 p-4 bg-dark border border-secondary text-white shadow-sm d-flex flex-column justify-content-center text-center">
                    <h5 class="mb-2 text-white">Évaluez cette soirée cinéma</h5>
                    <?php if ($has_voted_before): ?>
                        <p class="text-muted small mb-4">Merci d'avoir participé au scrutin ! Quelle note donnez-vous à l'événement général ?</p>
                        <form action="events.php?id=<?= $event_id ?>" method="POST">
                    <?= csrfField() ?>
                            <div class="rating-stars mb-4">
                                <input type="radio" id="star5" name="rating" value="5" <?= $user_rating == 5 ? 'checked' : '' ?>><label for="star5">★</label>
                                <input type="radio" id="star4" name="rating" value="4" <?= $user_rating == 4 ? 'checked' : '' ?>><label for="star4">★</label>
                                <input type="radio" id="star3" name="rating" value="3" <?= $user_rating == 3 ? 'checked' : '' ?>><label for="star3">★</label>
                                <input type="radio" id="star2" name="rating" value="2" <?= $user_rating == 2 ? 'checked' : '' ?>><label for="star2">★</label>
                                <input type="radio" id="star1" name="rating" value="1" <?= $user_rating == 1 ? 'checked' : '' ?>><label for="star1">★</label>
                            </div>
                            <button type="submit" name="submit_rating" class="btn btn-warning text-dark fw-bold px-4">Enregistrer ma note</button>
                        </form>
                    <?php else: ?>
                        <div class="px-3 py-4 border border-danger rounded bg-dark">
                            <p class="text-danger small mb-0 font-monospace">Accès restreint : Seuls les membres ayant soumis un classement Borda lors de la phase active peuvent noter cet événement.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php elseif ($has_voted_before): ?>
        <div class="row justify-content-center">
            <div class="col-md-7 text-center">
                <div class="card p-5 border-success mb-4 bg-dark">
                    <h3 class="text-success mb-2">Vote enregistré !</h3>
                    <p class="text-muted mb-0">Vous avez soumis votre classement pour cet événement. Le vote étant définitif, vous ne pouvez plus modifier votre choix. Les résultats finaux apparaîtront ici dès la clôture.</p>
                </div>
            </div>
        </div>

    <?php elseif (!$vote_error && ($maxParticipants <= 0 || $currentParticipants < $maxParticipants)): ?>
        <form id="voteForm" action="events.php?id=<?= $event_id ?>" method="POST">
                    <?= csrfField() ?>
            <input type="hidden" id="film_order" name="film_order" value="">
            <input type="hidden" id="location_order" name="location_order" value="">

            <div class="row justify-content-center g-4">
                <div class="col-lg-5 col-md-6">
                    <div class="card p-4">
                        <h4 class="mb-1 text-white">Ordre des Films</h4>
                        <p class="text-muted small mb-4">Glissez vos 3 préférés tout en haut.</p>
                        <ul id="sortable-films" class="sortable-list d-flex flex-column gap-2">
                            <?php foreach ($films as $film): ?>
                                <li data-id="<?= $film['id'] ?>" class="d-flex align-items-center justify-content-between p-2 rounded bg-dark border border-secondary">

                                    <div>
                                        <div class="text-white fw-bold">
                                            <?= htmlspecialchars($film['movie_title']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Genre : <?= htmlspecialchars($film['genre']) ?>
                                            • Version : <?= htmlspecialchars($film['language']) ?>
                                        </small>
                                    </div>

                                    <span class="rank-badge badge px-2 py-1">-</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-5 col-md-6">
                    <div class="card p-4">
                        <h4 class="mb-1 text-white">Ordre des Lieux</h4>
                        <p class="text-muted small mb-4">Seul votre podium récolte des points.</p>
                        <ul id="sortable-locations" class="sortable-list d-flex flex-column gap-2">
                            <?php foreach ($locations as $loc): ?>
                                <li data-id="<?= $loc['id'] ?>" class="d-flex align-items-center justify-content-between p-2 rounded bg-dark border border-secondary">

                                    <div>
                                        <div class="text-white fw-bold">
                                            <?= htmlspecialchars($loc['location_name']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Participants max : <?= intval($loc['max_capacity']) ?>
                                        </small>
                                    </div>

                                    <span class="rank-badge badge px-2 py-1">-</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <button type="submit" name="submit_vote" class="btn btn-primary px-5 py-3 text-dark fw-bold">Soumettre mon classement</button>
            </div>
        </form>
    <?php endif; ?>

    <!-- ==========================================
         BLOC STATISTIQUES EN DIRECT (UNIQUEMENT PENDANT LE VOTE)
         ========================================== -->
    <?php if ($is_logged_in && $event['status'] === 'voting' && $has_voted_before): ?>
        <div class="row justify-content-center mt-5">
            <div class="col-md-10">
                <div class="card p-4 bg-dark border border-secondary text-white shadow">
                    <h4 class="text-gradient-green mb-4 text-center">Évolution du scrutin en direct</h4>
                    <div class="row g-4">
                        
                        <div class="col-md-6">
                            <h5 class="text-gradient-green border-bottom border-secondary pb-2 mb-3">Classement Provisoire des Films</h5>
                            <div class="d-flex flex-column gap-3">
                                <?php 
                                $max_film_points = count($film_stats) > 0 ? max(array_column($film_stats, 'total_points')) : 0;
                                foreach($film_stats as $index => $f_stat): 
                                    $pct_film = $max_film_points > 0 ? ($f_stat['total_points'] / $max_film_points) * 100 : 0;
                                ?>
                                    <div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span><strong>#<?= $index + 1 ?></strong> <?= htmlspecialchars($f_stat['movie_title']) ?></span>
                                            <span class="badge bg-primary text-dark fw-bold"><?= $f_stat['total_points'] ?> pts</span>
                                        </div>
                                        <div class="progress" style="height: 8px; background-color: #2b3035;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct_film ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="text-gradient-green border-bottom border-secondary pb-2 mb-3">Classement Provisoire des Lieux</h5>
                            <div class="d-flex flex-column gap-3">
                                <?php 
                                $max_loc_points = count($location_stats) > 0 ? max(array_column($location_stats, 'total_points')) : 0;
                                foreach($location_stats as $index => $l_stat): 
                                    $pct_loc = $max_loc_points > 0 ? ($l_stat['total_points'] / $max_loc_points) * 100 : 0;
                                ?>
                                    <div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span><strong>#<?= $index + 1 ?></strong> <?= htmlspecialchars($l_stat['location_name']) ?></span>
                                            <span class="badge bg-primary text-dark fw-bold"><?= $l_stat['total_points'] ?> pts</span>
                                        </div>
                                        <div class="progress" style="height: 8px; background-color: #2b3035;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= $pct_loc ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    
    var dateStr = "<?= date('d-m-Y H:i:s', strtotime($event['event_date'])) ?>";
    var parts = dateStr.split(" ");
    var dateParts = parts[0].split("-");
    var timeParts = parts[1].split(":");

    var dateLimite = new Date(
        dateParts[2], dateParts[1] - 1, dateParts[0],
        timeParts[0], timeParts[1], timeParts[2]
    );

    function mettreAJourCompte(){
        var maintenant = new Date();
        var diff = dateLimite - maintenant;

        if(diff <= 0){
            $("#countdown").text("Fermées - Événement clos");
            clearInterval(interval);
            return;
        }

        var heures   = Math.floor(diff / (1000 * 60 * 60));
        var minutes  = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        heures   = heures.toString().padStart(2, "0");
        minutes  = minutes.toString().padStart(2, "0");

        $("#countdown").text("Fermeture dans " + heures + "h " + minutes + "m ");
    }

    var interval = setInterval(mettreAJourCompte, 1000);
    mettreAJourCompte();

    function updateRankNumbers() {
        $('#sortable-films li').each(function(i) {
            var badge = $(this).find('.rank-badge');
            badge.attr('class', 'rank-badge badge'); 
            if (i === 0) badge.text('1er (+3 pts)').addClass('bg-success text-dark fw-bold');
            else if (i === 1) badge.text('2ème (+2 pts)').addClass('bg-info text-dark fw-bold');
            else if (i === 2) badge.text('3ème (+1 pt)').addClass('bg-warning text-dark fw-bold');
            else badge.text('Exclu (0 pt)').addClass('bg-secondary text-white-50');
        });
        
        $('#sortable-locations li').each(function(i) {
            var badge = $(this).find('.rank-badge');
            badge.attr('class', 'rank-badge badge'); 
            if (i === 0) badge.text('1er (+3 pts)').addClass('bg-success text-dark fw-bold');
            else if (i === 1) badge.text('2ème (+2 pts)').addClass('bg-info text-dark fw-bold');
            else if (i === 2) badge.text('3ème (+1 pt)').addClass('bg-warning text-dark fw-bold');
            else badge.text('Exclu (0 pt)').addClass('bg-secondary text-white-50');
        });
    }

    if ($("#sortable-films").length > 0) {
        $("#sortable-films, #sortable-locations").sortable({ update: function() { updateRankNumbers(); } });
        updateRankNumbers();
    }

    $("#voteForm").submit(function() {
        var filmIds = []; $("#sortable-films li").each(function() { filmIds.push($(this).data('id')); });
        $("#film_order").val(filmIds.join(','));
        var locIds = []; $("#sortable-locations li").each(function() { locIds.push($(this).data('id')); });
        $("#location_order").val(locIds.join(','));
        return true; 
    });
});
</script>
</body>
</html>

<?php
// ==========================================
// CAS 2 : LISTE GLOBALE DE TOUS LES ÉVÉNEMENTS
// ==========================================
} else {
    $stmt = $pdo->query("SELECT e.*, u.username, (SELECT COUNT(DISTINCT user_id) FROM votes WHERE event_id = e.id) as total_votes FROM events e LEFT JOIN users u ON e.organizer_id = u.id ORDER BY e.event_date ASC");
    $all_events = $stmt->fetchAll();

    $voting_events = []; $planned_events = []; $archived_events = [];
    foreach ($all_events as $ev) {
        if ($ev['status'] === 'voting') $voting_events[] = $ev;
        elseif ($ev['status'] === 'planned') $planned_events[] = $ev;
        else $archived_events[] = $ev;
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filmatic - Liste des Événements</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <img src="assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link px-3 active text-luigi-gold" href="events.php">Événements</a></li>
                <li class="nav-item"><a class="nav-link px-3 text-luigi-gold" href="profile.php">Mon Profil</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success bg-success text-dark fw-bold border-0 text-center py-2 mb-4">L'événement a bien été supprimé définitivement.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="text-gradient-green mb-1">Sessions Cinéma</h1>
            <p class="text-muted mb-0">Découvrez, classez et rejoignez les séances.</p>
        </div>
        <a href="create-event.php" class="btn btn-primary text-dark">+ Créer un événement</a>
    </div>

    <h3 class="mb-4 text-white">Scrutins ouverts</h3>
    <div class="row mb-5">
        <?php foreach($voting_events as $ev): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="text-white mb-2"><?= htmlspecialchars($ev['title']) ?></h5>
                            <?php if ($is_logged_in && ($user_role === 'admin' || $user_id === intval($ev['organizer_id']))): ?>
                                <form action="events.php" method="POST" onsubmit="return confirm('Supprimer cet événement ?');">
                    <?= csrfField() ?>
                                    <input type="hidden" name="delete_event_id" value="<?= $ev['id'] ?>">
                                    <button type="submit" name="action_delete_event" class="btn btn-link text-danger p-0 border-0" style="font-size: 0.85rem;">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-3">Par : <?= htmlspecialchars($ev['username'] ?? 'Anonyme') ?> • Le <?= date('d/m/Y', strtotime($ev['event_date'])) ?></p>
                    </div>
                    <div class="mt-4 d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><?= $ev['total_votes'] ?> participant(s)</span>
                        <a href="events.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm text-dark px-3"><?= $is_logged_in ? 'Voter' : 'Voir l\'événement' ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
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
<?php } ?>