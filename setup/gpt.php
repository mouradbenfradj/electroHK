#!/images/legacy/usr/local/php5.6/bin/php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$setup = __DIR__;
$schemaFile = $setup . '/thelia.sql';
$csvDir = $setup . '/gpt/csv';
$reportFile = $setup . '/gpt/category_product_issues.txt';

if (is_file($csvDir . '/categories.csv') && is_file($csvDir . '/products.csv')) {
    legacy_import_like_setup_import($setup, $csvDir, $reportFile);
    exit(0);
}

list($dsn, $dbUser, $dbPass) = resolve_db($setup);
$pdo = new PDO($dsn, $dbUser, $dbPass, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));

$schema = parse_schema($schemaFile);
$tables = array_keys($schema);
if (!$tables) {
    throw new RuntimeException("Cannot parse schema: {$schemaFile}");
}

$activeTables = array();
foreach ($tables as $table) {
    $file = $csvDir . '/' . $table . '.csv';
    if (is_file($file)) {
        $activeTables[$table] = $file;
    }
}

if (!$activeTables) {
    echo "No CSV files found in {$csvDir}\n";
    exit(0);
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$reversed = array_reverse(array_keys($activeTables));
foreach ($reversed as $t) {
    $pdo->exec("TRUNCATE TABLE `{$t}`");
}
$pdo->beginTransaction();
try {
    foreach ($activeTables as $table => $file) {
        import_table($pdo, $table, $file, isset($schema[$table]) ? $schema[$table] : array());
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    throw $e;
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "Import finished from {$csvDir}\n";

function legacy_import_like_setup_import($setup, $csvDir, $reportFile)
{
    bootstrap_thelia_for_gpt_import($setup);

    list($categoryHeader, $categoryRows) = load_csv_assoc($csvDir . '/categories.csv');
    list($productHeader, $productRows) = load_csv_assoc($csvDir . '/products.csv');
    list($brandHeader, $brandRows) = load_csv_assoc($csvDir . '/brand.csv');

    if (!$categoryHeader || !$productHeader) {
        throw new RuntimeException("Missing required CSV headers in {$csvDir}");
    }

    $con = \Propel\Runtime\Propel::getConnection(
        \Thelia\Model\Map\ProductTableMap::DATABASE_NAME
    );
    $con->beginTransaction();

    try {
        exec_sql($con, "SET foreign_key_checks = 0");
        clear_gpt_import_tables($con);
        exec_sql($con, "SET foreign_key_checks = 1");

        $brandMap = import_brands_for_gpt($brandHeader, $brandRows, $productRows, $con);

        list($categoryMap, $missingParentRows) = import_category_tree_for_gpt($categoryRows, $con);

        $productReport = import_products_for_gpt($productRows, $categoryMap, $brandMap, $setup, $con);
        $productsWithoutCategory = isset($productReport['without_category']) ? $productReport['without_category'] : array();
        $productsWithUnknownCategories = isset($productReport['unknown_categories']) ? $productReport['unknown_categories'] : array();
        $importedProducts = isset($productReport['imported']) ? (int)$productReport['imported'] : 0;

        write_category_product_report(
            $reportFile,
            $missingParentRows,
            $productsWithoutCategory,
            $productsWithUnknownCategories
        );

        $con->commit();

        echo "Legacy style import finished from {$csvDir}\n";
        echo "categories: " . count($categoryMap) . "\n";
        echo "products: {$importedProducts}\n";
        echo "report: {$reportFile}\n";
    } catch (Exception $e) {
        try {
            exec_sql($con, "SET foreign_key_checks = 1");
        } catch (Exception $ignored) {
        }
        $con->rollBack();
        throw $e;
    }
}

function import_table($pdo, $table, $file, $tableSchema)
{
    $fh = fopen($file, 'rb');
    if ($fh === false) return;
    $headers = fgetcsv($fh, 0, ';');
    if ($headers === false || count($headers) === 0) {
        fclose($fh);
        return;
    }
    $headers = array_map('trim_bom', $headers);
    $colsSql = '`' . implode('`,`', $headers) . '`';
    $marks = implode(',', array_fill(0, count($headers), '?'));
    $sql = "INSERT INTO `{$table}` ({$colsSql}) VALUES ({$marks})";
    $stmt = $pdo->prepare($sql);
    $count = 0;

    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
        if (count($row) > count($headers)) $row = array_slice($row, 0, count($headers));
        $blank = true;
        foreach ($row as $v) {
            if (trim((string)$v) !== '') { $blank = false; break; }
        }
        if ($blank) continue;
        $bind = array();
        foreach ($row as $idx => $v) {
            $col = isset($headers[$idx]) ? $headers[$idx] : null;
            $isNotNull = false;
            if ($col !== null && isset($tableSchema[$col])) {
                $isNotNull = (bool)$tableSchema[$col];
            }
            if (trim((string)$v) === '' && !$isNotNull) {
                $bind[] = null;
            } else {
                if (is_string($v)) {
                    $v = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $v);
                }
                $bind[] = $v;
            }
        }
        $stmt->execute($bind);
        $count++;
    }
    fclose($fh);
    echo $table . ': ' . $count . "\n";
}

function parse_schema($file)
{
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return array();
    $out = array();
    $table = null;
    foreach ($lines as $line) {
        if (preg_match('/^CREATE TABLE `([^`]+)`\s*$/', trim($line), $m)) {
            $table = $m[1];
            $out[$table] = array();
            continue;
        }
        if ($table !== null) {
            if (preg_match('/^\s*`([^`]+)`\s+(.+)$/', $line, $m)) {
                $col = $m[1];
                $def = strtoupper($m[2]);
                $out[$table][$col] = (strpos($def, 'NOT NULL') !== false);
                continue;
            }
            if (preg_match('/^\) ENGINE=/', trim($line))) {
                $table = null;
            }
        }
    }
    return $out;
}

function bootstrap_thelia_for_gpt_import($setup)
{
    $loaded = false;

    if (is_file($file = $setup . '/../core/vendor/autoload.php')) {
        require_once $file;
        $loaded = true;
    } elseif (is_file($file = $setup . '/../../bootstrap.php')) {
        require_once $file;
        $loaded = true;
    }

    if (!$loaded) {
        throw new RuntimeException("No autoload file found for Thelia bootstrap");
    }

    $thelia = new \Thelia\Core\Thelia("dev", true);
    $thelia->boot();

    $container = $thelia->getContainer();
    if ($container && method_exists($container, 'has') && $container->has("thelia.translator")) {
        $container->get("thelia.translator");
    }

    // Required by rewritten URL generation in i18n model hooks.
    new \Thelia\Tools\URL();
}

function clear_gpt_import_tables($con)
{
    $tables = array(
        'accessory',
        'feature_product',
        'sale_product',
        'cart_item',
        'product_associated_content',
        'product_category',
        'product_sale_elements_product_document',
        'product_sale_elements_product_image',
        'product_document_i18n',
        'product_document',
        'product_image_i18n',
        'product_image',
        'product_price',
        'product_sale_elements',
        'product_i18n',
        'product_version',
        'product',
        'category_associated_content',
        'category_document_i18n',
        'category_document',
        'category_image_i18n',
        'category_image',
        'category_i18n',
        'category_version',
        'category',
        'brand_document_i18n',
        'brand_document',
        'brand_image_i18n',
        'brand_image',
        'brand_i18n',
        'brand'
    );

    $existing = existing_tables($con);
    foreach ($tables as $table) {
        if (!isset($existing[$table])) {
            continue;
        }
        exec_sql($con, "DELETE FROM `{$table}`");
    }

    if (isset($existing['rewriting_url'])) {
        exec_sql($con, "DELETE FROM `rewriting_url` WHERE `view` IN ('product','category','brand')");
    }
}

function existing_tables($con)
{
    $set = array();
    $stmt = $con->prepare("SHOW TABLES");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if (isset($row[0]) && $row[0] !== '') {
            $set[$row[0]] = true;
        }
    }
    $stmt->closeCursor();
    return $set;
}

