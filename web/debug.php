<?php

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$defaultTarget = '/cache/images/carousel/';
$target = isset($_GET['target']) ? trim((string) $_GET['target']) : $defaultTarget;
$limit = isset($_GET['limit']) ? max(500, (int) $_GET['limit']) : 20000;
$writeTest = isset($_GET['write_test']) ? ($_GET['write_test'] === '1') : true;

if ($target === '' || $target[0] !== '/') {
    $target = '/' . ltrim($target, '/');
}

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\") : '';
$absolutePath = $docRoot . str_replace('/', DIRECTORY_SEPARATOR, $target);
$scriptDir = __DIR__;
$projectRoot = (basename(str_replace('\\', '/', $scriptDir)) === 'web') ? dirname($scriptDir) : $scriptDir;
$targetBaseName = basename(rtrim($target, '/'));
$targetBaseName = ($targetBaseName === '' || $targetBaseName === '.') ? 'unknown' : $targetBaseName;
$imageSubDirs = array('carousel', 'product', 'category', 'brand', 'folder', 'content', 'store');

print_line('=== MBF Debug Report ===');
print_line('Generated at', date('Y-m-d H:i:s'));
print_line('Script', isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : __FILE__);
print_line('Request URI', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
print_line('Target URL path', $target);
print_line('Scan limit', (string) $limit);
print_line('Write test enabled', bool_text($writeTest));
print_line('Document root', $docRoot);
print_line('Resolved absolute path', $absolutePath);
print_line();

print_line('=== Runtime ===');
print_line('PHP version', PHP_VERSION);
print_line('SAPI', PHP_SAPI);
print_line('Server software', isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '');
print_line('open_basedir', ini_get('open_basedir'));
print_line('disable_functions', ini_get('disable_functions'));
print_line('Current user (get_current_user)', @get_current_user());
if (function_exists('posix_geteuid')) {
    $uid = @posix_geteuid();
    $gid = @posix_getegid();
    $uName = '';
    $gName = '';
    if (function_exists('posix_getpwuid')) {
        $uInfo = @posix_getpwuid($uid);
        if (is_array($uInfo) && isset($uInfo['name'])) {
            $uName = $uInfo['name'];
        }
    }
    if (function_exists('posix_getgrgid')) {
        $gInfo = @posix_getgrgid($gid);
        if (is_array($gInfo) && isset($gInfo['name'])) {
            $gName = $gInfo['name'];
        }
    }
    print_line('Effective UID/GID', $uid . ':' . $gid . ' (' . $uName . ':' . $gName . ')');
}
print_line();

print_line('=== Candidate Target Locations ===');
$targetCandidates = build_target_candidates($target, $targetBaseName, $docRoot, $scriptDir, $projectRoot);
foreach ($targetCandidates as $candidate) {
    print_stat_for_path($candidate, true);
}
print_line();

print_line('=== Image Roots (source and cache) ===');
$imageRoots = build_image_roots($docRoot, $scriptDir, $projectRoot, $imageSubDirs);
foreach ($imageRoots as $path) {
    print_stat_for_path($path, false);
}
print_line();

print_line('=== Write Access Test (non destructive) ===');
if (!$writeTest) {
    print_line('Skipped', 'Use write_test=1 to enable');
} else {
    foreach ($imageRoots as $path) {
        if (!is_dir($path)) {
            continue;
        }
        $result = write_test_directory($path);
        print_line('-- Directory', $path);
        print_line('   create', bool_text($result['create_ok']));
        if ($result['create_error'] !== '') {
            print_line('   create_error', $result['create_error']);
        }
        print_line('   rename', bool_text($result['rename_ok']));
        if ($result['rename_error'] !== '') {
            print_line('   rename_error', $result['rename_error']);
        }
        print_line('   delete', bool_text($result['delete_ok']));
        if ($result['delete_error'] !== '') {
            print_line('   delete_error', $result['delete_error']);
        }
        print_line('   temp_file', $result['temp_path']);
    }
}
print_line();

print_line('=== Tree Audit (all image files) ===');
$scanRootCandidates = array();
foreach ($imageRoots as $r) {
    if (is_dir($r)) {
        $scanRootCandidates[] = $r;
    }
}
$scanRootCandidates = array_values(array_unique($scanRootCandidates));

if (!$scanRootCandidates) {
    print_line('No existing image roots found');
} else {
    foreach ($scanRootCandidates as $root) {
        print_line('-- Audit root', $root);
        $audit = audit_image_tree($root, $limit);
        print_line('   scanned_entries', (string) $audit['scanned']);
        print_line('   file_count', (string) $audit['files']);
        print_line('   dir_count', (string) $audit['dirs']);
        print_line('   symlink_count', (string) $audit['symlinks']);
        print_line('   image_file_count', (string) $audit['image_files']);
        print_line('   stopped_by_limit', bool_text($audit['limit_reached']));
        print_line('   unreadable_dirs', (string) count($audit['unreadable_dirs']));
        print_line('   unwritable_dirs', (string) count($audit['unwritable_dirs']));
        print_line('   unreadable_files', (string) count($audit['unreadable_files']));
        print_line('   unwritable_files', (string) count($audit['unwritable_files']));
        print_line('   dirs_named_like_image', (string) count($audit['dirs_named_like_image']));
        print_line('   broken_symlinks', (string) count($audit['broken_symlinks']));
        print_limited_list('   sample_unreadable_dirs', $audit['unreadable_dirs'], 20);
        print_limited_list('   sample_unwritable_dirs', $audit['unwritable_dirs'], 20);
        print_limited_list('   sample_unreadable_files', $audit['unreadable_files'], 20);
        print_limited_list('   sample_unwritable_files', $audit['unwritable_files'], 20);
        print_limited_list('   sample_dirs_named_like_image', $audit['dirs_named_like_image'], 20);
        print_limited_list('   sample_broken_symlinks', $audit['broken_symlinks'], 20);
        print_limited_list('   sample_image_files', $audit['sample_image_files'], 10);
    }
}
print_line();

print_line('=== Parent Path Chain (target) ===');
$chain = build_path_chain($absolutePath);
foreach ($chain as $p) {
    print_stat_for_path($p, false);
}
print_line();

print_line('=== Relevant .htaccess ===');
$scanDirs = array();
$scanDirs[] = is_dir($absolutePath) ? $absolutePath : dirname($absolutePath);
foreach ($scanRootCandidates as $root) {
    $scanDirs[] = $root;
    $scanDirs[] = dirname($root);
}
$scanDirs = array_values(array_unique(array_filter($scanDirs)));

if (!$scanDirs) {
    print_line('No directories to check');
} else {
    $printedHt = array();
    foreach ($scanDirs as $dir0) {
        $dir = $dir0;
        for ($i = 0; $i < 3; $i++) {
            if ($dir === '' || $dir === '.' || $dir === DIRECTORY_SEPARATOR) {
                break;
            }
            $ht = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . '.htaccess';
            if (is_file($ht) && !isset($printedHt[$ht])) {
                $printedHt[$ht] = true;
                print_line('-- Directory', $dir);
                print_line('   .htaccess', $ht);
                print_line('   perms', perms_text($ht));
                print_line('   owner', owner_group_text($ht));
                $lines = @file($ht);
                if (is_array($lines)) {
                    $max = min(80, count($lines));
                    print_line('   first_lines', $max . ' line(s)');
                    for ($j = 0; $j < $max; $j++) {
                        echo str_pad((string) ($j + 1), 4, ' ', STR_PAD_LEFT) . ': ' . rtrim($lines[$j], "\r\n") . PHP_EOL;
                    }
                } else {
                    print_line('   read', 'FAILED');
                }
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
    }
}
print_line();

if (is_file($absolutePath)) {
    print_line('=== Target File Read Test ===');
    $fh = @fopen($absolutePath, 'rb');
    if ($fh !== false) {
        $sample = @fread($fh, 64);
        @fclose($fh);
        print_line('fopen', 'OK');
        print_line('First bytes (hex)', strtoupper(bin2hex((string) $sample)));
    } else {
        $err = error_get_last();
        print_line('fopen', 'FAILED');
        print_line('error', $err ? $err['message'] : 'unknown');
    }
    print_line();
}

if (is_dir($absolutePath)) {
    print_line('=== Target Directory Listing (first 50) ===');
    $list = @scandir($absolutePath);
    if (is_array($list)) {
        $count = 0;
        foreach ($list as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $count++;
            echo ' - ' . $entry . PHP_EOL;
            if ($count >= 50) {
                break;
            }
        }
        print_line('Displayed', (string) $count);
    } else {
        print_line('scandir', 'FAILED');
    }
    print_line();
}

print_line('=== HTTP HEAD Test To Same Host ===');
$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
$selfTargetUrl = ($host !== '') ? ($scheme . '://' . $host . $target) : '';
print_line('URL', $selfTargetUrl);

if ($selfTargetUrl !== '') {
    $headResult = http_head($selfTargetUrl);
    print_line('Status', isset($headResult['status']) ? $headResult['status'] : 'N/A');
    if (isset($headResult['headers']) && is_array($headResult['headers'])) {
        foreach ($headResult['headers'] as $h) {
            echo ' - ' . $h . PHP_EOL;
        }
    }
    if (isset($headResult['error']) && $headResult['error'] !== '') {
        print_line('HTTP error', $headResult['error']);
    }
} else {
    print_line('Host missing', 'Cannot build full URL');
}
print_line();

print_line('=== Advice ===');
print_line('1', 'Si images visibles mais non modifiables: verifier local/media/images/* (owner/group/perms) et resultat Write Access Test.');
print_line('2', 'Corriger typiquement avec chown www-data:www-data -R local/media/images web/cache/images');
print_line('3', 'Puis chmod 755 dossiers et 644 fichiers.');
print_line('4', 'Si dirs_named_like_image > 0, supprimer ces dossiers et remettre de vrais fichiers image.');
print_line('5', 'Supprimer debug.php apres analyse.');

function build_target_candidates($target, $targetBaseName, $docRoot, $scriptDir, $projectRoot)
{
    $candidates = array();
    $candidates[] = $docRoot . str_replace('/', DIRECTORY_SEPARATOR, $target);
    $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'web' . str_replace('/', DIRECTORY_SEPARATOR, $target);
    $candidates[] = $scriptDir . str_replace('/', DIRECTORY_SEPARATOR, $target);
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'carousel' . DIRECTORY_SEPARATOR . $targetBaseName;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'carousel' . DIRECTORY_SEPARATOR . $targetBaseName;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'carousel' . DIRECTORY_SEPARATOR . $targetBaseName;

    return array_values(array_unique($candidates));
}

function build_image_roots($docRoot, $scriptDir, $projectRoot, $imageSubDirs)
{
    $roots = array();
    $baseRoots = array(
        $projectRoot . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'images',
        $projectRoot . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images',
        $projectRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images',
        $docRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images',
        $docRoot . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images',
        $scriptDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images'
    );

    foreach ($baseRoots as $b) {
        $roots[] = normalize_path($b);
        foreach ($imageSubDirs as $sub) {
            $roots[] = normalize_path($b . DIRECTORY_SEPARATOR . $sub);
        }
    }

    return array_values(array_unique($roots));
}

function normalize_path($path)
{
    $p = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $path);
    return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $p);
}

function write_test_directory($dir)
{
    $result = array(
        'create_ok' => false,
        'create_error' => '',
        'rename_ok' => false,
        'rename_error' => '',
        'delete_ok' => false,
        'delete_error' => '',
        'temp_path' => ''
    );

    $tmp = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . '.mbf-debug-' . date('YmdHis') . '-' . mt_rand(1000, 9999) . '.tmp';
    $tmp2 = $tmp . '.renamed';
    $result['temp_path'] = $tmp;

    $payload = "MBF DEBUG WRITE TEST\n" . date('c') . "\n";

    $createError = '';
    set_error_handler(function ($severity, $message) use (&$createError) {
        $createError = $message;
    });
    $written = @file_put_contents($tmp, $payload);
    restore_error_handler();

    if ($written !== false) {
        $result['create_ok'] = true;
    } else {
        $result['create_error'] = $createError;
    }

    if ($result['create_ok']) {
        $renameError = '';
        set_error_handler(function ($severity, $message) use (&$renameError) {
            $renameError = $message;
        });
        $renamed = @rename($tmp, $tmp2);
        restore_error_handler();

        if ($renamed) {
            $result['rename_ok'] = true;
        } else {
            $result['rename_error'] = $renameError;
        }
    }

    $deleteError = '';
    $deletePath = $result['rename_ok'] ? $tmp2 : $tmp;
    if (file_exists($deletePath)) {
        set_error_handler(function ($severity, $message) use (&$deleteError) {
            $deleteError = $message;
        });
        $deleted = @unlink($deletePath);
        restore_error_handler();
        if ($deleted) {
            $result['delete_ok'] = true;
        } else {
            $result['delete_error'] = $deleteError;
        }
    } else {
        $result['delete_ok'] = true;
    }

    return $result;
}

function audit_image_tree($root, $limit)
{
    $out = array(
        'scanned' => 0,
        'files' => 0,
        'dirs' => 0,
        'symlinks' => 0,
        'image_files' => 0,
        'unreadable_dirs' => array(),
        'unwritable_dirs' => array(),
        'unreadable_files' => array(),
        'unwritable_files' => array(),
        'dirs_named_like_image' => array(),
        'broken_symlinks' => array(),
        'sample_image_files' => array(),
        'limit_reached' => false
    );

    if (!is_dir($root)) {
        return $out;
    }

    $stack = array($root);
    $visited = array();

    while (!empty($stack)) {
        $dir = array_pop($stack);
        if (isset($visited[$dir])) {
            continue;
        }
        $visited[$dir] = true;

        $out['scanned']++;
        $out['dirs']++;

        if (!is_readable($dir) || !is_executable($dir)) {
            $out['unreadable_dirs'][] = $dir;
            continue;
        }
        if (!is_writable($dir)) {
            $out['unwritable_dirs'][] = $dir;
        }
        if (looks_like_image_name(basename($dir))) {
            $out['dirs_named_like_image'][] = $dir;
        }

        $entries = @scandir($dir);
        if (!is_array($entries)) {
            $out['unreadable_dirs'][] = $dir;
            continue;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            $out['scanned']++;

            if ($out['scanned'] >= $limit) {
                $out['limit_reached'] = true;
                return $out;
            }

            if (is_link($path)) {
                $out['symlinks']++;
                $linkTarget = @readlink($path);
                if ($linkTarget === false) {
                    $out['broken_symlinks'][] = $path;
                } else {
                    if (substr($linkTarget, 0, 1) === DIRECTORY_SEPARATOR) {
                        $target = $linkTarget;
                    } else {
                        $target = dirname($path) . DIRECTORY_SEPARATOR . $linkTarget;
                    }
                    if (!file_exists($target)) {
                        $out['broken_symlinks'][] = $path . ' -> ' . $linkTarget;
                    }
                }
                continue;
            }

            if (is_dir($path)) {
                $stack[] = $path;
                if (looks_like_image_name($entry)) {
                    $out['dirs_named_like_image'][] = $path;
                }
                continue;
            }

            if (is_file($path)) {
                $out['files']++;
                if (looks_like_image_name($entry)) {
                    $out['image_files']++;
                    if (count($out['sample_image_files']) < 10) {
                        $out['sample_image_files'][] = $path;
                    }
                }
                if (!is_readable($path)) {
                    $out['unreadable_files'][] = $path;
                }
                if (!is_writable($path)) {
                    $out['unwritable_files'][] = $path;
                }
            }
        }
    }

    return $out;
}

function looks_like_image_name($name)
{
    $lower = strtolower((string) $name);
    return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|bmp|svg)$/', $lower);
}

