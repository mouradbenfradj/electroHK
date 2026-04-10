<?php
if (PHP_SAPI !== 'cli') { exit(1); }

$wp   = dirname(__DIR__) . '/wordpresswoocomerce';
$csv  = __DIR__ . '/csv';
$now  = date('Y-m-d H:i:s');

/* ------------------------------------------------------------------ helpers */

function csv_read($file)
{
    $fh = @fopen($file, 'rb');
    if (!$fh) { echo "Cannot open $file\n"; return array(array(), array()); }
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);
    $headers = fgetcsv($fh, 0, ';');
    if (!$headers) { fclose($fh); return array(array(), array()); }
    $headers = array_map('trim', $headers);
    $rows = array();
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (count($row) < count($headers))
            $row = array_pad($row, count($headers), '');
        $rows[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
    fclose($fh);
    return array($headers, $rows);
}

function csv_append($file, $rows, $headers)
{
    if (!$rows) return 0;
    $fh = fopen($file, 'ab');
    foreach ($rows as $row) {
        $line = array();
        foreach ($headers as $h) {
            $v = isset($row[$h]) ? (string)$row[$h] : '';
            if (strpbrk($v, ";\"\n\r") !== false || (strlen($v) > 0 && $v[0] === ' ')) {
                $v = '"' . str_replace('"', '""', $v) . '"';
            }
            $line[] = $v;
        }
        fwrite($fh, implode(';', $line) . "\n");
    }
    fclose($fh);
    return count($rows);
}

function gv($arr, $key, $default = '')
{
    return isset($arr[$key]) ? $arr[$key] : $default;
}

function build_path($termId, $catTerms, $catParent)
{
    $parts = array();
    $cur = $termId;
    $seen = array();
    while ($cur && isset($catTerms[$cur]) && !isset($seen[$cur])) {
        $seen[$cur] = true;
        $name = trim((string)(isset($catTerms[$cur]['name']) ? $catTerms[$cur]['name'] : 'Cat'.$cur));
        array_unshift($parts, $name);
        $cur = isset($catParent[$cur]) ? (int)$catParent[$cur] : 0;
    }
    return implode('/', $parts);
}

/* ------------------------------------------------------------------ load WP terms */

echo "Loading WooCommerce terms...\n";
$r = csv_read($wp . '/wnew23p_terms.csv');
$termRows = $r[1];
$terms = array();
foreach ($termRows as $tr) {
    $id = (int)gv($tr, 'term_id');
    if ($id) $terms[$id] = $tr;
}

echo "Loading WooCommerce term_taxonomy...\n";
$r2 = csv_read($wp . '/wnew23p_term_taxonomy.csv');
$ttRows = $r2[1];
$tt = array();
$taxonomies = array();
foreach ($ttRows as $tr) {
    $ttId   = (int)gv($tr, 'term_taxonomy_id');
    $termId = (int)gv($tr, 'term_id');
    $tax    = trim(gv($tr, 'taxonomy'));
    if (!$ttId || !$termId) continue;
    $tt[$ttId] = array('term_id' => $termId, 'taxonomy' => $tax, 'parent' => (int)gv($tr, 'parent'));
    $taxonomies[$tax] = true;
}
echo "Taxonomies found: " . implode(', ', array_keys($taxonomies)) . "\n\n";

$brandTax = array('product_brand' => true, 'pa_marque' => true);

/* ============================================================= brand.csv */
echo "=== brand.csv ===\n";

$rb = csv_read($csv . '/brand.csv');
$brandRows = $rb[1];
$existingBrandIds = array();
foreach ($brandRows as $br) {
    $id = (int)gv($br, 'id');
    if ($id) $existingBrandIds[$id] = true;
}
echo "Existing brand ids: " . count($existingBrandIds) . "\n";

