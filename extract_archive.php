<?php
// Vérifie si le paramètre 'archive' est présent dans l'URL
if (!isset($_GET['archive'])) {
    http_response_code(400);
    echo "Fichier manquant.";
    exit;
}

// Récupère le nom de l'archive et construit le chemin complet
$archive = $_GET['archive'];
$archivePath = realpath("data/logs/" . $archive);

// Vérifie si le fichier existe et s'il est dans le répertoire autorisé
if (!$archivePath || !file_exists($archivePath) || strpos($archivePath, realpath("data/logs/")) !== 0) {
    http_response_code(403);
    echo "Fichier interdit ou introuvable.";
    exit;
}

// Crée un répertoire temporaire unique pour l'extraction
$tmpDir = sys_get_temp_dir() . '/extracted_' . uniqid();
mkdir($tmpDir);

// Extrait l'archive tar.gz dans le répertoire temporaire
$cmd = "tar -xzf " . escapeshellarg($archivePath) . " -C " . escapeshellarg($tmpDir);
shell_exec($cmd);

// Liste les fichiers JSON extraits (recherche récursive puis non récursive si rien trouvé)
$files = glob("$tmpDir/**/*.json", GLOB_BRACE);
if (empty($files)) $files = glob("$tmpDir/*.json");

// Configure l'en-tête de la réponse en texte brut
header('Content-Type: text/plain');
// Affiche le contenu de chaque fichier JSON
foreach ($files as $file) {
    echo "\n\n===== $file =====\n";
    echo file_get_contents($file);
}

// Nettoie le répertoire temporaire à la fin du script
register_shutdown_function(function () use ($tmpDir) {
    shell_exec("rm -rf " . escapeshellarg($tmpDir));
});