function print_limited_list($label, $items, $limit)
{
    $count = count($items);
    print_line($label, (string) $count . ' item(s)');
    if ($count === 0) {
        return;
    }
    $max = min($limit, $count);
    for ($i = 0; $i < $max; $i++) {
        echo '    - ' . $items[$i] . PHP_EOL;
    }
    if ($count > $max) {
        echo '    ... +' . ($count - $max) . ' more' . PHP_EOL;
    }
}

function print_line($label = null, $value = null)
{
    if ($label === null) {
        echo PHP_EOL;
        return;
    }

    if ($value === null) {
        echo $label . PHP_EOL;
        return;
    }

    echo $label . ': ' . $value . PHP_EOL;
}

function bool_text($bool)
{
    return $bool ? 'yes' : 'no';
}

function file_type_text($path)
{
    if (!file_exists($path) && !is_link($path)) {
        return 'missing';
    }
    if (is_link($path)) {
        return 'symlink';
    }
    if (is_dir($path)) {
        return 'dir';
    }
    if (is_file($path)) {
        return 'file';
    }
    return 'other';
}

function perms_text($path)
{
    $perms = @fileperms($path);
    if ($perms === false) {
        return 'N/A';
    }
    return substr(sprintf('%o', $perms), -4);
}

function owner_group_text($path)
{
    $uid = @fileowner($path);
    $gid = @filegroup($path);

    $owner = ($uid === false) ? 'N/A' : (string) $uid;
    $group = ($gid === false) ? 'N/A' : (string) $gid;

    if ($uid !== false && function_exists('posix_getpwuid')) {
        $uInfo = @posix_getpwuid($uid);
        if (is_array($uInfo) && isset($uInfo['name'])) {
            $owner .= ' (' . $uInfo['name'] . ')';
        }
    }

    if ($gid !== false && function_exists('posix_getgrgid')) {
        $gInfo = @posix_getgrgid($gid);
        if (is_array($gInfo) && isset($gInfo['name'])) {
            $group .= ' (' . $gInfo['name'] . ')';
        }
    }

    return $owner . ':' . $group;
}