$missingBrands = array();
foreach ($tt as $row) {
    if (!isset($brandTax[$row['taxonomy']])) continue;
    $termId = $row['term_id'];
    if (isset($existingBrandIds[$termId])) continue;
    $missingBrands[] = array(
        'id'            => $termId,
        'visible'       => '1',
        'position'      => $termId,
        'logo_image_id' => '',
        'created_at'    => $now,
        'updated_at'    => $now,
    );
    $existingBrandIds[$termId] = true;
}
$added = csv_append($csv . '/brand.csv', $missingBrands,
    array('id','visible','position','logo_image_id','created_at','updated_at'));
echo "Added $added missing brands.\n\n";

/* ============================================================= categories.csv */
echo "=== categories.csv ===\n";

$catHeaders = array('CATEGORIES FR','CATEGORIES UK','CHAPO FR','CHAPO UK','DESCRIPTIF FR','DESCRIPTIF UK','PHOTO');

/* Build flat catTerms and catParent */
$catTerms  = array();
$catParent = array();
foreach ($tt as $row) {
    if ($row['taxonomy'] !== 'product_cat') continue;
    $tid = $row['term_id'];
    $catTerms[$tid]  = isset($terms[$tid]) ? $terms[$tid] : array();
    $parentTtId = $row['parent'];
    if ($parentTtId > 0 && isset($tt[$parentTtId])) {
        $catParent[$tid] = $tt[$parentTtId]['term_id'];
    } else {
        $catParent[$tid] = 0;
    }
}

$rc = csv_read($csv . '/categories.csv');
$existingCatRows = $rc[1];
$existingCatPaths = array();
foreach ($existingCatRows as $cr) {
    $path = trim(gv($cr, 'CATEGORIES FR'));
    if ($path !== '') $existingCatPaths[strtolower($path)] = true;
}
echo "Existing category paths: " . count($existingCatPaths) . "\n";

$missingCats = array();
foreach (array_keys($catTerms) as $tid) {
    $path = build_path($tid, $catTerms, $catParent);
    if ($path === '' || $path === 'Non classé') continue;
    $key = strtolower($path);
    if (isset($existingCatPaths[$key])) continue;
    $missingCats[] = array(
        'CATEGORIES FR' => $path,
        'CATEGORIES UK' => $path,
        'CHAPO FR'      => '',
        'CHAPO UK'      => '',
        'DESCRIPTIF FR' => '',
        'DESCRIPTIF UK' => '',
        'PHOTO'         => '',
    );
    $existingCatPaths[$key] = true;
}
$added = csv_append($csv . '/categories.csv', $missingCats, $catHeaders);
echo "Added $added missing categories.\n\n";

/* ============================================================= colors.csv */
echo "=== colors.csv ===\n";

$colorTax = array('pa_couleur' => true, 'pa_color' => true, 'pa_colors' => true);
$existingColors = array();
$colorFileContent = @file($csv . '/colors.csv', FILE_IGNORE_NEW_LINES);
if ($colorFileContent) {
    foreach ($colorFileContent as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', str_getcsv($line, ';'));
        $name = isset($parts[0]) ? strtolower($parts[0]) : '';
        if ($name !== '') $existingColors[$name] = true;
    }
}
echo "Existing colors: " . count($existingColors) . "\n";

$missingColors = array();
foreach ($tt as $row) {
    if (!isset($colorTax[$row['taxonomy']])) continue;
    $tid = $row['term_id'];
    if (!isset($terms[$tid])) continue;
    $name = trim((string)gv($terms[$tid], 'name'));
    if ($name === '') continue;
    $key = strtolower($name);
    if (isset($existingColors[$key])) continue;
    $missingColors[] = $name;
    $existingColors[$key] = true;
}
$fh = fopen($csv . '/colors.csv', 'ab');
foreach ($missingColors as $name) {
    $v = (strpbrk($name, ";\"\n\r") !== false) ? '"'.str_replace('"','""',$name).'"' : $name;
    fwrite($fh, $v . ';' . $v . "\n");
}
fclose($fh);
echo "Added " . count($missingColors) . " missing colors.\n\n";

/* ============================================================= materials.csv */
echo "=== materials.csv ===\n";

