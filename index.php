<?php
// index.php - Script principal de gestion des alertes SIEM
date_default_timezone_set('Europe/Paris');

// Configuration des chemins de base pour les fichiers de données
$alertsBase = __DIR__ . '/data/alerts/';
$actionsBase = __DIR__ . '/data/actions/';

// Récupération de la liste des clients et paramètres de l'URL
$clients = array_filter(scandir($alertsBase), fn($c) => $c !== '.' && $c !== '..');
$client = $_GET['client'] ?? ($clients[0] ?? null);
$date = $_GET['date'] ?? date('Y-m-d');
$perPage = isset($_GET['perpage']) ? max(10, (int)$_GET['perpage']) : 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Paramètres de filtrage
$levels = ['critical', 'warning', 'info'];
$selectedLevel = $_GET['level'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

// Chemins des fichiers de données
$alertsFile = "$alertsBase/$client/$date.json";
$actionsFile = "$actionsBase/$client/{$date}_actions.json";

// Vérification de l'existence du fichier d'alertes
if (!file_exists($alertsFile)) {
    echo "Aucun fichier d'alertes trouvé pour ce client/date.";
    exit;
}
$alerts = json_decode(file_get_contents($alertsFile), true);
if (!is_array($alerts)) $alerts = [];

// Fonction de journalisation pour le débogage
function logDebug($message)
{
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Traitement des actions POST (mise à jour du statut des alertes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    logDebug("Début du traitement de l'action pour l'ID: $id avec le statut: $status");
    
    if ($id && in_array($status, ['resolved', 'deleted', 'in_progress', 'open'])) {
        logDebug("ID et statut valides. Traitement en cours...");
        // Création du répertoire des actions si nécessaire
        $actionsDir = dirname($actionsFile);
        if (!is_dir($actionsDir)) {
            mkdir($actionsDir, 0755, true);
            logDebug("Répertoire des actions créé avec succès.");
        } else {
            logDebug("Le répertoire des actions existe déjà.");
        }

        // Chargement de l'historique des actions
        $history = [];
        if (file_exists($actionsFile)) {
            $history = json_decode(file_get_contents($actionsFile), true) ?: [];
            logDebug("Historique chargé depuis le fichier existant.");
        } else {
            logDebug("Aucun fichier d'historique existant trouvé. Initialisation d'un nouvel historique.");
        }

        // Ajout de la nouvelle action
        $history[] = [
            'id' => $id,
            'status' => $status,
            'time' => date('c')
        ];

        // Sauvegarde de l'historique
        if (file_put_contents($actionsFile, json_encode($history, JSON_PRETTY_PRINT))) {
            logDebug("Historique mis à jour enregistré avec succès dans le fichier.");
        } else {
            logDebug("Échec de l'enregistrement de l'historique mis à jour dans le fichier.");
        }

        // Mise à jour du statut dans le fichier des alertes
        $alertUpdated = false;
        foreach ($alerts as &$alert) {
            if ($alert['id'] === $id) {
                $alert['status'] = $status;
                $alertUpdated = true;
                logDebug("Statut de l'alerte mis à jour: " . $alert['id']);
                break;
            }
        }

        if ($alertUpdated) {
            if (file_put_contents($alertsFile, json_encode($alerts, JSON_PRETTY_PRINT))) {
                logDebug("Alertes mises à jour enregistrées avec succès dans le fichier.");
            } else {
                logDebug("Échec de l'enregistrement des alertes mises à jour dans le fichier.");
            }
        } else {
            logDebug("Aucune alerte trouvée avec l'ID: $id");
        }

        // Redirection pour éviter la resoumission
        header("Location: ?client=$client&date=$date&perpage=$perPage&page=$page&status=$selectedStatus");
        exit;
    } else {
        logDebug("ID ou statut invalide. Aucune action effectuée.");
    }
}

// Application des actions existantes aux alertes
if (file_exists($actionsFile)) {
    $history = json_decode(file_get_contents($actionsFile), true);
    foreach ($history as $action) {
        foreach ($alerts as &$alert) {
            if ($alert['id'] === $action['id']) {
                $alert['status'] = $action['status'];
            }
        }
    }
}

// Filtrage des alertes selon les critères sélectionnés
$filtered = array_filter($alerts, function ($alert) use ($selectedLevel, $selectedType, $selectedStatus) {
    $levelMatch = empty($selectedLevel) || strtolower($alert['level']) === strtolower($selectedLevel);
    $typeMatch = empty($selectedType) || strtolower($alert['type']) === strtolower($selectedType);
    $statusMatch = empty($selectedStatus) || (isset($alert['status']) && strtolower($alert['status']) === strtolower($selectedStatus));

    return $levelMatch && $typeMatch && $statusMatch;
});

// Gestion de la pagination
$total = count($filtered);
$pages = ceil($total / $perPage);
$page = min($page, max(1, $pages));
$offset = ($page - 1) * $perPage;
$visibleAlerts = array_slice($filtered, $offset, $perPage);

// Récupération des types d'alertes uniques
$types = array_unique(array_map(fn($a) => strtolower($a['type']), $alerts));
sort($types);

// Vérification des alertes critiques non résolues
$hasCritical = !empty(array_filter($filtered, fn($a) => strtolower($a['level']) === 'critical' && (!isset($a['status']) || $a['status'] === 'open' || $a['status'] === 'in_progress')));
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Monitoring - <?= htmlspecialchars($client) ?> - <?= htmlspecialchars($date) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="assets/script.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="bg-light">

    <div class="container py-4">
        <h1>SIEM - Client <?= htmlspecialchars($client) ?> (<?= $date ?>)</h1>

<!----------------------------------------------------------------------------------------------------------------
Encart pour une alerte critique
------------------------------------------------------------------------------------------------------------------>
        <?php if ($hasCritical): ?>
            <div class="alert alert-danger">
                ⚠️ Attention : au moins une alerte critique non résolue détectée !
            </div>
        <?php endif; ?>

<!----------------------------------------------------------------------------------------------------------------
Menu de sélection des alertes
------------------------------------------------------------------------------------------------------------------>

        <form method="get" class="card p-3 mb-4">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="client" class="form-label">Client :</label>
                    <select id="client" name="client" onchange="this.form.submit()" class="form-select">
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $c === $client ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="date" class="form-label">Date :</label>
                    <input type="date" id="date" name="date" value="<?= $date ?>" onchange="this.form.submit()" class="form-control" />
                </div>

                <div class="col-md-2">
                    <label for="level" class="form-label">Niveau :</label>
                    <select id="level" name="level" onchange="this.form.submit()" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach ($levels as $l): ?>
                            <option value="<?= $l ?>" <?= strtolower($selectedLevel) === strtolower($l) ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="type" class="form-label">Type :</label>
                    <select id="type" name="type" onchange="this.form.submit()" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t ?>" <?= strtolower($selectedType) === strtolower($t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="perpage" class="form-label">Affichage :</label>
                    <select id="perpage" name="perpage" onchange="this.form.submit()" class="form-select">
                        <?php foreach ([10, 25, 50, 100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?> / page</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">Statut :</label>
                    <select id="status" name="status" onchange="this.form.submit()" class="form-select">
                        <option value="">Tous</option>
                        <option value="open" <?= strtolower($selectedStatus) === 'open' ? 'selected' : '' ?>>Ouvert</option>
                        <option value="in_progress" <?= strtolower($selectedStatus) === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                        <option value="resolved" <?= strtolower($selectedStatus) === 'resolved' ? 'selected' : '' ?>>Résolu</option>
                        <option value="deleted" <?= strtolower($selectedStatus) === 'deleted' ? 'selected' : '' ?>>Supprimé</option>
                    </select>
                </div>
            </div>
        </form>

<!----------------------------------------------------------------------------------------------------------------
Pagination
------------------------------------------------------------------------------------------------------------------>

        <nav aria-label="Page navigation" class="my-3">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?client=<?= $client ?>&date=<?= $date ?>&page=1&perpage=<?= $perPage ?>&level=<?= $selectedLevel ?>&type=<?= $selectedType ?>&status=<?= $selectedStatus ?>">«</a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($pages, $page + 2);

                if ($start > 1): ?>
                    <li class="page-item"><span class="page-link">...</span></li>
                <?php endif;

                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?client=<?= $client ?>&date=<?= $date ?>&page=<?= $i ?>&perpage=<?= $perPage ?>&level=<?= $selectedLevel ?>&type=<?= $selectedType ?>&status=<?= $selectedStatus ?>"><?= $i ?></a>
                    </li>
                <?php endfor;

                if ($end < $pages): ?>
                    <li class="page-item"><span class="page-link">...</span></li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?client=<?= $client ?>&date=<?= $date ?>&page=<?= $pages ?>&perpage=<?= $perPage ?>&level=<?= $selectedLevel ?>&type=<?= $selectedType ?>&status=<?= $selectedStatus ?>">»</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

<!----------------------------------------------------------------------------------------------------------------
Sélection des alertes problématiques à traiter
------------------------------------------------------------------------------------------------------------------>
        <?php
        $problematicAlerts = array_filter($alerts, function ($alert) {
            $level = strtolower($alert['level'] ?? '');
            $status = strtolower($alert['status'] ?? 'open');
            return in_array($level, ['critical', 'warning']) &&
                in_array($status, ['open', 'in_progress']);
        });

        ?>

        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                ⚠️ Alertes Problématiques à Traiter
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Heure</th>
                            <th>Niveau</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($problematicAlerts)): ?>
                            <?php foreach ($problematicAlerts as $alert): ?>
                                <tr class="table-<?= $alert['level'] === 'critical' ? 'danger' : 'warning' ?>">
                                    <td><?= htmlspecialchars($alert['timestamp']) ?></td>
                                    <td><span class="badge bg-<?= $alert['level'] === 'critical' ? 'danger' : 'warning' ?>">
                                            <?= htmlspecialchars($alert['level']) ?></span></td>
                                    <td><?= htmlspecialchars($alert['type']) ?></td>
                                    <td>
                                        <a href="alert_details.php?id=<?= urlencode($alert['id']) ?>&client=<?= urlencode($client) ?>&date=<?= urlencode($date) ?>" target="_blank">
                                            <?= htmlspecialchars($alert['message']) ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-<?= $alert['status'] === 'in_progress' ? 'warning' : 'primary' ?>">
                                            <?= htmlspecialchars($alert['status'] ?? 'open') ?></span></td>
                                    <td>
                                        <form method="post" class="d-inline-flex gap-1">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($alert['id']) ?>">
                                            <button class="btn btn-sm btn-success" name="status" value="resolved" title="Résolu">✔️</button>
                                            <button class="btn btn-sm btn-warning" name="status" value="in_progress" title="En cours">🔄</button>
                                            <button class="btn btn-sm btn-danger" name="status" value="deleted" title="Supprimer">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-success">
                                    ✅ Aucune alerte critique ou en cours à traiter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!----------------------------------------------------------------------------------------------------------------
Tableau principal des alertes
------------------------------------------------------------------------------------------------------------------>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Heure</th>
                            <th>Niveau</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visibleAlerts as $alert): ?>
                            <tr class="table-<?= $alert['level'] === 'critical' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'info') ?>">
                                <td><?= htmlspecialchars($alert['timestamp']) ?></td>
                                <td><span class="badge bg-<?= $alert['level'] === 'critical' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'info') ?>"><?= htmlspecialchars($alert['level']) ?></span></td>
                                <td><?= htmlspecialchars($alert['type']) ?></td>
                                <td>
                                    <a href="alert_details.php?id=<?= urlencode($alert['id']) ?>&client=<?= urlencode($client) ?>&date=<?= urlencode($date) ?>" target="_blank">
                                        <?= htmlspecialchars($alert['message']) ?>
                                    </a>
                                </td>

                                <td><span class="badge bg-<?= $alert['status'] === 'resolved' ? 'success' : ($alert['status'] === 'in_progress' ? 'warning' : ($alert['status'] === 'deleted' ? 'danger' : 'primary')) ?>"><?= htmlspecialchars($alert['status'] ?? 'open') ?></span></td>
                                <td>
                                    <form method="post" class="d-inline-flex gap-1">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($alert['id']) ?>">
                                        <button class="btn btn-sm btn-success" name="status" value="resolved" title="Résolu">✔️</button>
                                        <button class="btn btn-sm btn-warning" name="status" value="in_progress" title="En cours">🔄</button>
                                        <button class="btn btn-sm btn-danger" name="status" value="deleted" title="Supprimer">🗑️</button>
                                    </form>
                                    <?php if (!empty($alert['status']) && $alert['status'] !== 'open'): ?>
                                        <form method="post" class="d-inline-flex">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($alert['id']) ?>">
                                            <button class="btn btn-sm btn-secondary" name="status" value="open" title="Réouvrir">↺</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!----------------------------------------------------------------------------------------------------------------
Pagination
------------------------------------------------------------------------------------------------------------------>
        <nav aria-label="Page navigation" class="my-3">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?client=<?= $client ?>&date=<?= $date ?>&page=1&perpage=<?= $perPage ?>&level=<?= $selectedLevel ?>&type=<?= $selectedType ?>&status=<?= $selectedStatus ?>">«</a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($pages, $page + 2);

                if ($start > 1): ?>
                    <li class="page-item"><span class="page-link">...</span></li>
                <?php endif;

                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?client=<?= $client ?>&date=<?= $date ?>&page=<?= $i ?>&perpage=<?= $perPage ?>&level=<?= $selectedLevel ?>&type=<?= $selectedType ?>&status=<?= $selectedStatus ?>"><?= $i ?></a>
                    </li>
                <?php endfor;

                if ($end < $pages): ?>
                    <li class="page-item"><span class="page-link">...</span></li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?client=<?= $client ?>&date=<?= $date ?>&page=<?= $pages ?>&perpage=<?= $perPage ?><?= $selectedLevel ? "&level=$selectedLevel" : '' ?><?= $selectedType ? "&type=$selectedType" : '' ?>">»</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

</body>

</html>