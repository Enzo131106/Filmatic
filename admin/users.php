<?php
// admin/users.php
require_once __DIR__ . '/../security.php';
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// --- TRAITEMENT FORMULAIRE : ACTION ADMIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_role'])) {
        $u_id = intval($_POST['user_id']);
        $new_role = $_POST['role'];
        if ($u_id !== (int) $_SESSION['user_id'] && in_array($new_role, ['participant', 'organizer', 'admin'], true)) {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $u_id]);
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $u_id = intval($_POST['user_id']);
        // Empêcher l'admin de se supprimer lui-même
        if ($u_id !== (int) $_SESSION['user_id']) {
            try {
                $pdo->beginTransaction();
                foreach (['votes', 'event_ratings', 'role_applications'] as $table) {
                    $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$u_id]);
                }
                $pdo->prepare("UPDATE proposals SET user_id = NULL WHERE user_id = ?")->execute([$u_id]);
                $pdo->prepare("UPDATE events SET organizer_id = NULL WHERE organizer_id = ?")->execute([$u_id]);
                $pdo->prepare("UPDATE events SET created_by = NULL WHERE created_by = ?")->execute([$u_id]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$u_id]);
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                http_response_code(500);
                exit("La suppression a échoué.");
            }
        }
    }
    header("Location: users.php");
    exit();
}

// Récupération de tous les utilisateurs
$users = $pdo->query("SELECT id, username, email, role FROM users ORDER BY username ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmatic - Gestion Utilisateurs</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-5">
    <div class="container">
        <img src="../assets/img/logo.png" alt="Logo Filmatic" width="40" class="me-2">
        <a class="navbar-brand" href="../index.php">FILM<span class="text-luigi-gold">ATIC.</span> <small class="text-muted fs-6">[ADMIN]</small></a></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="dashboard.php">Vue d'ensemble</a></li>
                <li class="nav-item"><a class="nav-link active text-luigi-gold" href="users.php">Utilisateurs</a></li>
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="proposals.php">Propositions</a></li>
                <li class="nav-item"><a class="nav-link text-luigi-gold" href="events.php">Événements</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="mb-5">
        <h1 class="text-white mb-1">Membres du Manoir</h1>
        <p class="text-muted">Modifiez les privilèges des comptes enregistrés sur la plateforme.</p>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 bg-transparent">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-4 py-3">Pseudonyme</th>
                        <th class="py-3">Adresse Email</th>
                        <th class="py-3">Rôle Actuel</th>
                        <th class="text-end pe-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4 fw-semibold text-white"><?= htmlspecialchars($u['username']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge border font-monospace px-2 py-1 <?= $u['role'] === 'admin' ? 'border-warning text-warning' : ($u['role'] === 'organizer' ? 'border-info text-info' : 'border-secondary text-secondary') ?>">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <form action="users.php" method="POST" class="d-inline-flex gap-2">
                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm bg-dark border-secondary text-white py-1 rounded" style="width: 130px; font-size:0.85rem;">
                                        <option value="participant" <?= $u['role'] === 'participant' ? 'selected' : '' ?>>Participant</option>
                                        <option value="organizer" <?= $u['role'] === 'organizer' ? 'selected' : '' ?>>Organisateur</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-sm btn-primary text-dark py-1 px-2" style="font-size:0.85rem;">Sauver</button>
                                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirm('Bannir définitivement cet utilisateur ?')" style="font-size:0.85rem;">Exclure</button>
                                    <?php endif; ?>
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