<?php

$isCli = (PHP_SAPI === 'cli');

if (!$isCli && !headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$defaultRoot = realpath(__DIR__ . '/..');
$projectRoot = $defaultRoot;
$dryRun = false;
$materializeCarouselLinks = true;

// Security for HTTP mode: set this to a strong random value.
$requiredKey = getenv('FIX_BROKEN_IMAGE_KEY');
if ($requiredKey === false || $requiredKey === '') {
    $requiredKey = 'MBF_FIX_2026_03_08_kN9u2JmQp7R4tX6v';
}

if ($isCli) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--root=') === 0) {
            $candidate = substr($arg, 7);
            if ($candidate !== '') {
                $projectRoot = $candidate;
            }
        } elseif ($arg === '--dry-run') {
            $dryRun = true;
        } elseif ($arg === '--no-materialize') {
            $materializeCarouselLinks = false;
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "Usage:\n";
            echo "  php web/fix_broken_image_links.php [--root=/path/to/project] [--dry-run] [--no-materialize]\n";
            echo "\n";
            echo "HTTP usage:\n";
            echo "  /fix_broken_image_links.php?key=YOUR_KEY&dry_run=1&materialize=1\n";
            exit(0);
        }
    }
} else {
    $providedKey = isset($_GET['key']) ? (string) $_GET['key'] : '';
    if ($providedKey === '' || !hash_equals($requiredKey, $providedKey)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit(1);
    }

    $dryRun = isset($_GET['dry_run']) && (string) $_GET['dry_run'] === '1';
    if (isset($_GET['materialize']) && (string) $_GET['materialize'] === '0') {
        $materializeCarouselLinks = false;
    }
    if (isset($_GET['root']) && $_GET['root'] !== '') {
        $projectRoot = (string) $_GET['root'];
    }
}

$projectRoot = rtrim((string) $projectRoot, "/\\");
if (!is_dir($projectRoot)) {
    out("Project root does not exist: {$projectRoot}");
    exit(1);
}

$brokenName = 'appareil-3en1-panini-et-zouza-florence-hk465-1-4.jpg';
$cacheCarouselDir = $projectRoot . '/web/cache/images/carousel';
$localCarouselDir = $projectRoot . '/local/media/images/carousel';
$localTarget = $localCarouselDir . '/' . $brokenName;
$cacheHashedExact = $cacheCarouselDir . '/dc1186bf7e4430af0c0fe8b9aeba94d2-' . $brokenName;

$stats = array(
    'restored_source' => false,
    'removed_broken_symlinks' => 0,
    'materialized_symlinks' => 0,
    'errors' => array()
);

out('Project root: ' . $projectRoot);
out('Dry run: ' . ($dryRun ? 'yes' : 'no'));
out('Mode: ' . ($isCli ? 'cli' : 'http'));
out('Materialize carousel symlinks: ' . ($materializeCarouselLinks ? 'yes' : 'no'));
out('');

// 0) Ensure Thelia is configured to use 'copy' instead of 'symlink' for images.
try {
    require_once __DIR__ . '/../core/vendor/autoload.php';
    $thelia = new \Thelia\Core\Thelia('prod', false);
    $thelia->boot();
    
    $mode = \Thelia\Model\ConfigQuery::read('original_image_delivery_mode', 'symlink');
    if ($mode !== 'copy') {
        out("[INFO] Changing original_image_delivery_mode from '$mode' to 'copy'...");
        if (!$dryRun) {
            $config = \Thelia\Model\ConfigQuery::create()->filterByName('original_image_delivery_mode')->findOne();
            if (!$config) {
                $config = new \Thelia\Model\Config();
                $config->setName('original_image_delivery_mode');
            }
            $config->setValue('copy');
            $config->save();
            out("[OK] Configuration updated to 'copy'.");
        }
    } else {
        out("[OK] Thelia is already configured to 'copy' images.");
    }
} catch (\Exception $e) {
    out("[WARNING] Could not update Thelia config: " . $e->getMessage());
}

out('');

// 1) Restore missing source file for known broken carousel image.
$needRestore = !is_file($localTarget);

