<?php
/**
 * User: gaalferov
 * Date: 05.06.16
 * Time: 13:36
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://lasflore.ru/category/";
$companySite = "http://lasflore.ru";
$company = $companyName = "lasflore";

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
    createProducts($outXml, $companySite . $value['href'], $value['id'], $companySite);
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

    $menus = $html_cat->find('.rcat_root_category');
    if (!$menus) {
        return false;
    }

    foreach ($menus as $value) {

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

    $prods_parcing = $html_prod->find('.product_brief_block');
    if (!$prods_parcing) {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = $params = null;

        $idData = $product->find('input[name="productID"]', 0);
        if ($idData) {
            $pid = html_clear_var($idData->value, 'int');
        }

        $priceData = $product->find('.product_price', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->value, 'double');
        }

        $nameData = $product->find('.prdbrief_name a', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            if ($nameData->href) {
                $aUrl = $companySite . $nameData->href;
            }
        }

        if (empty($pid) || empty($name) || empty($aUrl) || empty($price))
            continue;

        $html_prod_intro = new simple_html_dom();
        $html_prod_intro->load(html_getSaitData($aUrl));

        $imageData = $html_prod_intro->find('#img-current_picture', 0);
        if ($imageData) {
            if ($imageData->src) {
                $image = html_clear_var($companySite . $imageData->src);
            }
        }

        $descData = $html_prod_intro->find('.cpt_product_description', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        if (empty($image))
            continue;

        $good = [
            'id' => $pid,
            'group_id' => $pid,
            'available' => true,
            'url' => $aUrl,
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
