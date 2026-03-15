<?php

/**
 * Script pour mettre à jour les prix dans le CSV GPT depuis WooCommerce
 * - PRIX = prix régulier
 * - PRIX2 = prix promo (si en solde)
 * - Pour les produits new dans WooCommerce → identifiés, sinon non-promo = new
 */

$wooDir  = __DIR__ . '/../wordpresswoocomerce/';
$gptFile = __DIR__ . '/csv/products.csv';
$outFile = __DIR__ . '/csv/products_updated.csv';

echo "=== Extraction des données WooCommerce ===\n";

// 1. Lire wc_product_meta_lookup pour obtenir onsale + product_id
echo "Chargement wc_product_meta_lookup...\n";
$metaLookup = []; // product_id => [onsale, min_price, max_price]
$fh = fopen($wooDir . 'wnew23p_wc_product_meta_lookup.csv', 'r');
$header = fgetcsv($fh, 0, ';');
// BOM
$header[0] = ltrim($header[0], "\xEF\xBB\xBF");
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    if (count($row) < count($header)) continue;
    $r = array_combine($header, $row);
    $metaLookup[$r['product_id']] = [
        'onsale'    => $r['onsale'],
        'min_price' => $r['min_price'],
        'max_price' => $r['max_price'],
        'sku'       => $r['sku'],
    ];
}
fclose($fh);
echo "  → " . count($metaLookup) . " produits dans meta_lookup\n";

// 2. Lire postmeta pour extraire _sku, _regular_price, _sale_price
echo "Chargement postmeta (fichier volumineux, patience)...\n";
$skuToPostId    = []; // sku => post_id
$postRegPrice   = []; // post_id => regular_price
$postSalePrice  = []; // post_id => sale_price

$fh = fopen($wooDir . 'wnew23p_postmeta.csv', 'r');
$header = fgetcsv($fh, 0, ';');
$header[0] = ltrim($header[0], "\xEF\xBB\xBF");
$count = 0;
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    if (count($row) < 4) continue;
    $postId   = $row[1];
    $metaKey  = $row[2];
    $metaVal  = $row[3];
    if ($metaKey === '_sku' && $metaVal !== '') {
        $skuToPostId[$metaVal] = $postId;
    } elseif ($metaKey === '_regular_price' && $metaVal !== '') {
        $postRegPrice[$postId] = $metaVal;
    } elseif ($metaKey === '_sale_price' && $metaVal !== '') {
        $postSalePrice[$postId] = $metaVal;
    }
    $count++;
}
fclose($fh);
echo "  → $count lignes lues, " . count($skuToPostId) . " SKUs trouvés\n";

// 3. Chercher les produits "new" via terme ou tag dans WooCommerce
// En l'absence de terme "new", on considère que non-promo = new (selon les instructions)
// Vérifier les termes pour "new"
echo "Recherche termes 'new'...\n";
$newTermIds = [];
$fh = fopen($wooDir . 'wnew23p_terms.csv', 'r');
$header = fgetcsv($fh, 0, ';');
$header[0] = ltrim($header[0], "\xEF\xBB\xBF");
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    if (count($row) < count($header)) continue;
    $r = array_combine($header, $row);
    if (stripos($r['slug'], 'new') !== false || stripos($r['name'], 'nouveau') !== false) {
        $newTermIds[$r['term_id']] = $r['name'];
    }
}
fclose($fh);

$newPostIds = [];
if (!empty($newTermIds)) {
    echo "  → Termes 'new' trouvés: " . implode(', ', $newTermIds) . "\n";
    // Chercher les term_taxonomy_id pour ces termes
    $termTaxNewIds = [];
    $fh = fopen($wooDir . 'wnew23p_term_taxonomy.csv', 'r');
    $header = fgetcsv($fh, 0, ';');
    $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (count($row) < count($header)) continue;
        $r = array_combine($header, $row);
        if (isset($newTermIds[$r['term_id']])) {
            $termTaxNewIds[$r['term_taxonomy_id']] = true;
        }
    }
    fclose($fh);

    if (!empty($termTaxNewIds)) {
        // Chercher les post_ids associés
        $fh = fopen($wooDir . 'wnew23p_term_relationships.csv', 'r');
        $header = fgetcsv($fh, 0, ';');
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            if (count($row) < count($header)) continue;
            $r = array_combine($header, $row);
            if (isset($termTaxNewIds[$r['term_taxonomy_id']])) {
                $newPostIds[$r['object_id']] = true;
            }
        }
        fclose($fh);
        echo "  → " . count($newPostIds) . " produits 'new' via termes\n";
    }
} else {
    echo "  → Aucun terme 'new' défini dans WooCommerce. Les produits non-promo seront marqués 'new'.\n";
}

$wooHasNewDefinition = !empty($newPostIds);

// 4. Construire un résumé par SKU
// sku => [regular_price, sale_price, onsale, is_new]
$skuData = [];
foreach ($skuToPostId as $sku => $postId) {
    $regPrice  = isset($postRegPrice[$postId]) ? $postRegPrice[$postId] : null;
    $salePrice = isset($postSalePrice[$postId]) ? $postSalePrice[$postId] : null;
    $onsale    = false;
    // Vérifier via wc_product_meta_lookup
    if (isset($metaLookup[$postId])) {
        $onsale = ($metaLookup[$postId]['onsale'] == '1');
        if (!$regPrice) $regPrice = $metaLookup[$postId]['max_price'];
        if (!$salePrice && $onsale) $salePrice = $metaLookup[$postId]['min_price'];
    }
    $isNew = $wooHasNewDefinition ? isset($newPostIds[$postId]) : !$onsale;
    $skuData[$sku] = [
        'regular_price' => $regPrice,
        'sale_price'    => ($onsale && $salePrice) ? $salePrice : null,
        'onsale'        => $onsale,
        'is_new'        => $isNew,
    ];
}
echo "  → " . count($skuData) . " SKUs avec données de prix\n";