function print_stat_for_path($path, $verbose)
{
    print_line('-- Path', $path);
    print_line('   exists', bool_text(file_exists($path) || is_link($path)));
    print_line('   type', file_type_text($path));
    print_line('   perms', (file_exists($path) || is_link($path)) ? perms_text($path) : 'N/A');
    print_line('   owner:group', (file_exists($path) || is_link($path)) ? owner_group_text($path) : 'N/A');
    print_line('   is_readable', bool_text(@is_readable($path)));
    print_line('   is_writable', bool_text(@is_writable($path)));
    print_line('   is_executable', bool_text(@is_executable($path)));

    if (is_link($path)) {
        $link = @readlink($path);
        print_line('   symlink_to', $link !== false ? $link : 'N/A');
        if ($link !== false) {
            print_line('   symlink_target_exists', bool_text(file_exists($link)));
        }
    }

    if ($verbose && is_file($path)) {
        print_line('   size', (string) @filesize($path));
        print_line('   mime', function_exists('mime_content_type') ? @mime_content_type($path) : 'N/A');
        print_line('   realpath', @realpath($path));
    }
}

function build_path_chain($path)
{
    $out = array();
    $seen = array();
    $cur = $path;

    while (true) {
        if ($cur === '' || isset($seen[$cur])) {
            break;
        }
        $out[] = $cur;
        $seen[$cur] = true;
        $parent = dirname($cur);
        if ($parent === $cur) {
            break;
        }
        $cur = $parent;
    }

    return array_reverse($out);
}

function http_head($url)
{
    $result = array(
        'status' => 'N/A',
        'headers' => array(),
        'error' => ''
    );

    if (function_exists('curl_init')) {
        $headers = array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $result['error'] = curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['status'] = (string) $code;
            $headers = preg_split('/\r\n|\r|\n/', trim($raw));
            $result['headers'] = $headers;
        }
        curl_close($ch);
        return $result;
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'HEAD',
            'ignore_errors' => true,
            'timeout' => 15
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false
        )
    ));

    $data = @file_get_contents($url, false, $context);
    $meta = isset($http_response_header) ? $http_response_header : array();
    if (is_array($meta) && isset($meta[0])) {
        $result['status'] = $meta[0];
        $result['headers'] = $meta;
    } else {
        $result['error'] = ($data === false) ? 'request failed' : '';
    }

    return $result;
}
