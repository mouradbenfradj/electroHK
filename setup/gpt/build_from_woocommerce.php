<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$setup = dirname(__DIR__);
$wp = $setup . '/wordpresswoocomerce';
$schemaFile = $setup . '/thelia.sql';
$out = $setup . '/gpt';
$csvDir = $out . '/csv';
$imgSrc = $setup . '/electrohajkacem';
$imgDst = $out . '/images';

@mkdir($csvDir, 0777, true);
@mkdir($imgDst, 0777, true);

$schema = parse_schema($schemaFile);
$writers = array();
foreach ($schema as $table => $cols) {
    $fh = fopen($csvDir . '/' . $table . '.csv', 'wb');
    fputcsv($fh, $cols, ';');
    $writers[$table] = $fh;
}

$now = date('Y-m-d H:i:s');
$brandTax = array('product_brand' => true, 'pa_marque' => true);

$terms = load_by_id($wp . '/wnew23p_terms.csv', 'term_id');
$tt = array();
csv_each($wp . '/wnew23p_term_taxonomy.csv', function ($r) use (&$tt) {
    $id = to_int(getv($r, 'term_taxonomy_id'));
    $termId = to_int(getv($r, 'term_id'));
    if ($id === null || $termId === null) return;
    $tt[$id] = array(
        'term_id' => $termId,
        'taxonomy' => trim((string)getv($r, 'taxonomy', '')),
        'parent' => to_int(getv($r, 'parent')) ?: 0
    );
});

$rels = array();
csv_each($wp . '/wnew23p_term_relationships.csv', function ($r) use (&$rels) {
    $obj = to_int(getv($r, 'object_id'));
    $ttId = to_int(getv($r, 'term_taxonomy_id'));
    if ($obj === null || $ttId === null) return;
    if (!isset($rels[$obj])) $rels[$obj] = array();
    $rels[$obj][] = $ttId;
});

$termThumb = array();
csv_each($wp . '/wnew23p_termmeta.csv', function ($r) use (&$termThumb) {
    if (getv($r, 'meta_key', '') !== 'thumbnail_id') return;
    $termId = to_int(getv($r, 'term_id'));
    $att = to_int(getv($r, 'meta_value'));
    if ($termId === null || $att === null) return;
    $termThumb[$termId] = $att;
});

$products = array();
$vars = array();
$varsByParent = array();
$orders = array();
$attach = array();
csv_each($wp . '/wnew23p_posts.csv', function ($r) use (&$products, &$vars, &$varsByParent, &$orders, &$attach) {
    $id = to_int(getv($r, 'ID'));
    if ($id === null) return;
    $type = trim((string)getv($r, 'post_type', ''));
    if ($type === 'product') {
        $products[$id] = $r;
    } elseif ($type === 'product_variation') {
        $vars[$id] = $r;
        $p = to_int(getv($r, 'post_parent')) ?: 0;
        if ($p > 0) {
            if (!isset($varsByParent[$p])) $varsByParent[$p] = array();
            $varsByParent[$p][] = $id;
        }
    } elseif ($type === 'shop_order') {
        $orders[$id] = $r;
    } elseif ($type === 'attachment') {
        $attach[$id] = $r;
    }
});

$users = load_by_id($wp . '/wnew23p_users.csv', 'ID');
$userMeta = load_user_meta($wp . '/wnew23p_usermeta.csv');
$postMeta = load_post_meta($wp . '/wnew23p_postmeta.csv', array_keys($products + $vars + $orders));
$orderItemsByOrder = load_order_items($wp);

$countryCodes = array('TN' => true);
foreach ($userMeta as $m) {
    foreach (array('billing_country', 'shipping_country') as $k) {
        $cc = strtoupper(trim((string)getv($m, $k, '')));
        if ($cc !== '') $countryCodes[$cc] = true;
    }
}
foreach ($postMeta as $m) {
    foreach (array('_billing_country', '_shipping_country') as $k) {
        $cc = strtoupper(trim((string)getv($m, $k, '')));
        if ($cc !== '') $countryCodes[$cc] = true;
    }
}
$countryMap = map_ids(array_keys($countryCodes));
$defaultCountry = isset($countryMap['TN']) ? $countryMap['TN'] : 1;

