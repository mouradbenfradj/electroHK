<?php

$root = dirname(__DIR__);

$output = [];
$returnCode = 0;

echo "<pre>";

$php = find_php_cli();

echo "=== php Thelia thelia:dev:reloadDB ===\n";
exec($php . " " . escapeshellarg($root . "/Thelia") . " thelia:dev:reloadDB 2>&1", $output, $returnCode);
echo implode("\n", $output);
echo "\nCode retour : $returnCode\n\n";

$output = [];
$returnCode = 0;

echo "=== php setup/gpt.php ===\n";
exec($php . " " . escapeshellarg($root . "/setup/gpt.php") . " 2>&1", $output, $returnCode);
echo implode("\n", $output);
echo "\nCode retour : $returnCode\n";

echo "</pre>";

function find_php_cli()
{
    $binary = PHP_BINARY;

    if (stripos($binary, 'php-fpm') !== false || stripos($binary, 'fpm') !== false) {
        $cli = preg_replace('#/sbin/php-fpm.*$#', '/bin/php', $binary);
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

    foreach (['/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php8', '/usr/bin/php7'] as $candidate) {
        if (is_executable($candidate)) {
            return escapeshellcmd($candidate);
        }
    }

    return 'php';
}
