<?php
/**
 * User: gaalferov
 * Date: 13.03.16
 * Time: 13:41
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://sushi-city116.ru/";
$companySite = "http://sushi-city116.ru";
$company = $companyName = "sushi-city116";

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
    createProducts($outXml, $companySite . $value['href'] . '?limit=0', $value['id'], $companySite);
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

    $menus = $html_cat->find('.menu-block-wrapper .menu', 0);
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

        if ($name == '') {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => null,
            'name' => $name,
            'href' => $href
        ];

        $childs = $menu->find('ul.menu', 0);
        if ($childs) {
            $childs = $childs->children();
        }

        if (is_array($childs) && (count($childs) > 1)) {
            $ccatID = $catID;

            foreach ($childs as $child) {
                ++$ccatID;

                $cvalue = $child->find('a', 0);

                $chref = html_clear_var($cvalue->href);
                $cname = $cvalue->plaintext;
                $cname = mb_strtolower(html_clear_var($cname), 'UTF-8');

                $categories[] = [
                    'id' => $ccatID,
                    'parentId' => $catID,
                    'name' => $cname,
                    'href' => $chref
                ];
            }

            $catID = $ccatID;
        }

        ++$catID;

    }

    $html_cat->clear();
    return $categories;
}

function createProducts($outXml, $urlCatParce, $catId, $companySite)
{

    $result = html_getSaitData($urlCatParce);
    $html_prod = new simple_html_dom();
    $html_prod->load($result);

    $prods_parcing = $html_prod->find('.view-content div.product');
    if (!$prods_parcing) {
        return false;
    }

    $id = 0;

    foreach ($prods_parcing as $product) {

        $name = $aUrl = $price = $description = $image = null;

        $id++;

        $pid = $catId . $id;

        $nameData = $product->find('.title', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            $name = mb_strtolower($name, 'UTF-8');
        }

        $weightData = $product->find('.weight', 0);
        if ($weightData) {
            $weigh = html_clear_var($weightData->plaintext);
            $weigh = mb_strtolower($weigh, 'UTF-8');
            if ($weigh)
                $name = $name . ' (' . $weigh . ')';
        }

        $priceData = $product->find('div.price', 0);
        if ($priceData) {
            $price = str_replace(" ", '', $priceData->plaintext);
            $price = str_replace("&nbsp;", '', $price);
            $price = html_clear_var($price, 'double');
        }

        if (empty($price) || empty($id) || empty($name)) {
            continue;
        }

        $descriptionData = $product->find('.desc', 0);
        if ($descriptionData) {
            $description = html_clear_var($descriptionData->plaintext);
        }

        $imgData = $product->find('.image img', 0);
        if ($imgData) {
            $image = html_clear_var($imgData->getAttribute('src'));
        }

        if (empty($image))
            continue;

        $good = [
            'id' => $pid,
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