// 5. Lire et mettre à jour le CSV GPT
echo "Mise à jour du CSV GPT...\n";
$fh = fopen($gptFile, 'r');
$fo = fopen($outFile, 'w');

// Lire l'en-tête
$headerLine = fgets($fh);
fputs($fo, $headerLine);
$headers = str_getcsv(trim($headerLine), ';', '"');
$headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");

// Index des colonnes clés
$colRef   = array_search('REF', $headers);
$colPrix  = array_search('PRIX', $headers);
$colPrix2 = array_search('PRIX2', $headers);

echo "  Colonnes: REF=$colRef, PRIX=$colPrix, PRIX2=$colPrix2\n";

$updatedCount = 0;
$notFoundInWoo = 0;
$gptRefs = [];

while (($row = fgetcsv($fh, 0, ';', '"')) !== false) {
    if (empty($row[$colRef])) {
        fputcsv($fo, $row, ';', '"');
        continue;
    }
    $ref = trim($row[$colRef]);
    $gptRefs[$ref] = true;

    if (isset($skuData[$ref])) {
        $data = $skuData[$ref];
        if ($data['regular_price'] !== null) {
            $newPrix  = number_format((float)$data['regular_price'], 4, '.', '');
            $newPrix2 = $data['sale_price'] !== null ? number_format((float)$data['sale_price'], 4, '.', '') : '';
            if ($row[$colPrix] != $newPrix || $row[$colPrix2] != $newPrix2) {
                $row[$colPrix]  = $newPrix;
                $row[$colPrix2] = $newPrix2;
                $updatedCount++;
            }
        }
    } else {
        $notFoundInWoo++;
    }
    fputcsv($fo, $row, ';', '"');
}
fclose($fh);

echo "  → $updatedCount produits mis à jour\n";
echo "  → $notFoundInWoo produits GPT non trouvés dans WooCommerce\n";

// 6. Ajouter les produits WooCommerce manquants dans le CSV GPT
echo "Recherche produits WooCommerce manquants dans GPT...\n";
$addedCount = 0;

// Récupérer les informations des produits depuis wp_posts
$postsData = []; // post_id => [title, slug]
$fh = fopen($wooDir . 'wnew23p_posts.csv', 'r');
$postsHeader = fgetcsv($fh, 0, ';');
$postsHeader[0] = ltrim($postsHeader[0], "\xEF\xBB\xBF");
$postNameIdx    = array_search('post_name', $postsHeader);
$postTitleIdx   = array_search('post_title', $postsHeader);
$postStatusIdx  = array_search('post_status', $postsHeader);
$postTypeIdx    = array_search('post_type', $postsHeader);
$postIdIdx      = array_search('ID', $postsHeader);
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    if (count($row) <= max($postNameIdx, $postTitleIdx, $postStatusIdx, $postTypeIdx)) continue;
    if ($row[$postTypeIdx] !== 'product') continue;
    if ($row[$postStatusIdx] !== 'publish') continue;
    $postsData[$row[$postIdIdx]] = [
        'title' => $row[$postTitleIdx],
        'slug'  => $row[$postNameIdx],
    ];
}
fclose($fh);
echo "  → " . count($postsData) . " produits WooCommerce publiés\n";

foreach ($skuData as $sku => $data) {
    if (isset($gptRefs[$sku])) continue; // existe déjà dans GPT
    $postId = isset($skuToPostId[$sku]) ? $skuToPostId[$sku] : null;
    if (!$postId) continue;
    $post = isset($postsData[$postId]) ? $postsData[$postId] : null;

    // Construire une ligne vide avec seulement les champs disponibles
    // REF;TITRE UK;CHAPO UK;CHAPO FR;DESCRIPTIF UK;DESCRIPTIF FR;POSTSCRIPTUM UK;POSTSCRIPTUM FR;PRIX;PRIX2;PHOTO;BRAND;COULEUR UK;MATERIAL UK;CONTENT UK;CATEGORIE
    $newRow = array_fill(0, count($headers), '');
    $newRow[$colRef]  = $sku;
    $newRow[$colPrix] = $data['regular_price'] ? number_format((float)$data['regular_price'], 4, '.', '') : '';
    $newRow[$colPrix2] = $data['sale_price'] ? number_format((float)$data['sale_price'], 4, '.', '') : '';
    if ($post) {
        $titleIdx = array_search('TITRE UK', $headers);
        if ($titleIdx !== false) $newRow[$titleIdx] = $post['title'];
        $photoIdx = array_search('PHOTO', $headers);
        if ($photoIdx !== false) $newRow[$photoIdx] = $post['slug'] . '.jpg';
    }
    fputcsv($fo, $newRow, ';', '"');
    $addedCount++;
}

fclose($fo);
echo "  → $addedCount produits ajoutés depuis WooCommerce\n";

echo "\n=== Terminé! Fichier mis à jour: $outFile ===\n";
echo "Statistiques:\n";
echo "  - Produits mis à jour: $updatedCount\n";
echo "  - Produits ajoutés:    $addedCount\n";
echo "  - Produits sans match: $notFoundInWoo\n";