if ($needRestore) {
    $sourceCandidate = null;

    if (is_file($cacheHashedExact)) {
        $sourceCandidate = $cacheHashedExact;
    } else {
        $matches = glob($cacheCarouselDir . '/*-' . $brokenName);
        if (is_array($matches) && !empty($matches)) {
            foreach ($matches as $m) {
                if (is_file($m)) {
                    $sourceCandidate = $m;
                    break;
                }
            }
        }
    }

    if ($sourceCandidate !== null) {
        out('[INFO] Source image missing, restore from: ' . $sourceCandidate);
        if (!$dryRun) {
            if (!is_dir($localCarouselDir)) {
                if (!@mkdir($localCarouselDir, 0777, true) && !is_dir($localCarouselDir)) {
                    $stats['errors'][] = 'Cannot create directory: ' . $localCarouselDir;
                }
            }
            if (is_dir($localCarouselDir)) {
                if (@copy($sourceCandidate, $localTarget)) {
                    $stats['restored_source'] = true;
                    out('[OK] Restored: ' . $localTarget);
                } else {
                    $stats['errors'][] = 'Failed copy: ' . $sourceCandidate . ' -> ' . $localTarget;
                }
            }
        }
    } else {
        $stats['errors'][] = 'No candidate found to restore: ' . $localTarget;
    }
} else {
    out('[OK] Source file already exists: ' . $localTarget);
}

out('');

// 2) Remove broken symlinks in web/cache/images.
$cacheImagesRoot = $projectRoot . '/web/cache/images';
if (!is_dir($cacheImagesRoot)) {
    $stats['errors'][] = 'Directory not found: ' . $cacheImagesRoot;
} else {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheImagesRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($rii as $item) {
        $path = $item->getPathname();
        if (!is_link($path)) {
            continue;
        }

        $linkTarget = @readlink($path);
        $absTarget = null;

        if ($linkTarget !== false) {
            if (strlen($linkTarget) > 0 && $linkTarget[0] === '/') {
                $absTarget = $linkTarget;
            } else {
                $absTarget = dirname($path) . '/' . $linkTarget;
            }
        }

        $isBroken = ($linkTarget === false) || !file_exists($absTarget);
        if ($isBroken) {
            out('[BROKEN] ' . $path . ($linkTarget !== false ? (' -> ' . $linkTarget) : ''));
            if (!$dryRun) {
                if (@unlink($path)) {
                    $stats['removed_broken_symlinks']++;
                } else {
                    $stats['errors'][] = 'Cannot remove broken symlink: ' . $path;
                }
            }
            continue;
        }

        if ($materializeCarouselLinks && starts_with_path($path, $cacheCarouselDir . '/')) {
            out('[SYMLINK] ' . $path . ' -> ' . $absTarget);

            if (!$dryRun) {
                $tmpPath = $path . '.tmpcopy.' . uniqid('', true);
                if (!@copy($absTarget, $tmpPath)) {
                    $stats['errors'][] = 'Cannot copy symlink target to temp file: ' . $absTarget;
                    continue;
                }

                if (!@unlink($path)) {
                    @unlink($tmpPath);
                    $stats['errors'][] = 'Cannot remove symlink before materializing: ' . $path;
                    continue;
                }

                if (!@rename($tmpPath, $path)) {
                    @unlink($tmpPath);
                    $stats['errors'][] = 'Cannot rename temp file to final path: ' . $path;
                    continue;
                }

                $stats['materialized_symlinks']++;
            }
        }
    }
}

out('');
out('=== Summary ===');
out('Restored source file: ' . ($stats['restored_source'] ? 'yes' : 'no'));
out('Broken symlinks removed: ' . (int) $stats['removed_broken_symlinks']);
out('Carousel symlinks materialized: ' . (int) $stats['materialized_symlinks']);

if (!empty($stats['errors'])) {
    out('Errors:');
    foreach ($stats['errors'] as $err) {
        out('- ' . $err);
    }
    exit(2);
}

out('Done.');
exit(0);

function out($line)
{
    echo $line . "\n";
}

function starts_with_path($path, $prefix)
{
    $p = str_replace('\\', '/', (string) $path);
    $x = str_replace('\\', '/', (string) $prefix);
    return strpos($p, $x) === 0;
}