$currencyCodes = array('TND' => true);
foreach ($postMeta as $m) {
    $cc = strtoupper(trim((string)getv($m, '_order_currency', '')));
    if ($cc !== '') $currencyCodes[$cc] = true;
}
$currencyMap = map_ids(array_keys($currencyCodes));
$defaultCurrency = isset($currencyMap['TND']) ? $currencyMap['TND'] : 1;

$statusMap = array();
$sid = 1;
foreach ($orders as $o) {
    $st = (string)getv($o, 'post_status', 'wc-pending');
    if (!isset($statusMap[$st])) $statusMap[$st] = $sid++;
}
if (!$statusMap) $statusMap = array('wc-pending' => 1);

seed_base($writers, $schema, $now, $countryMap, $defaultCountry, $currencyMap, $defaultCurrency, $statusMap);

$neededImages = array();
$brandMap = array();
$catImgId = 1;
$brandImgId = 1;

foreach ($tt as $ttId => $row) {
    if ($row['taxonomy'] !== 'product_cat') continue;
    $termId = $row['term_id'];
    $title = getv(getv($terms, $termId, array()), 'name', 'Category ' . $termId);
    row($writers, $schema, 'category', array('id' => $termId, 'parent' => $row['parent'], 'visible' => 1, 'position' => $termId, 'created_at' => $now, 'updated_at' => $now, 'version' => 1, 'version_created_at' => $now, 'version_created_by' => 'gpt'));
    foreach (array('fr_FR', 'en_US') as $loc) {
        row($writers, $schema, 'category_i18n', array('id' => $termId, 'locale' => $loc, 'title' => $title, 'meta_title' => $title));
    }
    if (isset($termThumb[$termId])) {
        $f = attachment_file($attach, $termThumb[$termId]);
        if ($f) {
            $neededImages[strtolower($f)] = $f;
            row($writers, $schema, 'category_image', array('id' => $catImgId, 'category_id' => $termId, 'file' => $f, 'visible' => 1, 'position' => 1, 'created_at' => $now, 'updated_at' => $now));
            $catImgId++;
        }
    }
}

foreach ($tt as $ttId => $row) {
    if (!isset($brandTax[$row['taxonomy']])) continue;
    $termId = $row['term_id'];
    $brandId = $termId;
    $brandMap[$termId] = $brandId;
    $title = getv(getv($terms, $termId, array()), 'name', 'Brand ' . $brandId);
    row($writers, $schema, 'brand', array('id' => $brandId, 'visible' => 1, 'position' => $brandId, 'created_at' => $now, 'updated_at' => $now));
    foreach (array('fr_FR', 'en_US') as $loc) {
        row($writers, $schema, 'brand_i18n', array('id' => $brandId, 'locale' => $loc, 'title' => $title, 'meta_title' => $title));
    }
    if (isset($termThumb[$termId])) {
        $f = attachment_file($attach, $termThumb[$termId]);
        if ($f) {
            $neededImages[strtolower($f)] = $f;
            row($writers, $schema, 'brand_image', array('id' => $brandImgId, 'brand_id' => $brandId, 'file' => $f, 'visible' => 1, 'position' => 1, 'created_at' => $now, 'updated_at' => $now));
            $brandImgId++;
        }
    }
}

$pseByProduct = array();
$pseByVar = array();
$pseId = 1;
$imgId = 1;

