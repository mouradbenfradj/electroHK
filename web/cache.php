<?php
// Fichier : /home/mbfdevb/www/web/cache.php

// Activer l'affichage des erreurs temporairement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Tenter d'exécuter la commande Thelia cache:clear via le bon binaire PHP
$phpBin = PHP_BINDIR . '/php'; // Chemin du PHP actuel (ex: /usr/bin/php)
$theliaScript = dirname(__DIR__) . '/Thelia';

if (file_exists($theliaScript)) {
    // Construire la commande en échappant les arguments
    $command = escapeshellcmd($phpBin . ' ' . escapeshellarg($theliaScript) . ' cache:clear 2>&1');
    $output = shell_exec($command);

    // Si la commande a produit une sortie, l'afficher
    if ($output !== null) {
        echo '<h3>Résultat de la commande cache:clear</h3>';
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
        exit; // On s'arrête ici si ça a fonctionné
    }
}

// 2. Si la commande a échoué (ou que shell_exec est désactivé), on passe à la méthode manuelle
echo '<h3>La commande a échoué, utilisation de la méthode manuelle</h3>';

require_once dirname(__DIR__) . '/core/vendor/autoload.php';

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

$fs = new Filesystem();
$cacheDir = dirname(__DIR__) . '/cache';
$logDir   = dirname(__DIR__) . '/log';
$messages = [];

try {
    // Vider le cache
    if ($fs->exists($cacheDir)) {
        $fs->remove($cacheDir);
        $messages[] = '✅ Cache vidé avec succès (manuel).';
    } else {
        $messages[] = 'ℹ️ Le dossier cache n\'existe pas.';
    }

    // Vider les logs (supprimer les fichiers, pas le dossier lui-même)
    if ($fs->exists($logDir)) {
        $logFiles = glob($logDir . '/*');
        if (!empty($logFiles)) {
            $fs->remove($logFiles);
            $messages[] = '✅ Logs vidés avec succès (manuel).';
        } else {
            $messages[] = 'ℹ️ Aucun fichier de log à supprimer.';
        }
    } else {
        $messages[] = 'ℹ️ Le dossier logs n\'existe pas.';
    }

    // Afficher les résultats
    foreach ($messages as $msg) {
        echo "<p>$msg</p>";
    }
} catch (IOExceptionInterface $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
