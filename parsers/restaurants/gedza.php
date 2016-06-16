<?php
/**
 * User: gaalferov
 * Date: 07.06.16
 * Time: 11:50
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://gedza.ru/";
$companySite = "http://gedza.ru";
$company = $companyName = "gedza";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$categories = parcing($inParceUrl);

html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);
foreach ($categories as $value) {
    createProducts($outXml, $inParceUrl . $value['href'], $value['id'], $companySite);
}
html_writeEndFile($outXml);

function parcing($url)
{

    $catID = 1;
    $categories = [];

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menus = $html_cat->find('.item-list .menu', 0);
    if ($menus) {
        $menus = $menus->children();
    } else {
        return false;
    }

    foreach ($menus as $menu) {

        $value = $menu->find('a', 0);

        $href = html_clear_var($value->href);
        $name = mb_strtolower($value->plaintext, 'UTF-8');

        if ($name == '' || $href == '') {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => null,
            'name' => $name,
            'href' => $href
        ];

        ++$catID;

    }

    $html_cat->clear();
    return $categories;
}

function createProducts($outXml, $urlCatParce, $catId, $companySite)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.catitem');
    if (!$prods_parcing) {
        return false;
    }

    foreach ($prods_parcing as $key => $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('input[name="product_id"]', 0);
        if ($idData) {
            $pid = html_clear_var($idData->value, 'int');
        }

        $nameData = $product->find('.node__title', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
        }

        $imageData = $product->find('.commerce-product-field-field-image .colorbox', 0);
        if ($imageData) {
            $image = html_clear_var($imageData->href);
            $description = str_replace([$name . '|||', $name . ' |||'], '', html_clear_var($imageData->getAttribute('title')));
        }

        $priceData = $product->find('.field-commerce-price', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        $weightData = $product->find('.commerce-product-field-field-weight', 0);
        if ($weightData) {
            $name = $name . ' ' . html_clear_var($weightData->plaintext);
        }

        if (empty($pid) || empty($name) || empty($image) || empty($price))
            continue;

        $good = [
            'id' => $pid,
            'group_id' => $pid,
            'available' => true,
            'url' => $urlCatParce,
            'image' => $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        html_writeProductInFile($outXml, $catId, $good);
    }

    $html_prod->clear();

    return true;
}