foreach ($products as $pid => $p) {
    $m = getv($postMeta, $pid, array());
    $ref = trim((string)getv($m, '_sku', ''));
    if ($ref === '') $ref = 'WP-PROD-' . $pid;

    $brandId = '';
    $catIds = array();
    $links = getv($rels, $pid, array());
    foreach ($links as $ttId) {
        if (!isset($tt[$ttId])) continue;
        $tax = $tt[$ttId]['taxonomy'];
        $termId = $tt[$ttId]['term_id'];
        if ($tax === 'product_cat') $catIds[] = $termId;
        if (isset($brandTax[$tax]) && isset($brandMap[$termId])) $brandId = $brandMap[$termId];
    }

    row($writers, $schema, 'product', array('id' => $pid, 'tax_rule_id' => 1, 'ref' => $ref, 'visible' => (getv($p, 'post_status', '') === 'publish') ? 1 : 0, 'position' => $pid, 'brand_id' => $brandId, 'virtual' => 0, 'created_at' => date_ok(getv($p, 'post_date', ''), $now), 'updated_at' => date_ok(getv($p, 'post_modified', ''), $now), 'version' => 1, 'version_created_at' => $now, 'version_created_by' => 'gpt'));
    $title = trim((string)getv($p, 'post_title', ''));
    if ($title === '') $title = 'Product ' . $pid;
    foreach (array('fr_FR', 'en_US') as $loc) {
        row($writers, $schema, 'product_i18n', array('id' => $pid, 'locale' => $loc, 'title' => $title, 'description' => getv($p, 'post_content', ''), 'chapo' => getv($p, 'post_excerpt', ''), 'meta_title' => $title));
    }
    $pos = 1;
    $def = false;
    foreach (array_values(array_unique($catIds)) as $cid) {
        row($writers, $schema, 'product_category', array('product_id' => $pid, 'category_id' => $cid, 'default_category' => $def ? 0 : 1, 'position' => $pos++, 'created_at' => $now, 'updated_at' => $now));
        $def = true;
    }

    $vIds = getv($varsByParent, $pid, array());
    if (!$vIds) {
        $price = to_float(getv($m, '_regular_price')); if ($price === null) $price = to_float(getv($m, '_price')); if ($price === null) $price = 0;
        $sale = to_float(getv($m, '_sale_price')); if ($sale === null) $sale = 0;
        $qty = to_float(getv($m, '_stock')); if ($qty === null) $qty = 0; if ($qty < 0) $qty = 0;
        $promo = ($sale > 0 && $sale < $price) ? 1 : 0;
        $pseRef = $ref . '-PSE';
        row($writers, $schema, 'product_sale_elements', array('id' => $pseId, 'product_id' => $pid, 'ref' => $pseRef, 'quantity' => $qty, 'promo' => $promo, 'newness' => 0, 'weight' => to_float(getv($m, '_weight')) ?: 0, 'is_default' => 1, 'created_at' => $now, 'updated_at' => $now));
        row($writers, $schema, 'product_price', array('product_sale_elements_id' => $pseId, 'currency_id' => $defaultCurrency, 'price' => nf($price), 'promo_price' => nf($promo ? $sale : 0), 'from_default_currency' => 1, 'created_at' => $now, 'updated_at' => $now));
        $pseByProduct[$pid] = array('id' => $pseId, 'ref' => $pseRef);
        $pseId++;
    } else {
        $first = true;
        foreach ($vIds as $vid) {
            $vm = getv($postMeta, $vid, array());
            $vRef = trim((string)getv($vm, '_sku', '')); if ($vRef === '') $vRef = $ref . '-VAR-' . $vid;
            $price = to_float(getv($vm, '_regular_price')); if ($price === null) $price = to_float(getv($vm, '_price')); if ($price === null) $price = 0;
            $sale = to_float(getv($vm, '_sale_price')); if ($sale === null) $sale = 0;
            $qty = to_float(getv($vm, '_stock')); if ($qty === null) $qty = 0; if ($qty < 0) $qty = 0;
            $promo = ($sale > 0 && $sale < $price) ? 1 : 0;
            row($writers, $schema, 'product_sale_elements', array('id' => $pseId, 'product_id' => $pid, 'ref' => $vRef, 'quantity' => $qty, 'promo' => $promo, 'newness' => 0, 'weight' => to_float(getv($vm, '_weight')) ?: 0, 'is_default' => $first ? 1 : 0, 'created_at' => $now, 'updated_at' => $now));
            row($writers, $schema, 'product_price', array('product_sale_elements_id' => $pseId, 'currency_id' => $defaultCurrency, 'price' => nf($price), 'promo_price' => nf($promo ? $sale : 0), 'from_default_currency' => 1, 'created_at' => $now, 'updated_at' => $now));
            $pseByVar[$vid] = array('id' => $pseId, 'ref' => $vRef);
            if ($first) $pseByProduct[$pid] = array('id' => $pseId, 'ref' => $vRef);
            $first = false; $pseId++;
        }
    }

    $images = array();
    $thumb = to_int(getv($m, '_thumbnail_id'));
    if ($thumb) {
        $f = attachment_file($attach, $thumb); if ($f) $images[] = $f;
    }
    $gal = trim((string)getv($m, '_product_image_gallery', ''));
    if ($gal !== '') {
        foreach (explode(',', $gal) as $chunk) {
            $aid = to_int($chunk);
            if ($aid) {
                $f = attachment_file($attach, $aid); if ($f) $images[] = $f;
            }
        }
    }
    $ipos = 1;
    foreach (array_values(array_unique($images)) as $fimg) {
        $neededImages[strtolower($fimg)] = $fimg;
        row($writers, $schema, 'product_image', array('id' => $imgId, 'product_id' => $pid, 'file' => $fimg, 'visible' => 1, 'position' => $ipos++, 'created_at' => $now, 'updated_at' => $now));
        $imgId++;
    }
}

