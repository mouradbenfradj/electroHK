<?php

$root = dirname(__DIR__);
$script = $root . '/setup/gpt.php';

echo "<pre>";

echo "=== Diagnostic PHP ===\n";
echo "PHP version (web): " . PHP_VERSION . "\n";
echo "PHP_BINARY: " . PHP_BINARY . "\n";
$foundCli = find_php_cli();
echo "CLI binaire trouvé: " . $foundCli . "\n";

$directCandidates = [
    '/images/legacy/usr/local/php5.6/bin/php',
    '/images/legacy/usr/local/php7.4/bin/php',
    '/images/legacy/usr/local/php8.0/bin/php',
    '/usr/local/php5.6/bin/php',
    '/usr/local/php7.4/bin/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
];
echo "Vérification des binaires:\n";
foreach ($directCandidates as $c) {
    $exists = file_exists($c) ? 'existe' : 'absent';
    $exec   = is_executable($c) ? 'exécutable' : 'non-exécutable';
    echo "  $c => $exists / $exec\n";
}
$whichPhp = trim((string)shell_exec('which php 2>/dev/null'));
echo "which php: " . ($whichPhp ?: '(vide)') . "\n";

echo "\n=== php setup/gpt.php (via find_php_cli) ===\n";
$output = [];
$returnCode = 0;
exec($foundCli . ' ' . escapeshellarg($script) . ' 2>&1', $output, $returnCode);
echo implode("\n", $output);
echo "\nCode retour : $returnCode\n";

echo "\n=== setup/gpt.php (exécution directe via shebang) ===\n";
$output2 = [];
$returnCode2 = 0;
exec(escapeshellarg($script) . ' 2>&1', $output2, $returnCode2);
echo implode("\n", $output2);
echo "\nCode retour : $returnCode2\n";

echo "</pre>";

function find_php_cli()
{
    $binary = PHP_BINARY;

    if (stripos($binary, 'php-fpm') !== false || stripos($binary, 'fpm') !== false) {
        $cli = preg_replace('#/sbin/php-fpm[^/]*$#', '/bin/php', $binary);
        if ($cli !== $binary && is_executable($cli)) {
            return escapeshellcmd($cli);
        }
        $cli = preg_replace('#/php-fpm[^/]*$#', '/php', $binary);
        if ($cli !== $binary && is_executable($cli)) {
            return escapeshellcmd($cli);
        }
    }

    if (is_executable($binary) && stripos($binary, 'fpm') === false) {
        return escapeshellcmd($binary);
    }

    $candidates = [
        '/images/legacy/usr/local/php5.6/bin/php',
        '/images/legacy/usr/local/php7.4/bin/php',
        '/images/legacy/usr/local/php8.0/bin/php',
        '/images/legacy/usr/local/php8.1/bin/php',
        '/images/legacy/usr/local/php8.2/bin/php',
        '/usr/local/php8.2/bin/php', '/usr/local/php8.1/bin/php',
        '/usr/local/php8.0/bin/php', '/usr/local/php80/bin/php',
        '/usr/local/php7.4/bin/php', '/usr/local/php74/bin/php',
        '/usr/local/php7.3/bin/php', '/usr/local/php73/bin/php',
        '/usr/local/php5.6/bin/php', '/usr/local/php56/bin/php',
        '/usr/local/bin/php', '/usr/bin/php',
        '/usr/bin/php8', '/usr/bin/php7', '/usr/bin/php56',
        '/opt/php8.2/bin/php', '/opt/php8.1/bin/php',
        '/opt/php8.0/bin/php', '/opt/php7.4/bin/php',
        '/opt/php5.6/bin/php', '/opt/php56/bin/php',
    ];
    foreach ($candidates as $candidate) {
        if (is_executable($candidate)) {
            return escapeshellcmd($candidate);
        }
    }

    return 'php';
}
