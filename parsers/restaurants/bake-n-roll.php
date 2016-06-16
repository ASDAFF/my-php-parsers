<?php
/**
 * User: gaalferov
 * Date: 01.05.16
 * Time: 16:10
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://bake-n-roll.ru/";
$companySite = "http://bake-n-roll.ru";
$company = $companyName = "bake-n-roll";

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
    createProducts($outXml, $value['href'], $value['id'], $companySite, true);
}
html_writeEndFile($outXml);

//Удаляем пустые категорие, дублирующиеся товары, выставляем корректно id категории
$unique = restoreXML($outXml);
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

    $result = html_getSaitData($url);
    $html_cat = new simple_html_dom();
    $html_cat->load($result);

    $menus = $html_cat->find('.product-categories li');
    if (!$menus) {
        return false;
    }

    foreach ($menus as $menu) {

        $value = $menu->find('a', 0);

        $href = html_clear_var($value->href);
        $name = $value->plaintext;
        $name = mb_strtolower(html_clear_var($name), 'UTF-8');

        if ($name == '') {
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

function checkpagination($outXml, $html, $catId, $companySite)
{
    $prods_pagination_parcing = $html->find('.page-numbers a');
    if ($prods_pagination_parcing) {
        foreach ($prods_pagination_parcing as $pagination) {
            $pname = $pagination->plaintext;
            $phref = $pagination->href;
            if ((in_array($pname, ["←","→","&larr;","&rarr;","1"]) == false) && ($phref))
                createProducts($outXml, $phref, $catId, $companySite);
        }
    }
}

function createProducts($outXml, $urlCatParce, $catId, $companySite, $pagination = false)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.products', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = $jsonData = null;

        $idData = $product->find('.add_to_cart_button', 0);
        if ($idData) {
            $pid = $idData->getAttribute('data-product_id');
        }

        $nameData = $product->find('h3', 0);
        if ($nameData) {
            $name = $nameData->plaintext;
        }

        $urlData = $product->find('a', 0);
        if ($urlData) {
            $aUrl = $urlData->href;
        }

        $priceData = $product->find('.amount', 0);
        if ($priceData) {
            $price = str_replace(['руб.', ' '], '', $priceData->plaintext);
            $price = html_clear_var($price, 'int');

        }

        if (empty($pid) || empty($name) || empty($aUrl) || empty($price))
            continue;

        $html_prod_details = new simple_html_dom();
        $html_prod_details->load(html_getSaitData($aUrl));

        $imgData = $html_prod_details->find('.woocommerce-main-image', 0);
        if ($imgData) {
            $image = $imgData->href;
        }

        $descData = $html_prod_details->find('.entry-summary div');
        if ($descData) {
            foreach ($descData as $desc) {
                $itemprop = $desc->getAttribute('itemprop');
                if (isset($itemprop) && ($itemprop == 'description'))
                    $description = html_clear_var($desc->plaintext);
            }
        }

        if (empty($image)) {
            continue;
        }

        $good = [
            'id' => $pid,
            'available' => true,
            'url' => $aUrl,
            'image' => $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        html_writeProductInFile($outXml, $catId, $good);
    }

    if ($pagination)
        checkpagination($outXml, $html_prod, $catId, $companySite);

    $html_prod->clear();

    return true;
}

function restoreXML($filename)
{
    $categories = $offers = $params = [];

    $xmlFile = simplexml_load_file($filename);
    $xml_categories = $xmlFile->xpath('//categories')[0];
    $xml_offers = $xmlFile->xpath('//offers')[0];

    foreach ($xml_categories as $xml_category) {
        $cat_attributes = $xml_category->attributes();
        $id = (int)$cat_attributes['id']; //id категории
        $parentId = (int)$cat_attributes['parentId']; //id верхней категории
        $name = (string)$xml_category;
        $categories[$id] = [
            "name" => $name,
            "id" => $id,
            "parentId" => $parentId,
            "products_count" => 0
        ];
    }

    foreach ($xml_offers as $xml_offer) {
        $offer_attributes = $xml_offer->attributes();
        $id = (int)$offer_attributes['id'];
        $available = (string)$offer_attributes['available'];
        $url = (string)$xml_offer->url;
        $currencyId = (string)$xml_offer->currencyId;
        $categoryId = (int)$xml_offer->categoryId;
        $picture = (string)$xml_offer->picture;
        $name = (string)$xml_offer->name;
        $description = (string)$xml_offer->description;
        $price = (double)$xml_offer->price;

        //Проверка уникальности id товара
        if (array_key_exists($id, $offers)) {
            $_offer_parent = $categories[$categoryId]['parentId'];
            $_offer_catid = $offers[$id]['categoryId'];

            if ($_offer_parent == $_offer_catid) {
                //Удаляем товар из верхней категории, если он есть в подкатегории
                unset($offers[$id]);
                $categories[$_offer_catid]['products_count']--;
            } else {
                continue;
            }
        }

        $offers[$id] = [
            "id" => $id,
            "available" => $available == 'false' ? false : true,
            "url" => $url,
            "currencyId" => $currencyId,
            "categoryId" => $categoryId,
            "image" => $picture,
            "name" => $name,
            "description" => $description,
            "price" => $price
        ];

        $categories[$categoryId]['products_count']++;
    }

    //Удаляем пустые категории
    foreach ($categories as $category) {
        if ($category['products_count'] < 1 && $category['parentId'] != 0)
            unset($categories[$category['id']]);
    }


    return ['categories' => $categories, 'offers' => $offers];
}