function import_brands_for_gpt($brandHeader, $brandRows, $productRows, $con)
{
    $brandNames = array();

    $hasNameHeader = in_array('NAME', $brandHeader, true);
    if ($hasNameHeader) {
        foreach ($brandRows as $row) {
            $name = trim((string)getv($row, 'NAME', ''));
            if ($name !== '') {
                $brandNames[$name] = true;
            }
        }
    }

    foreach ($productRows as $row) {
        $name = trim((string)getv($row, 'BRAND', ''));
        if ($name !== '') {
            $brandNames[$name] = true;
        }
    }

    $names = array_keys($brandNames);
    natcasesort($names);

    $out = array();
    $position = 0;

    foreach ($names as $name) {
        $brand = new \Thelia\Model\Brand();
        $brand
            ->setVisible(1)
            ->setPosition(++$position)
            ->setLocale('fr_FR')
                ->setTitle($name)
                ->setChapo('')
                ->setDescription('')
            ->setLocale('en_US')
                ->setTitle($name)
                ->setChapo('')
                ->setDescription('')
            ->save($con);

        $out[normalize_name_key($name)] = $brand;
    }

    return $out;
}

function import_category_tree_for_gpt($categoryRows, $con)
{
    $leafRows = array();
    $explicitPaths = array();

    foreach ($categoryRows as $row) {
        $pathFr = normalize_path(getv($row, 'CATEGORIES FR', ''));
        if ($pathFr === '') {
            continue;
        }
        $pathEn = normalize_path(getv($row, 'CATEGORIES UK', ''));
        if ($pathEn === '') {
            $pathEn = $pathFr;
        }

        $leafRows[] = array(
            'path_fr' => $pathFr,
            'path_en' => $pathEn,
            'chapo_fr' => trim((string)getv($row, 'CHAPO FR', '')),
            'chapo_en' => trim((string)getv($row, 'CHAPO UK', '')),
            'desc_fr' => trim((string)getv($row, 'DESCRIPTIF FR', '')),
            'desc_en' => trim((string)getv($row, 'DESCRIPTIF UK', ''))
        );
        $explicitPaths[$pathFr] = true;
    }

    $missingParentRows = array();
    foreach ($leafRows as $leaf) {
        $path = $leaf['path_fr'];
        $parts = split_path($path);
        if (count($parts) <= 1) {
            continue;
        }
        $parentPath = implode('/', array_slice($parts, 0, count($parts) - 1));
        if ($parentPath !== '' && !isset($explicitPaths[$parentPath])) {
            $missingParentRows[] = array(
                'category' => $path,
                'parent' => $parentPath
            );
        }
    }

    $nodes = array();

    foreach ($leafRows as $leaf) {
        $partsFr = split_path($leaf['path_fr']);
        $partsEn = split_path($leaf['path_en']);
        if (!$partsFr) {
            continue;
        }

        $currentFr = array();
        $currentEn = array();
        $lastIndex = count($partsFr) - 1;

        foreach ($partsFr as $idx => $segFr) {
            $segEn = isset($partsEn[$idx]) && $partsEn[$idx] !== '' ? $partsEn[$idx] : $segFr;
            $currentFr[] = $segFr;
            $currentEn[] = $segEn;
            $fullFr = implode('/', $currentFr);
            $parent = $idx > 0 ? implode('/', array_slice($currentFr, 0, $idx)) : '';

            if (!isset($nodes[$fullFr])) {
                $nodes[$fullFr] = array(
                    'path_fr' => $fullFr,
                    'title_fr' => $segFr,
                    'title_en' => $segEn,
                    'parent' => $parent,
                    'chapo_fr' => '',
                    'chapo_en' => '',
                    'desc_fr' => '',
                    'desc_en' => ''
                );
            }

            if ($idx === $lastIndex) {
                if ($leaf['chapo_fr'] !== '') $nodes[$fullFr]['chapo_fr'] = $leaf['chapo_fr'];
                if ($leaf['chapo_en'] !== '') $nodes[$fullFr]['chapo_en'] = $leaf['chapo_en'];
                if ($leaf['desc_fr'] !== '') $nodes[$fullFr]['desc_fr'] = $leaf['desc_fr'];
                if ($leaf['desc_en'] !== '') $nodes[$fullFr]['desc_en'] = $leaf['desc_en'];
            }
        }
    }

    $keys = array_keys($nodes);
    usort($keys, 'compare_category_paths');

    $map = array();
    $position = 0;

    foreach ($keys as $path) {
        $node = $nodes[$path];
        $parentId = 0;
        if ($node['parent'] !== '' && isset($map[$node['parent']])) {
            $parentId = $map[$node['parent']]->getId();
        }

        $titleFr = $node['title_fr'] !== '' ? $node['title_fr'] : 'Category';
        $titleEn = $node['title_en'] !== '' ? $node['title_en'] : $titleFr;

        $category = new \Thelia\Model\Category();
        $category
            ->setVisible(1)
            ->setPosition(++$position)
            ->setParent($parentId)
            ->setLocale('fr_FR')
                ->setTitle($titleFr)
                ->setChapo($node['chapo_fr'])
                ->setDescription($node['desc_fr'])
            ->setLocale('en_US')
                ->setTitle($titleEn)
                ->setChapo($node['chapo_en'])
                ->setDescription($node['desc_en'])
            ->save($con);

        $map[$path] = $category;
    }

    return array($map, $missingParentRows);
}

