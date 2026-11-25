<?php
// admin/proposals.php
require_once __DIR__ . '/../security.php';
require_once '../db.php';

// Sécurité admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// =====================================
// TRAITEMENT ACTIONS (APPROVE / DELETE)
// =====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_id'])) {

    $p_id = (int) $_POST['proposal_id'];

    if ($p_id > 0) {

        // APPROUVER
        if (isset($_POST['approve_proposal'])) {
            $stmt = $pdo->prepare("UPDATE proposals SET status = 'approved' WHERE id = ?");
            $stmt->execute([$p_id]);
        }

        // SUPPRIMER
        if (isset($_POST['delete_proposal'])) {
            $stmt = $pdo->prepare("DELETE FROM proposals WHERE id = ?");
            $stmt->execute([$p_id]);
        }
    }

    header("Location: proposals.php");
    exit();
}

// =====================================
// RÉCUPÉRATION DES PROPOSITIONS
// =====================================
try {
    $stmt = $pdo->query("
        SELECT p.*, u.username
        FROM proposals p
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY p.id DESC
    ");
    $proposals = $stmt->fetchAll();

} catch (Exception $e) {
    $proposals = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Modération Films</title>
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
                <li class="nav-item"><a class="nav-link active text-luigi-gold" href="proposals.php">Propositions</a></li>
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="events.php">Événements</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">

    <div class="mb-4">
        <h1 class="text-white">Boîte à Idées Ciné</h1>
        <p class="text-muted">Modération des propositions de films.</p>
    </div>

    <div class="card bg-dark text-white">
        <div class="table-responsive">

            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-4">Film</th>
                        <th>Utilisateur</th>
                        <th>Statut</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($proposals)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Aucune proposition disponible
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($proposals as $p): ?>

                    <tr>
                        <td class="ps-4">
                            <strong><?= htmlspecialchars($p['title_name'] ?? 'Sans titre') ?></strong>
                            <div class="text-muted small">
                                <?= htmlspecialchars($p['genre'] ?? 'Général') ?>
                            </div>
                        </td>

                        <td class="text-muted small">
                            <?= htmlspecialchars($p['username'] ?? 'Inconnu') ?>
                        </td>

                        <td>
                            <?php
                                $status = $p['status'] ?? 'pending';
                            ?>
                            <span class="badge <?= $status === 'approved' ? 'bg-success' : 'bg-warning' ?> text-dark">
                                <?= strtoupper($status) ?>
                            </span>
                        </td>

                        <td class="text-end pe-4">

                            <form method="POST" class="d-inline-flex gap-2">
                    <?= csrfField() ?>

                                <input type="hidden" name="proposal_id" value="<?= (int)$p['id'] ?>">

                                <?php if (($p['status'] ?? 'pending') !== 'approved'): ?>
                                    <button type="submit"
                                            name="approve_proposal"
                                            class="btn btn-sm btn-primary text-dark">
                                        Approuver
                                    </button>
                                <?php endif; ?>

                                <button type="submit"
                                        name="delete_proposal"
                                        class="btn btn-sm btn-outline-danger">
                                    Retirer
                                </button>

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