<?php
/**
 * User: gaalferov
 * Date: 26.05.16
 * Time: 15:24
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://sushidomm.ru/";
$companySite = "http://sushidomm.ru";
$company = $companyName = "sushidomm";

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

//Удаляем пустые категорие, дублирующиеся товары, выставляем корректно id категории
$unique = html_restoreXML($outXml);
if ($unique) {
    html_writeStartFile($outXml, $unique['categories'], $companySite, $company, $companyName);
    foreach ($unique['offers'] as $offer) {
        html_writeProductInFile($outXml, $offer['categoryId'], $offer);
    }
    html_writeEndFile($outXml);
}

function parcing($url)
{

    $catID = 1;
    $categories = [];

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menus = $html_cat->find('ul.menu', 0);
    if ($menus) {
        $menus = $menus->children();
    } else {
        return false;
    }

    foreach ($menus as $menu) {

        $value = $menu->find('a', 0);

        $href = html_clear_var($value->href);
        $name = $value->plaintext;
        $name = mb_strtolower(html_clear_var($name), 'UTF-8');

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

    $prods_parcing = $html_prod->find('.catalog', $catId - 1);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->find('.block');
    } else {
        return false;
    }

    foreach ($prods_parcing as $key => $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $pid = $catId . '000' . $key;

        $nameData = $product->find('.title', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            if (!$name) {
                $name = $GLOBALS['categories'][$catId-1]['name'] . ' ' . $key;
            }
        }

        $imageData = $product->find('img', 0);
        if ($imageData) {
            $image = html_clear_var($companySite . $imageData->getAttribute('src'));
        }

        $priceData = $product->find('.price-block', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        $descData = $product->find('.text-block', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
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