$addressId = 1;
$customerMap = array();
foreach ($users as $uid => $u) {
    $um = getv($userMeta, $uid, array());
    $full = trim((string)getv($um, 'first_name', '') . ' ' . (string)getv($um, 'last_name', ''));
    if ($full === '') $full = getv($u, 'display_name', '');
    list($fn, $ln) = split_name($full);
    if ($fn === '') $fn = 'Client';
    if ($ln === '') $ln = (string)$uid;
    $customerMap[$uid] = $uid;
    row($writers, $schema, 'customer', array('id' => $uid, 'title_id' => 1, 'lang_id' => 1, 'ref' => 'WPUSR-' . $uid, 'firstname' => $fn, 'lastname' => $ln, 'email' => getv($u, 'user_email', ''), 'password' => getv($u, 'user_pass', ''), 'algo' => 'wordpress', 'reseller' => 0, 'discount' => '0.000000', 'enable' => 1, 'created_at' => date_ok(getv($u, 'user_registered', ''), $now), 'updated_at' => $now, 'version' => 1, 'version_created_at' => $now, 'version_created_by' => 'gpt'));
    $cc = strtoupper(trim((string)getv($um, 'billing_country', 'TN')));
    $cid = isset($countryMap[$cc]) ? $countryMap[$cc] : $defaultCountry;
    row($writers, $schema, 'address', array('id' => $addressId, 'label' => 'Adresse principale', 'customer_id' => $uid, 'title_id' => 1, 'company' => getv($um, 'billing_company', ''), 'firstname' => getv($um, 'billing_first_name', $fn), 'lastname' => getv($um, 'billing_last_name', $ln), 'address1' => getv($um, 'billing_address_1', 'Adresse inconnue'), 'address2' => getv($um, 'billing_address_2', ''), 'address3' => '', 'zipcode' => substr((string)getv($um, 'billing_postcode', '0000'), 0, 10), 'city' => getv($um, 'billing_city', 'Ville'), 'country_id' => $cid, 'phone' => getv($um, 'billing_phone', ''), 'cellphone' => '', 'is_default' => 1, 'created_at' => $now, 'updated_at' => $now));
    $addressId++;
}