function compare_category_paths($a, $b)
{
    $da = substr_count($a, '/');
    $db = substr_count($b, '/');
    if ($da === $db) {
        return strcmp($a, $b);
    }
    return ($da < $db) ? -1 : 1;
}

function import_products_for_gpt($productRows, $categoryMap, $brandMap, $setup, $con)
{
    $withoutCategory = array();
    $unknownCategories = array();
    $usedRefs = array();
    $count = 0;

    foreach ($productRows as $idx => $row) {
        $ref = trim((string)getv($row, 'REF', ''));
        if ($ref === '') {
            $ref = 'gpt-ref-' . ($idx + 1);
        }
        $ref = unique_ref($ref, $usedRefs, $idx + 1);

        $titleSource = trim((string)getv($row, 'TITRE UK', ''));
        if ($titleSource === '') {
            $titleSource = trim((string)getv($row, 'CHAPO FR', ''));
        }
        if ($titleSource === '') {
            $titleSource = $ref;
        }
        $titleEn = product_title_from_ref($ref, $titleSource);
        $titleFr = $titleEn;

        $product = new \Thelia\Model\Product();
        $product
            ->setRef($ref)
            ->setVisible(1)
            ->setTaxRuleId(1);

        $brandKey = normalize_name_key(getv($row, 'BRAND', ''));
        if ($brandKey !== '' && isset($brandMap[$brandKey])) {
            $product->setBrand($brandMap[$brandKey]);
        }

        $categoriesRaw = trim((string)getv($row, 'CATEGORIE', ''));
        $categoryPaths = split_multi_value($categoriesRaw);

        $assignedCount = 0;
        if (!$categoryPaths) {
            $withoutCategory[] = $ref;
        } else {
            foreach ($categoryPaths as $catPath) {
                $key = normalize_path($catPath);
                if ($key === '') {
                    continue;
                }
                if (isset($categoryMap[$key])) {
                    $product->addCategory($categoryMap[$key]);
                    $assignedCount++;
                } else {
                    if (!isset($unknownCategories[$ref])) {
                        $unknownCategories[$ref] = array();
                    }
                    $unknownCategories[$ref][$key] = true;
                }
            }
        }

        if ($assignedCount === 0 && !in_array($ref, $withoutCategory, true)) {
            $withoutCategory[] = $ref;
        }

        $product
            ->setLocale('en_US')
                ->setTitle($titleEn)
                ->setChapo(getv($row, 'CHAPO UK', ''))
                ->setDescription(getv($row, 'DESCRIPTIF UK', ''))
                ->setPostscriptum(getv($row, 'POSTSCRIPTUM UK', ''))
            ->setLocale('fr_FR')
                ->setTitle($titleFr)
                ->setChapo(getv($row, 'CHAPO FR', ''))
                ->setDescription(getv($row, 'DESCRIPTIF FR', ''))
                ->setPostscriptum(getv($row, 'POSTSCRIPTUM FR', ''))
            ->save($con);

        if ($assignedCount > 0) {
            $pcs = $product->getProductCategories();
            if ($pcs && $pcs->count() > 0) {
                $firstPc = $pcs->getFirst();
                if ($firstPc) {
                    $firstPc->setDefaultCategory(true)->save($con);
                }
            }
        }

        $price = to_decimal(getv($row, 'PRIX', '0'));
        $promoPrice = to_decimal(getv($row, 'PRIX2', ''));

        $pse = new \Thelia\Model\ProductSaleElements();
        $pse
            ->setProduct($product)
            ->setRef($ref . '-default')
            ->setQuantity(999)
            ->setPromo($promoPrice > 0 ? 1 : 0)
            ->setNewness(0)
            ->setWeight(0)
            ->save($con);

        $productPrice = new \Thelia\Model\ProductPrice();
        $productPrice
            ->setProductSaleElements($pse)
            ->setCurrencyId(1)
            ->setPrice($price > 0 ? $price : 0)
            ->setPromoPrice($promoPrice > 0 ? $promoPrice : 0);
        $productPrice->save($con);

        $pse->setIsDefault(1)->save($con);

        $imageList = split_multi_value(getv($row, 'PHOTO', ''));
        foreach ($imageList as $image) {
            $image = trim((string)$image);
            if ($image === '') continue;

            $productImage = new \Thelia\Model\ProductImage();
            $productImage
                ->setProduct($product)
                ->setFile($image)
                ->save($con);

            copy_gpt_image_if_exists($setup, $image, 'product');
        }

        $count++;
    }

    $normalizedUnknown = array();
    foreach ($unknownCategories as $ref => $catSet) {
        $cats = array_keys($catSet);
        sort($cats);
        $normalizedUnknown[$ref] = $cats;
    }

    sort($withoutCategory);

    return array(
        'without_category' => $withoutCategory,
        'unknown_categories' => $normalizedUnknown,
        'imported' => $count
    );
}

