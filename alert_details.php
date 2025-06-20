<?php
// Définition des chemins de base pour les fichiers d'alertes et d'actions
$alertsBase = __DIR__ . '/data/alerts/';
$actionsBase = __DIR__ . '/data/actions/';

// Récupération des paramètres GET
$id = $_GET['id'] ?? null;
$client = $_GET['client'] ?? null;
$date = $_GET['date'] ?? null;

// Vérification de la présence des paramètres requis
if (!$id || !$client || !$date) {
    die("Paramètres manquants (id, client ou date).");
}

// Construction des chemins des fichiers
$alertsFile = "$alertsBase/$client/$date.json";
$actionsFile = "$actionsBase/$client/{$date}_actions.json";

// Vérification de l'existence du fichier d'alertes
if (!file_exists($alertsFile)) {
    die("Fichier d'alertes non trouvé pour ce client/date.");
}

// Lecture et décodage du fichier d'alertes
$alerts = json_decode(file_get_contents($alertsFile), true);
if (!is_array($alerts)) {
    die("Fichier d'alertes corrompu.");
}

// Recherche de l'alerte spécifique
$alertData = null;
foreach ($alerts as $alert) {
    if ($alert['id'] === $id) {
        $alertData = $alert;
        break;
    }
}

// Vérification si l'alerte a été trouvée
if (!$alertData) {
    die("Alerte introuvable.");
}

// Chargement de l'historique des actions
$actions = [];
if (file_exists($actionsFile)) {
    $allActions = json_decode(file_get_contents($actionsFile), true);
    foreach ($allActions as $entry) {
        if (($entry['id'] ?? '') === $id) {
            $actions[] = $entry;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails de l'alerte</title>
    <!-- Inclusion de Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
    <!-- Titre principal -->
    <h2>Détail de l'alerte</h2>
    <!-- Tableau des détails de l'alerte -->
    <table class="table table-bordered mt-3">
        <tr><th>ID</th><td><?= htmlspecialchars($alertData['id']) ?></td></tr>
        <tr><th>Timestamp</th><td><?= htmlspecialchars($alertData['timestamp']) ?></td></tr>
        <tr><th>Niveau</th><td><?= htmlspecialchars($alertData['level']) ?></td></tr>
        <tr><th>Type</th><td><?= htmlspecialchars($alertData['type']) ?></td></tr>
        <tr><th>Message</th><td><?= nl2br(htmlspecialchars($alertData['message'])) ?></td></tr>
        <tr><th>Client</th><td><?= htmlspecialchars($client) ?></td></tr>
        <tr><th>Date</th><td><?= htmlspecialchars($date) ?></td></tr>
        <tr><th>Status</th><td><?= htmlspecialchars($alertData['status'] ?? 'open') ?></td></tr>
    </table>

    <!-- Section historique des actions -->
    <?php if (!empty($actions)): ?>
        <h4>Historique des actions</h4>
        <table class="table table-striped">
            <thead>
                <tr><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($actions as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['timestamp'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