$orderAddressId = 1;
$orderProductId = 1;
$guestBase = 1000000;
foreach ($orders as $oid => $o) {
    $m = getv($postMeta, $oid, array());
    $wpUid = to_int(getv($m, '_customer_user')); if ($wpUid === null) $wpUid = 0;
    $cust = isset($customerMap[$wpUid]) ? $customerMap[$wpUid] : null;
    if ($cust === null) {
        $cust = $guestBase + $oid;
        if (!isset($customerMap[$cust])) {
            row($writers, $schema, 'customer', array('id' => $cust, 'title_id' => 1, 'lang_id' => 1, 'ref' => 'WPGUEST-' . $oid, 'firstname' => getv($m, '_billing_first_name', 'Client'), 'lastname' => getv($m, '_billing_last_name', (string)$oid), 'email' => getv($m, '_billing_email', ''), 'password' => '', 'algo' => '', 'reseller' => 0, 'discount' => '0.000000', 'enable' => 1, 'created_at' => date_ok(getv($o, 'post_date', ''), $now), 'updated_at' => $now, 'version' => 1, 'version_created_at' => $now, 'version_created_by' => 'gpt'));
            $customerMap[$cust] = $cust;
        }
    }
    $bcc = strtoupper(trim((string)getv($m, '_billing_country', 'TN')));
    $scc = strtoupper(trim((string)getv($m, '_shipping_country', $bcc)));
    $bcid = isset($countryMap[$bcc]) ? $countryMap[$bcc] : $defaultCountry;
    $scid = isset($countryMap[$scc]) ? $countryMap[$scc] : $bcid;
    $bAddr = $orderAddressId++; $sAddr = $orderAddressId++;
    row($writers, $schema, 'order_address', array('id' => $bAddr, 'customer_title_id' => 1, 'company' => getv($m, '_billing_company', ''), 'firstname' => getv($m, '_billing_first_name', 'Client'), 'lastname' => getv($m, '_billing_last_name', (string)$oid), 'address1' => getv($m, '_billing_address_1', 'Adresse inconnue'), 'address2' => getv($m, '_billing_address_2', ''), 'address3' => '', 'zipcode' => substr((string)getv($m, '_billing_postcode', '0000'), 0, 10), 'city' => getv($m, '_billing_city', 'Ville'), 'phone' => getv($m, '_billing_phone', ''), 'cellphone' => '', 'country_id' => $bcid, 'created_at' => $now, 'updated_at' => $now));
    row($writers, $schema, 'order_address', array('id' => $sAddr, 'customer_title_id' => 1, 'company' => getv($m, '_shipping_company', ''), 'firstname' => getv($m, '_shipping_first_name', getv($m, '_billing_first_name', 'Client')), 'lastname' => getv($m, '_shipping_last_name', getv($m, '_billing_last_name', (string)$oid)), 'address1' => getv($m, '_shipping_address_1', getv($m, '_billing_address_1', 'Adresse inconnue')), 'address2' => getv($m, '_shipping_address_2', getv($m, '_billing_address_2', '')), 'address3' => '', 'zipcode' => substr((string)getv($m, '_shipping_postcode', getv($m, '_billing_postcode', '0000')), 0, 10), 'city' => getv($m, '_shipping_city', getv($m, '_billing_city', 'Ville')), 'phone' => getv($m, '_shipping_phone', getv($m, '_billing_phone', '')), 'cellphone' => '', 'country_id' => $scid, 'created_at' => $now, 'updated_at' => $now));
    $cur = strtoupper(trim((string)getv($m, '_order_currency', 'TND')));
    $curId = isset($currencyMap[$cur]) ? $currencyMap[$cur] : $defaultCurrency;
    $st = getv($statusMap, getv($o, 'post_status', 'wc-pending'), 1);
    row($writers, $schema, 'cart', array('id' => $oid, 'token' => 'cart-' . $oid, 'customer_id' => $cust, 'currency_id' => $curId, 'discount' => nf(to_float(getv($m, '_cart_discount')) ?: 0), 'created_at' => date_ok(getv($o, 'post_date', ''), $now), 'updated_at' => $now));
    $oref = trim((string)getv($m, '_order_number', '')); if ($oref === '') $oref = 'WP-ORDER-' . $oid;
    row($writers, $schema, 'order', array('id' => $oid, 'ref' => $oref, 'customer_id' => $cust, 'invoice_order_address_id' => $bAddr, 'delivery_order_address_id' => $sAddr, 'invoice_date' => date_ok(getv($o, 'post_date', ''), $now), 'currency_id' => $curId, 'currency_rate' => 1, 'transaction_ref' => getv($m, '_transaction_id', ''), 'invoice_ref' => $oref, 'discount' => nf(to_float(getv($m, '_cart_discount')) ?: 0), 'postage' => nf(to_float(getv($m, '_order_shipping')) ?: 0), 'postage_tax' => nf(to_float(getv($m, '_order_shipping_tax')) ?: 0), 'postage_tax_rule_title' => 'TVA 0%', 'payment_module_id' => 1, 'delivery_module_id' => 1, 'status_id' => $st, 'lang_id' => 1, 'cart_id' => $oid, 'created_at' => date_ok(getv($o, 'post_date', ''), $now), 'updated_at' => date_ok(getv($o, 'post_modified', ''), $now), 'version' => 1, 'version_created_at' => $now, 'version_created_by' => 'gpt'));
    foreach (getv($orderItemsByOrder, $oid, array()) as $it) {
        $im = getv($it, 'meta', array());
        $pid = to_int(getv($im, '_product_id')); if ($pid === null) $pid = 0;
        $vid = to_int(getv($im, '_variation_id')); if ($vid === null) $vid = 0;
        $qty = to_float(getv($im, '_qty')); if ($qty === null || $qty <= 0) $qty = 1;
        $lineTotal = to_float(getv($im, '_line_total')); if ($lineTotal === null) $lineTotal = 0;
        $lineSub = to_float(getv($im, '_line_subtotal')); if ($lineSub === null) $lineSub = $lineTotal;
        $uPrice = $lineSub / $qty;
        $uPromo = $lineTotal / $qty;
        $pse = null;
        if ($vid > 0 && isset($pseByVar[$vid])) $pse = $pseByVar[$vid];
        elseif ($pid > 0 && isset($pseByProduct[$pid])) $pse = $pseByProduct[$pid];
        $pref = $pid > 0 ? (trim((string)getv(getv($postMeta, $pid, array()), '_sku', '')) ?: ('WP-PROD-' . $pid)) : ('WP-LINE-' . getv($it, 'order_item_id', '0'));
        row($writers, $schema, 'order_product', array('id' => $orderProductId, 'order_id' => $oid, 'product_ref' => $pref, 'product_sale_elements_ref' => $pse ? $pse['ref'] : ($pref . '-PSE'), 'product_sale_elements_id' => $pse ? $pse['id'] : '', 'title' => getv($it, 'name', ''), 'quantity' => $qty, 'price' => nf($uPrice), 'promo_price' => nf($uPromo), 'was_new' => 0, 'was_in_promo' => ($uPromo < $uPrice) ? 1 : 0, 'weight' => '0', 'tax_rule_title' => 'TVA 0%', 'virtual' => 0, 'created_at' => $now, 'updated_at' => $now));
        $orderProductId++;
    }
}

