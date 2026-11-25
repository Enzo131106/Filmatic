<?php
require_once __DIR__ . '/security.php';
require_once 'db.php';

// Sécurité : Réservé aux Organisateurs et Admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['organizer', 'admin'])) {
    header("Location: index.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    
    // Récupération des tableaux
    $m_titles = $_POST['movie_titles'] ?? [];
    $m_genres = $_POST['movie_genres'] ?? [];
    $m_langs = $_POST['movie_langs'] ?? [];
    
    $l_names = $_POST['loc_names'] ?? [];
    $l_mins = $_POST['loc_mins'] ?? [];
    $l_maxs = $_POST['loc_maxs'] ?? [];

    // --- VALIDATION DES DATES ---
    $min_date = new DateTime();
    $min_date->modify('+7 days');
    $submitted_date = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);

    // --- VALIDATION DES CAPACITÉS ---
    $capacity_error = count($l_names) !== count($l_mins) || count($l_names) !== count($l_maxs);
    foreach ($l_names as $i => $name) {
        $min = filter_var(($l_mins[$i] ?? '') === '' ? '0' : $l_mins[$i], FILTER_VALIDATE_INT);
        $max = filter_var(($l_maxs[$i] ?? '') === '' ? '0' : $l_maxs[$i], FILTER_VALIDATE_INT);
        if ($min === false || $max === false || $min < 0 || $max < 0 || $min > $max) {
            $capacity_error = true;
            break;
        }
    }

    if (count($m_titles) < 3 || count($m_titles) > 10 || count($l_names) < 3 || count($l_names) > 10) {
        $error = "Vous devez proposer entre 3 et 10 films, et entre 3 et 10 lieux.";
    } elseif (empty($title) || strlen($title) > 100 || !$submitted_date || $submitted_date->format('Y-m-d\TH:i') !== $event_date || count(array_filter($m_titles, fn($v) => trim($v) !== '')) !== count($m_titles) || count(array_filter($l_names, fn($v) => trim($v) !== '')) !== count($l_names)) {
        $error = "Veuillez remplir le titre et la date de l'événement.";
    } elseif ($submitted_date < $min_date) {
        $error = "L'événement doit être planifié au moins 7 jours à l'avance.";
    } elseif ($capacity_error) {
        $error = "Erreur : Pour un ou plusieurs lieux, le nombre minimum de personnes ne peut pas être supérieur au maximum.";
    } else {
        // --- INSCRIPTION EN BASE DE DONNÉES ---
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO events (title, event_date, organizer_id, created_by, status) VALUES (?, ?, ?, ?, 'voting')");
            $stmt->execute([$title, $event_date, $_SESSION['user_id'], $_SESSION['user_id']]);
            $event_id = $pdo->lastInsertId();

            // 2. Insertion des films
            $stmtMovie = $pdo->prepare("INSERT INTO event_movies (event_id, movie_title, genre, language) VALUES (?, ?, ?, ?)");
            foreach ($m_titles as $i => $t) {
                if(!empty(trim($t))) {
                    $stmtMovie->execute([$event_id, trim($t), trim($m_genres[$i] ?? ''), trim($m_langs[$i] ?? '')]);
                }
            }

            // 3. Insertion des lieux
            $stmtLoc = $pdo->prepare("INSERT INTO event_locations (event_id, location_name, min_capacity, max_capacity) VALUES (?, ?, ?, ?)");
            foreach ($l_names as $i => $name) {
                if(!empty(trim($name))) {
                    $stmtLoc->execute([$event_id, trim($name), (int)($l_mins[$i] ?? 0), (int)($l_maxs[$i] ?? 0)]);
                }
            }

            $pdo->commit();
            $success = "Événement créé avec succès !";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "Une erreur technique est survenue.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Planifier une séance</title>
    <link rel="icon" href="./assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-5">
    <div class="container">
        <img src="assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="index.php">FILM<span class="text-luigi-gold">ATIC.</span></a>
        <div class="ms-auto"><a href="index.php" class="btn btn-outline-light btn-sm">Annuler</a></div>
    </div>
</nav>

<div class="container my-4" style="max-width: 1000px;">
    <h1 class="mb-1">Créer une session</h1>
    <?php if ($error): ?><div class="alert alert-danger small"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success small"><?= $success ?></div><?php endif; ?>

    <form method="POST" id="eventForm">
                    <?= csrfField() ?>
        <div class="card mb-4 p-3">
            <h3 class="h5 text-white mb-3">Informations Générales</h3>
            <div class="row g-3">
                <div class="col-md-7"><input type="text" name="title" class="form-control" placeholder="Nom de la soirée" required></div>
                <div class="col-md-5"><input type="datetime-local" name="event_date" class="form-control" required></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 text-white mb-0">Les Films</h3>
                        <button type="button" class="btn btn-sm btn-primary" id="addMovieBtn">+ Ajouter</button>
                    </div>
                    <div id="movies-container" class="d-flex flex-column gap-2">
                        <?php for($i=0; $i<3; $i++): ?>
                        <div class="row g-2">
                            <div class="col-6"><input type="text" name="movie_titles[]" class="form-control form-control-sm" placeholder="Titre" required></div>
                            <div class="col-3"><input type="text" name="movie_genres[]" class="form-control form-control-sm" placeholder="Genre"></div>
                            <div class="col-3"><input type="text" name="movie_langs[]" class="form-control form-control-sm" placeholder="Langue"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 text-white mb-0">Les Lieux</h3>
                        <button type="button" class="btn btn-sm btn-primary" id="addLocationBtn">+ Ajouter</button>
                    </div>
                    <div id="locations-container" class="d-flex flex-column gap-2">
                        <?php for($i=0; $i<3; $i++): ?>
                        <div class="row g-2">
                            <div class="col-6"><input type="text" name="loc_names[]" class="form-control form-control-sm" placeholder="Lieu" required></div>
                            <div class="col-3"><input type="number" name="loc_mins[]" class="form-control form-control-sm" placeholder="Min" min="0"></div>
                            <div class="col-3"><input type="number" name="loc_maxs[]" class="form-control form-control-sm" placeholder="Max" min="0"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-4 py-3 fw-bold">Publier la session</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    var date = new Date();
    date.setDate(date.getDate() + 7);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    var minimum = date.toISOString().slice(0, 16);
    $('input[name="event_date"]').attr("min", minimum);
});

