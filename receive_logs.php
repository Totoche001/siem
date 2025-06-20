<?php
// receive_logs.php
// Définit le fuseau horaire par défaut
date_default_timezone_set('Europe/Paris');

// Définit les chemins de base pour les logs et les alertes
$logBase = __DIR__ . '/data/logs/';
$alertBase = __DIR__ . '/data/alerts/';

// Prépare et crée le répertoire si nécessaire
function prepareDir($base, $client, $sub = '') {
    $path = rtrim($base, '/') . "/$client" . ($sub ? "/$sub" : "");
    if (!is_dir($path)) mkdir($path, 0777, true);
    return $path;
}

// Fonction pour parser les logs Linux au format : "jun 19 13:14:22 hostname process[pid]: message"
function parseLinuxLogLine($line) {
    // Analyse la ligne avec une expression régulière
    if (preg_match('/^(\w{3})\s+(\d{1,2})\s+(\d{2}:\d{2}:\d{2})\s+([\w\-.]+)\s+([^\[]+)(?:\[(\d+)\])?:\s+(.*)$/', trim($line), $m)) {
        // Conversion du mois en format numérique
        $monthStr = strtolower($m[1]);
        $months = array_flip(['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']);
        $month = isset($months[$monthStr]) ? $months[$monthStr] + 1 : 1;
        
        // Formatage de la date
        $day = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $time = $m[3];
        $year = date('Y');
        $timestamp = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-$day $time";

        // Retourne les informations structurées du log
        return [
            'timestamp' => $timestamp,
            'level' => 'INFO',
            'type' => 'SYSTEM',
            'source' => trim($m[5]),
            'message' => trim($m[7]),
            'status' => 'new'
        ];
    }
    return null;
}

// Vérifie si la requête est valide (POST avec fichier et client)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST['client'])) {
    // Nettoie le nom du client
    $client = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['client']);
    $dateDir = date('Y-m-d');
    
    // Prépare les répertoires de destination
    $logDir = prepareDir($logBase, $client, $dateDir);
    $alertDir = prepareDir($alertBase, $client);

    $filename = basename($_FILES['file']['name']);
    $destLog = "$logDir/$filename";

    // Déplace le fichier uploadé vers sa destination finale
    if (move_uploaded_file($_FILES['file']['tmp_name'], $destLog)) {
        echo "Fichier brut stocké : $destLog\n";

        // Parsing du fichier en JSON
        $jsonEntries = [];
        $lines = file($destLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $entry = parseLinuxLogLine($line);
            if ($entry) {
                // Génère un ID unique pour chaque entrée
                $entry['id'] = md5($entry['timestamp'] . $entry['level'] . $entry['type'] . $entry['message']);
                $jsonEntries[] = $entry;
            }
        }

        // Si des entrées ont été trouvées, les sauvegarde en JSON
        if (!empty($jsonEntries)) {
            $jsonFile = "$alertDir/$dateDir.json";
            $existing = [];

            // Fusion avec ancien JSON si existe
            if (file_exists($jsonFile)) {
                $existing = json_decode(file_get_contents($jsonFile), true) ?? [];
            }

            // Fusionne et sauvegarde les données
            $merged = array_merge($existing, $jsonEntries);
            file_put_contents($jsonFile, json_encode($merged, JSON_PRETTY_PRINT));
            echo "Fichier JSON mis à jour : $jsonFile\n";
        } else {
            echo "Aucune ligne compatible trouvée pour JSON.\n";
        }

        // Effectue une rotation du fichier si sa taille dépasse 1 Go
        if (filesize($destLog) > 1073741824) {
            $zipPath = "$destLog.tar.gz";
            exec("tar -czf \"$zipPath\" -C \"$logDir\" \"$filename\"");
            unlink($destLog);
            echo "Rotation : $filename compressé.\n";
        }

    } else {
        // Erreur lors du transfert du fichier
        http_response_code(500);
        echo "Erreur lors du transfert.\n";
    }
} else {
    // Requête invalide
    http_response_code(400);
    echo "Requête incomplète.\n";
}