$index = array();
if (is_dir($imgSrc)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imgSrc, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if (!$fi->isFile()) continue;
        $k = strtolower($fi->getBasename());
        if (!isset($index[$k])) $index[$k] = $fi->getPathname();
    }
}
$copied = 0; $missing = array();
foreach ($neededImages as $k => $name) {
    if (!isset($index[$k])) { $missing[] = $name; continue; }
    if (@copy($index[$k], $imgDst . '/' . $name)) $copied++; else $missing[] = $name;
}
file_put_contents($out . '/missing_images.txt', implode(PHP_EOL, array_unique($missing)) . PHP_EOL);

foreach ($writers as $fh) fclose($fh);
echo "CSV dir: {$csvDir}\n";
echo "Images copied={$copied} missing=" . count(array_unique($missing)) . "\n";

function parse_schema($file) { $lines=@file($file, FILE_IGNORE_NEW_LINES); if($lines===false) return array(); $s=array(); $t=null; foreach($lines as $line){ if(preg_match('/^CREATE TABLE `([^`]+)`\s*$/', trim($line), $m)){ $t=$m[1]; $s[$t]=array(); continue; } if($t!==null){ if(preg_match('/^\s*`([^`]+)`\s+/', $line, $m)){ $s[$t][]=$m[1]; continue; } if(preg_match('/^\) ENGINE=/', trim($line))) $t=null; } } return $s; }
function csv_each($file, $cb) { if(!is_file($file)) return; $h=fopen($file,'rb'); if(!$h) return; $head=fgetcsv($h,0,';'); if($head===false){fclose($h);return;} $head=array_map(function($x){return trim((string)$x,"\xEF\xBB\xBF \t\n\r\0\x0B");},$head); while(($row=fgetcsv($h,0,';'))!==false){ if(count($row)<count($head)) $row=array_pad($row,count($head),''); elseif(count($row)>count($head)) $row=array_slice($row,0,count($head)); $assoc=array_combine($head,$row); if($assoc!==false) call_user_func($cb,$assoc);} fclose($h); }
function load_by_id($file, $key) { $out=array(); csv_each($file,function($r) use (&$out,$key){$id=to_int(getv($r,$key)); if($id!==null)$out[$id]=$r;}); return $out; }
function load_user_meta($file) { $wanted=array_fill_keys(array('first_name','last_name','billing_first_name','billing_last_name','billing_company','billing_address_1','billing_address_2','billing_city','billing_postcode','billing_country','billing_phone','billing_state','shipping_first_name','shipping_last_name','shipping_company','shipping_address_1','shipping_address_2','shipping_city','shipping_postcode','shipping_country','shipping_phone','shipping_state'),true); $out=array(); csv_each($file,function($r) use (&$out,$wanted){$uid=to_int(getv($r,'user_id')); $k=getv($r,'meta_key',''); if($uid===null||!isset($wanted[$k])) return; if(!isset($out[$uid]))$out[$uid]=array(); $out[$uid][$k]=getv($r,'meta_value','');}); return $out; }
function load_post_meta($file, $ids) { $idSet=array_fill_keys(array_map('strval',$ids),true); $wanted=array_fill_keys(array('_sku','_price','_regular_price','_sale_price','_stock','_weight','_thumbnail_id','_product_image_gallery','_billing_first_name','_billing_last_name','_billing_company','_billing_address_1','_billing_address_2','_billing_city','_billing_postcode','_billing_country','_billing_phone','_billing_email','_shipping_first_name','_shipping_last_name','_shipping_company','_shipping_address_1','_shipping_address_2','_shipping_city','_shipping_postcode','_shipping_country','_shipping_phone','_order_currency','_order_shipping','_order_shipping_tax','_cart_discount','_customer_user','_transaction_id','_order_number'),true); $out=array(); csv_each($file,function($r) use (&$out,$idSet,$wanted){$pid=to_int(getv($r,'post_id')); $k=getv($r,'meta_key',''); if($pid===null||!isset($idSet[(string)$pid])||!isset($wanted[$k])) return; if(!isset($out[$pid]))$out[$pid]=array(); $out[$pid][$k]=getv($r,'meta_value','');}); return $out; }
function load_order_items($wp){ $byOrder=array(); $byId=array(); csv_each($wp.'/wnew23p_woocommerce_order_items.csv',function($r) use (&$byOrder,&$byId){$id=to_int(getv($r,'order_item_id')); $oid=to_int(getv($r,'order_id')); if($id===null||$oid===null||getv($r,'order_item_type','')!=='line_item')return; if(!isset($byOrder[$oid]))$byOrder[$oid]=array(); $byOrder[$oid][]=array('order_item_id'=>$id,'name'=>getv($r,'order_item_name',''),'meta'=>array()); $idx=count($byOrder[$oid])-1; $byId[$id]=array(&$byOrder[$oid][$idx]);}); $wanted=array_fill_keys(array('_product_id','_variation_id','_qty','_line_total','_line_subtotal'),true); csv_each($wp.'/wnew23p_woocommerce_order_itemmeta.csv',function($r) use (&$byId,$wanted){$id=to_int(getv($r,'order_item_id')); $k=getv($r,'meta_key',''); if($id===null||!isset($byId[$id])||!isset($wanted[$k]))return; foreach($byId[$id] as &$ref){$ref['meta'][$k]=getv($r,'meta_value','');} unset($ref);}); return $byOrder; }
function row($writers,$schema,$table,$data){ if(!isset($writers[$table])||!isset($schema[$table])) return; $line=array(); foreach($schema[$table] as $c){ $line[] = isset($data[$c]) ? (string)$data[$c] : ''; } fputcsv($writers[$table],$line,';'); }
function seed_base($writers,$schema,$now,$countryMap,$defaultCountry,$currencyMap,$defaultCurrency,$statusMap){ row($writers,$schema,'lang',array('id'=>1,'title'=>'Français','code'=>'fr','locale'=>'fr_FR','date_format'=>'d/m/Y','time_format'=>'H:i','datetime_format'=>'d/m/Y H:i','decimal_separator'=>',','thousands_separator'=>' ','active'=>1,'visible'=>1,'decimals'=>'2','by_default'=>1,'position'=>1,'created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'customer_title',array('id'=>1,'by_default'=>1,'position'=>'1','created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'customer_title_i18n',array('id'=>1,'locale'=>'fr_FR','short'=>'M.','long'=>'Monsieur')); row($writers,$schema,'customer_title_i18n',array('id'=>1,'locale'=>'en_US','short'=>'Mr','long'=>'Mister')); foreach($countryMap as $code=>$id){ row($writers,$schema,'country',array('id'=>$id,'visible'=>1,'isocode'=>$code,'isoalpha2'=>$code,'isoalpha3'=>strlen($code)===2?$code.'X':substr($code,0,3),'has_states'=>0,'need_zip_code'=>0,'zip_code_format'=>'','by_default'=>$id===$defaultCountry?1:0,'shop_country'=>$id===$defaultCountry?1:0,'created_at'=>$now,'updated_at'=>$now)); $title=$code==='TN'?'Tunisie':$code; row($writers,$schema,'country_i18n',array('id'=>$id,'locale'=>'fr_FR','title'=>$title)); row($writers,$schema,'country_i18n',array('id'=>$id,'locale'=>'en_US','title'=>$title)); } foreach($currencyMap as $code=>$id){ row($writers,$schema,'currency',array('id'=>$id,'code'=>$code,'symbol'=>$code,'format'=>'%n %s','rate'=>1,'visible'=>1,'position'=>$id,'by_default'=>$id===$defaultCurrency?1:0,'created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'currency_i18n',array('id'=>$id,'locale'=>'fr_FR','name'=>$code)); row($writers,$schema,'currency_i18n',array('id'=>$id,'locale'=>'en_US','name'=>$code)); } row($writers,$schema,'tax',array('id'=>1,'type'=>'Thelia\\TaxEngine\\TaxType\\FixAmountTaxType','serialized_requirements'=>serialize(array('amount'=>0)),'created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'tax_i18n',array('id'=>1,'locale'=>'fr_FR','title'=>'TVA 0%')); row($writers,$schema,'tax_i18n',array('id'=>1,'locale'=>'en_US','title'=>'VAT 0%')); row($writers,$schema,'tax_rule',array('id'=>1,'is_default'=>1,'created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'tax_rule_i18n',array('id'=>1,'locale'=>'fr_FR','title'=>'Règle TVA')); row($writers,$schema,'tax_rule_i18n',array('id'=>1,'locale'=>'en_US','title'=>'VAT Rule')); $i=1; foreach($countryMap as $code=>$id){ row($writers,$schema,'tax_rule_country',array('id'=>$i,'tax_rule_id'=>1,'country_id'=>$id,'tax_id'=>1,'position'=>$i,'created_at'=>$now,'updated_at'=>$now)); $i++; } row($writers,$schema,'module',array('id'=>1,'code'=>'manual','version'=>'1.0.0','type'=>2,'category'=>'classic','activate'=>1,'position'=>1,'full_namespace'=>'','mandatory'=>0,'hidden'=>0,'created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'module_i18n',array('id'=>1,'locale'=>'fr_FR','title'=>'Module manuel')); row($writers,$schema,'module_i18n',array('id'=>1,'locale'=>'en_US','title'=>'Manual module')); foreach($statusMap as $s=>$id){ $code=str_replace('wc-','',$s); $title=ucwords(str_replace('-',' ',$code)); row($writers,$schema,'order_status',array('id'=>$id,'code'=>$code,'color'=>'#888888','position'=>$id,'protected_status'=>0,'created_at'=>$now,'updated_at'=>$now)); row($writers,$schema,'order_status_i18n',array('id'=>$id,'locale'=>'fr_FR','title'=>$title)); row($writers,$schema,'order_status_i18n',array('id'=>$id,'locale'=>'en_US','title'=>$title)); } }
function getv($arr,$key,$default=null){ return (is_array($arr) && array_key_exists($key,$arr)) ? $arr[$key] : $default; }
function to_int($v){ if($v===null) return null; $s=trim((string)$v," \t\n\r\0\x0B\""); if($s===''||!preg_match('/^-?\d+$/',$s)) return null; return (int)$s; }
function to_float($v){ if($v===null) return null; $s=str_replace(',','.',trim((string)$v," \t\n\r\0\x0B\"")); if($s===''||!is_numeric($s)) return null; return (float)$s; }
function nf($f){ return number_format((float)$f,6,'.',''); }
function map_ids($codes){ $m=array(); $i=1; foreach($codes as $c){ $m[$c]=$i++; } return $m; }
function date_ok($v,$fallback){ $v=trim((string)$v," \t\n\r\0\x0B\""); if($v===''||$v==='0000-00-00 00:00:00') return $fallback; return $v; }
function split_name($s){ $s=trim((string)$s); if($s==='') return array('',''); $p=preg_split('/\s+/',$s); if(!$p) return array('',''); if(count($p)<=1) return array($p[0],''); $f=array_shift($p); return array($f,implode(' ',$p)); }
function attachment_file($attach,$id){ if(!isset($attach[$id])) return null; $g=getv($attach[$id],'guid',''); if($g==='') return null; $p=parse_url($g, PHP_URL_PATH); if(!is_string($p)||$p==='') return null; $b=basename($p); return $b!=='' ? rawurldecode($b) : null; }
