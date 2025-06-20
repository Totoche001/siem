<?php
// Définition du fuseau horaire par défaut
date_default_timezone_set('Europe/Paris');

// Définition des chemins et récupération de la liste des clients
$actionsBase = __DIR__ . '/data/actions/';
$clients = array_filter(scandir($actionsBase), fn($c) => $c !== '.' && $c !== '..');

// Récupération des paramètres GET avec valeurs par défaut
$client = $_GET['client'] ?? ($clients[0] ?? null);
$date = $_GET['date'] ?? date('Y-m-d');
$actionsFile = "$actionsBase/$client/{$date}_actions.json";

// Lecture du fichier d'historique des actions
$actions = [];
if (file_exists($actionsFile)) {
    $actions = json_decode(file_get_contents($actionsFile), true);
    if (!is_array($actions)) $actions = [];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des actions - <?= htmlspecialchars($client) ?></title>
    <!-- Styles CSS pour la mise en forme -->
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #f0f0f0; }
        tr:nth-child(even) { background: #fefefe; }
        /* Couleurs de fond selon le statut */
        .resolved { background-color: #e0f7fa; }
        .deleted { background-color: #ffebee; }
        .in_progress { background-color: #fff3e0; }
    </style>
</head>
<body>
    <!-- Titre de la page -->
    <h1>Historique des actions – Client <?= htmlspecialchars($client) ?> – Date <?= htmlspecialchars($date) ?></h1>

    <!-- Formulaire de sélection du client et de la date -->
    <form method="get">
        Client :
        <select name="client" onchange="this.form.submit()">
            <?php foreach ($clients as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $c === $client ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>

        Date :
        <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
    </form>

    <!-- Affichage des actions ou message si aucune action -->
    <?php if (empty($actions)): ?>
        <p><em>Aucune action enregistrée pour ce jour.</em></p>
    <?php else: ?>
        <!-- Tableau des actions -->
        <table>
            <thead>
                <tr>
                    <th>Horodatage</th>
                    <th>ID Alerte</th>
                    <th>Statut Appliqué</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actions as $a): ?>
                    <tr class="<?= htmlspecialchars($a['status']) ?>">
                        <td><?= htmlspecialchars($a['time']) ?></td>
                        <td><code><?= htmlspecialchars($a['id']) ?></code></td>
                        <td><strong><?= strtoupper($a['status']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Lien de retour -->
    <p><a href="index.php?client=<?= urlencode($client) ?>&date=<?= urlencode($date) ?>">⬅ Retour aux alertes</a></p>
</body>
</html>
