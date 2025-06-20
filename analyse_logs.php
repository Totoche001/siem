<?php
// Définir le fuseau horaire sur Paris
date_default_timezone_set('Europe/Paris');

// Répertoire de base pour les alertes
$alertBase = __DIR__ . '/data/alerts/';
// Obtenir la liste des répertoires clients (en excluant . et ..)
$clients = array_filter(scandir($alertBase), function($v) use ($alertBase) {
    return $v !== '.' && $v !== '..' && is_dir("$alertBase/$v");
});

echo "Résumé des alertes par client et date:\n\n";

// Parcourir chaque répertoire client
foreach ($clients as $client) {
    $clientDir = "$alertBase/$client";
    $files = scandir($clientDir);
    // Traiter chaque fichier dans le répertoire client
    foreach ($files as $file) {
        // Vérifier si le fichier correspond au format de date YYYY-MM-DD.json
        if (preg_match('/^\d{4}-\d{2}-\d{2}\.json$/', $file)) {
            $path = "$clientDir/$file";
            // Charger et décoder le contenu du fichier JSON
            $logs = json_decode(file_get_contents($path), true) ?? [];
            // Initialiser les compteurs pour chaque niveau de log
            $counts = ['CRITICAL'=>0, 'WARNING'=>0, 'INFO'=>0];
            // Compter les occurrences de chaque niveau de log
            foreach ($logs as $log) {
                $lvl = strtoupper($log['level'] ?? 'INFO');
                if (isset($counts[$lvl])) $counts[$lvl]++;
            }
            // Afficher le résumé pour le client et la date en cours
            echo "Client: $client, Date: " . substr($file,0,10) . "\n";
            echo "  Critiques: {$counts['CRITICAL']}, Avertissements: {$counts['WARNING']}, Infos: {$counts['INFO']}\n\n";
        }
    }
}