function copy_gpt_image_if_exists($setup, $image, $type)
{
    $src = $setup . '/gpt/images/' . $image;
    if (!is_file($src)) {
        return;
    }

    if (!defined('THELIA_LOCAL_DIR')) {
        return;
    }

    $destDir = THELIA_LOCAL_DIR . 'media/images/' . $type . '/';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0777, true);
    }
    @copy($src, $destDir . $image);
}

function unique_ref($ref, &$usedRefs, $seed)
{
    $base = $ref;
    if (!isset($usedRefs[$base])) {
        $usedRefs[$base] = true;
        return $base;
    }

    $i = (int)$seed;
    do {
        $candidate = $base . '-' . $i;
        $i++;
    } while (isset($usedRefs[$candidate]));

    $usedRefs[$candidate] = true;
    return $candidate;
}

function write_category_product_report($reportFile, $missingParentRows, $productsWithoutCategory, $productsWithUnknownCategories)
{
    $dir = dirname($reportFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    usort($missingParentRows, 'compare_missing_parent_rows');
    ksort($productsWithUnknownCategories);

    $lines = array();
    $lines[] = "Generated at: " . date('Y-m-d H:i:s');
    $lines[] = "";
    $lines[] = "Categories with non-existing parent in categories.csv: " . count($missingParentRows);
    foreach ($missingParentRows as $row) {
        $lines[] = '- category: ' . $row['category'] . ' | missing parent: ' . $row['parent'];
    }

    $lines[] = "";
    $lines[] = "Products without category: " . count($productsWithoutCategory);
    foreach ($productsWithoutCategory as $ref) {
        $lines[] = '- ' . $ref;
    }

    $lines[] = "";
    $lines[] = "Products referencing unknown categories: " . count($productsWithUnknownCategories);
    foreach ($productsWithUnknownCategories as $ref => $cats) {
        $lines[] = '- ' . $ref . ' -> ' . implode(' ; ', $cats);
    }

    file_put_contents($reportFile, implode(PHP_EOL, $lines) . PHP_EOL);
}

function compare_missing_parent_rows($a, $b)
{
    $ac = isset($a['category']) ? $a['category'] : '';
    $bc = isset($b['category']) ? $b['category'] : '';
    return strcmp($ac, $bc);
}

function normalize_path($value)
{
    $v = trim((string)$value);
    if ($v === '') {
        return '';
    }
    $v = str_replace("\\", "/", $v);
    $parts = split_path($v);
    return implode('/', $parts);
}

function split_path($path)
{
    $parts = explode('/', (string)$path);
    $out = array();
    foreach ($parts as $part) {
        $p = trim((string)$part);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function split_multi_value($value)
{
    $v = trim((string)$value);
    if ($v === '') {
        return array();
    }

    // Protect HTML entities like &amp; from being split on their trailing semicolon.
    $protected = preg_replace('/&([a-zA-Z0-9#]+);/', '&$1__SEMI__', $v);
    $parts = explode(';', $protected);
    $out = array();
    $seen = array();

    foreach ($parts as $part) {
        $p = str_replace('__SEMI__', ';', $part);
        $p = trim((string)$p);
        if ($p === '') continue;
        if (isset($seen[$p])) continue;
        $seen[$p] = true;
        $out[] = $p;
    }

    return $out;
}

function normalize_name_key($value)
{
    $v = trim((string)$value);
    if ($v === '') return '';
    return strtolower($v);
}

function to_decimal($value)
{
    $v = trim((string)$value);
    if ($v === '') {
        return 0.0;
    }
    $v = str_replace(' ', '', $v);
    $v = str_replace(',', '.', $v);
    $v = preg_replace('/[^0-9.\-]/', '', $v);
    if ($v === '' || $v === '-' || $v === '.') {
        return 0.0;
    }
    return (float)$v;
}

function product_title_from_ref($ref, $titleSource)
{
    $ref = trim((string)$ref);
    $titleSource = trim((string)$titleSource);

    if ($ref === '') {
        $ref = 'product';
    }

    if ($titleSource === '' || $titleSource === $ref) {
        return $ref;
    }

    $title = $ref . ' - ' . $titleSource;
    if (strlen($title) > 250) {
        $title = substr($title, 0, 250);
    }

    return $title;
}

function exec_sql($con, $sql)
{
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $stmt->closeCursor();
}

function load_csv_assoc($file)
{
    if (!is_file($file)) {
        return array(array(), array());
    }
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        return array(array(), array());
    }

    $header = fgetcsv($fh, 0, ';');
    if ($header === false) {
        fclose($fh);
        return array(array(), array());
    }
    $header = array_map('trim_bom', $header);

    $rows = array();
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');
        }
        if (count($row) > count($header)) {
            $row = array_slice($row, 0, count($header));
        }
        $assoc = array_combine($header, $row);
        if ($assoc !== false) {
            $rows[] = $assoc;
        }
    }
    fclose($fh);

    return array($header, $rows);
}