$matTax = array('pa_matiere' => true, 'pa_material' => true, 'pa_mat' => true, 'pa_materiaux' => true);
$existingMats = array();
$matFileContent = @file($csv . '/materials.csv', FILE_IGNORE_NEW_LINES);
if ($matFileContent) {
    foreach ($matFileContent as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', str_getcsv($line, ';'));
        $name = isset($parts[0]) ? strtolower($parts[0]) : '';
        if ($name !== '') $existingMats[$name] = true;
    }
}
echo "Existing materials: " . count($existingMats) . "\n";

$missingMats = array();
foreach ($tt as $row) {
    if (!isset($matTax[$row['taxonomy']])) continue;
    $tid = $row['term_id'];
    if (!isset($terms[$tid])) continue;
    $name = trim((string)gv($terms[$tid], 'name'));
    if ($name === '') continue;
    $key = strtolower($name);
    if (isset($existingMats[$key])) continue;
    $missingMats[] = $name;
    $existingMats[$key] = true;
}
$fh = fopen($csv . '/materials.csv', 'ab');
foreach ($missingMats as $name) {
    $v = (strpbrk($name, ";\"\n\r") !== false) ? '"'.str_replace('"','""',$name).'"' : $name;
    fwrite($fh, $v . ';' . $v . "\n");
}
fclose($fh);
echo "Added " . count($missingMats) . " missing materials.\n\n";

/* ============================================================= products.csv */
echo "=== products.csv ===\n";

$prodHeaders = array('REF','TITRE UK','CHAPO UK','CHAPO FR','DESCRIPTIF UK','DESCRIPTIF FR','POSTSCRIPTUM UK','POSTSCRIPTUM FR','PRIX','PRIX2','PHOTO','BRAND','COULEUR UK','MATERIAL UK','CONTENT UK','CATEGORIE');

$rp = csv_read($csv . '/products.csv');
$prodRows = $rp[1];
$existingRefs = array();
foreach ($prodRows as $pr) {
    $ref = trim(gv($pr, 'REF'));
    if ($ref !== '') $existingRefs[strtolower($ref)] = true;
}
echo "Existing products: " . count($existingRefs) . "\n";

/* Load postmeta */
echo "Loading WooCommerce postmeta (this may take a moment)...\n";
$postMeta = array();
$rPm = csv_read($wp . '/wnew23p_postmeta.csv');
$pmRows = $rPm[1];
unset($rPm);
foreach ($pmRows as $pm) {
    $pid = (int)gv($pm, 'post_id');
    $key = trim(gv($pm, 'meta_key'));
    $val = gv($pm, 'meta_value');
    if (!isset($postMeta[$pid])) $postMeta[$pid] = array();
    if (!isset($postMeta[$pid][$key])) $postMeta[$pid][$key] = $val;
}
unset($pmRows);

/* Build brand name map */
$brandNameMap = array();
foreach ($tt as $row) {
    if (!isset($brandTax[$row['taxonomy']])) continue;
    $tid = $row['term_id'];
    if (isset($terms[$tid])) {
        $brandNameMap[$tid] = trim((string)gv($terms[$tid], 'name'));
    }
}

/* Build product -> brand and product -> categories from relationships */
echo "Loading WooCommerce term_relationships...\n";
$productBrand = array();
$productCats  = array();
$rRel = csv_read($wp . '/wnew23p_term_relationships.csv');
$relRows = $rRel[1];
unset($rRel);
foreach ($relRows as $rel) {
    $objId = (int)gv($rel, 'object_id');
    $ttId  = (int)gv($rel, 'term_taxonomy_id');
    if (!$objId || !$ttId || !isset($tt[$ttId])) continue;
    $row = $tt[$ttId];
    if (isset($brandTax[$row['taxonomy']])) {
        $tid = $row['term_id'];
        if (isset($brandNameMap[$tid])) $productBrand[$objId] = $brandNameMap[$tid];
    }
    if ($row['taxonomy'] === 'product_cat') {
        $tid = $row['term_id'];
        $path = build_path($tid, $catTerms, $catParent);
        if ($path !== '' && $path !== 'Non classé') {
            if (!isset($productCats[$objId])) $productCats[$objId] = array();
            $productCats[$objId][] = $path;
        }
    }
}
unset($relRows);

