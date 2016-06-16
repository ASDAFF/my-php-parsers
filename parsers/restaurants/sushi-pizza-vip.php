<?php
/**
 * User: gaalferov
 * Date: 19.05.16
 * Time: 11:15
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://sushi-pizza-vip.ru/";
$companySite = "http://sushi-pizza-vip.ru";
$company = $companyName = "sushi-pizza-vip";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$allProcutsId = [];

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$categories = parcing($inParceUrl);

html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);
foreach ($categories as $value) {
    $i = 0;
    do {
        $addProduct = createProducts($outXml, $companySite . $value['href'] . '?p='. ++$i, $value['id'], $companySite);
    } while ($addProduct);

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

    $menus = $html_cat->find('.shop-menu', 0);
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

    $prods_parcing = $html_prod->find('ul.product');
    if (!$prods_parcing) {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('.dcart-add-to-cart-btn', 0);
        if ($idData) {
            $pid = explode('id=', $idData->href);
            $pid = html_clear_var($pid[1], 'int');
        }

        $nameData = $product->find('.product_img img', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->getAttribute('title'));
            if ($nameData->src) {
                $image = $companySite . '/' . $nameData->src;
            }
        }

        $priceData = $product->find('.product_price', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        $descData = $product->find('.tov_atr', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        if (array_key_exists((string)$pid, $GLOBALS['allProcutsId'])) {
            return false;
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

        $GLOBALS['allProcutsId'][(string)$pid] = $name;
    }

    $html_prod->clear();

    return true;
}