document.getElementById('addMovieBtn').addEventListener('click', function() {
    const container = document.getElementById('movies-container');
    if (container.children.length >= 10) return alert("Limite de 10 atteinte.");
    const div = document.createElement('div');
    div.className = "row g-2 align-items-center";
    div.innerHTML = `
        <div class="col-6"><input type="text" name="movie_titles[]" class="form-control form-control-sm" placeholder="Titre" required></div>
        <div class="col-3"><input type="text" name="movie_genres[]" class="form-control form-control-sm" placeholder="Genre"></div>
        <div class="col-2"><input type="text" name="movie_langs[]" class="form-control form-control-sm" placeholder="Langue"></div>
        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()">X</button></div>
    `;
    container.appendChild(div);
});

document.getElementById('addLocationBtn').addEventListener('click', function() {
    const container = document.getElementById('locations-container');
    if (container.children.length >= 10) return alert("Limite de 10 atteinte.");
    const div = document.createElement('div');
    div.className = "row g-2 align-items-center";
    div.innerHTML = `
        <div class="col-5"><input type="text" name="loc_names[]" class="form-control form-control-sm" placeholder="Lieu" required></div>
        <div class="col-3"><input type="number" name="loc_mins[]" class="form-control form-control-sm" placeholder="Min" min="0"></div>
        <div class="col-3"><input type="number" name="loc_maxs[]" class="form-control form-control-sm" placeholder="Max" min="0"></div>
        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()">X</button></div>
    `;
    container.appendChild(div);
});
</script>
</body>
</html>