/* Load products from posts */
echo "Loading WooCommerce products...\n";
$missingProducts = array();
$rPosts = csv_read($wp . '/wnew23p_posts.csv');
$postRows = $rPosts[1];
unset($rPosts);
foreach ($postRows as $p) {
    $type = trim(gv($p, 'post_type'));
    if ($type !== 'product') continue;
    $pid = (int)gv($p, 'ID');
    if (!$pid) continue;
    $pm  = isset($postMeta[$pid]) ? $postMeta[$pid] : array();
    $sku = trim(isset($pm['_sku']) ? $pm['_sku'] : '');
    if ($sku === '') $sku = 'WP-PROD-' . $pid;
    if (isset($existingRefs[strtolower($sku)])) continue;

    $title   = trim(gv($p, 'post_title'));
    $desc    = trim(gv($p, 'post_content'));
    $excerpt = trim(gv($p, 'post_excerpt'));
    $price   = isset($pm['_regular_price']) ? trim($pm['_regular_price']) : '';
    if ($price === '') $price = isset($pm['_price']) ? trim($pm['_price']) : '';

    $catList = isset($productCats[$pid]) ? implode(';', array_unique($productCats[$pid])) : '';
    $brand   = isset($productBrand[$pid]) ? $productBrand[$pid] : '';

    $missingProducts[] = array(
        'REF'             => $sku,
        'TITRE UK'        => $title,
        'CHAPO UK'        => $excerpt,
        'CHAPO FR'        => $excerpt,
        'DESCRIPTIF UK'   => $desc,
        'DESCRIPTIF FR'   => $desc,
        'POSTSCRIPTUM UK' => '',
        'POSTSCRIPTUM FR' => '',
        'PRIX'            => $price,
        'PRIX2'           => '',
        'PHOTO'           => '',
        'BRAND'           => $brand,
        'COULEUR UK'      => '',
        'MATERIAL UK'     => '',
        'CONTENT UK'      => '',
        'CATEGORIE'       => $catList,
    );
    $existingRefs[strtolower($sku)] = true;
}
unset($postMeta);
$added = csv_append($csv . '/products.csv', $missingProducts, $prodHeaders);
echo "Added $added missing products.\n\n";

/* ============================================================= contents.csv */
echo "=== contents.csv ===\n";

$contHeaders = array('TITLE FR','TITLE UK','CHAPO FR','CHAPO UK','DESCRIPTIF FR','DESCRIPTIF UK','PHOTO','FOLDERS UK');
$rCont = csv_read($csv . '/contents.csv');
$contRows = $rCont[1];
$existingContents = array();
foreach ($contRows as $cr) {
    $t = strtolower(trim(gv($cr, 'TITLE FR')));
    if ($t !== '') $existingContents[$t] = true;
}
echo "Existing contents: " . count($existingContents) . "\n";

$missingContents = array();
foreach ($postRows as $p) {
    $type = trim(gv($p, 'post_type'));
    if ($type !== 'page') continue;
    $status = trim(gv($p, 'post_status'));
    if ($status === 'trash' || $status === 'auto-draft') continue;
    $title = trim(gv($p, 'post_title'));
    if ($title === '') continue;
    $key = strtolower($title);
    if (isset($existingContents[$key])) continue;
    $desc = trim(gv($p, 'post_content'));
    $missingContents[] = array(
        'TITLE FR'      => $title,
        'TITLE UK'      => $title,
        'CHAPO FR'      => '',
        'CHAPO UK'      => '',
        'DESCRIPTIF FR' => $desc,
        'DESCRIPTIF UK' => $desc,
        'PHOTO'         => '',
        'FOLDERS UK'    => 'Information',
    );
    $existingContents[$key] = true;
}
$added = csv_append($csv . '/contents.csv', $missingContents, $contHeaders);
echo "Added $added missing contents.\n\n";

echo "Done!\n";