function resolve_db($setup)
{
    $host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'mariadb';
    $port = getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'thelia';
    $user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'thelia';
    $pass = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: 'thelia';

    $cfg = dirname($setup) . '/local/config/database.yml';
    if (is_file($cfg)) {
        $raw = file_get_contents($cfg);
        $dsnYaml = match_cfg($raw, '/^[ \t]*dsn:[ \t]*([^\r\n]+)[ \t]*$/m');
        if ($dsnYaml !== null && strpos($dsnYaml, 'mysql:') === 0) {
            $tmp = substr($dsnYaml, strlen('mysql:'));
            $parts = explode(';', $tmp);
            foreach ($parts as $part) {
                $kv = explode('=', trim($part), 2);
                if (count($kv) !== 2) continue;
                $k = trim($kv[0]);
                $v = trim($kv[1]);
                if ($k === 'host' && $v !== '') $host = $v;
                if ($k === 'port' && $v !== '') $port = $v;
                if ($k === 'dbname' && $v !== '') $name = $v;
            }
        }
        $v = match_cfg($raw, '/^[ \t]*hostname:[ \t]*([^\r\n]+)[ \t]*$/m');
        if ($v !== null) $host = $v;
        $v = match_cfg($raw, '/^[ \t]*database:[ \t]*([^\r\n]+)[ \t]*$/m');
        if ($v !== null) $name = $v;
        $v = match_cfg($raw, '/^[ \t]*username:[ \t]*([^\r\n]+)[ \t]*$/m');
        if ($v !== null) $user = $v;
        $v = match_cfg($raw, '/^[ \t]*user:[ \t]*([^\r\n]+)[ \t]*$/m');
        if ($v !== null) $user = $v;
        $v = match_cfg($raw, '/^[ \t]*password:[ \t]*([^\r\n]*)[ \t]*$/m');
        if ($v !== null) $pass = $v;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8";
    return array($dsn, $user, $pass);
}

function match_cfg($raw, $pattern)
{
    if (preg_match($pattern, (string)$raw, $m)) {
        return trim(trim($m[1]), "\"'");
    }
    return null;
}

function trim_bom($v)
{
    return trim((string)$v, "\xEF\xBB\xBF \t\n\r\0\x0B");
}

function getv($arr, $key, $default)
{
    if (is_array($arr) && array_key_exists($key, $arr)) {
        return $arr[$key];
    }
    return $default;
}
