<?php
/**
 * User: gaalferov
 * Date: 01.05.16
 * Time: 12:41
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://azbukabuketa.ru/";
$companySite = "http://azbukabuketa.ru";
$company = $companyName = "azbukabuketa";

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
    createProducts($outXml, $companySite . $value['href'], $value['id'], $companySite, true);
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

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menu = $html_cat->find('#LeftMenu li');
    if (!$menu) {
        return false;
    }

    foreach ($menu as $value) {

        $a = $value->find('a', 0);
        $href = html_clear_var($a->href);
        $name = $a->plaintext;
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
    $prods_pagination_parcing = $html->find('#Pager', 0);
    if ($prods_pagination_parcing) {
        foreach ($prods_pagination_parcing->find('a') as $pagination) {
            $pname = $pagination->plaintext;
            $phref = $pagination->href;
            if (in_array($pname, ["←","→","1"]) == false)
                createProducts($outXml, $companySite . $phref, $catId, $companySite);
        }
    }
}

function createProducts($outXml, $urlCatParce, $catId, $companySite, $pagination = false)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.goodItems', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('.js-order_button', 0);
        if ($idData) {
            $pid = explode('/',$idData->href)[3];
            $pid = html_clear_var($pid, 'int');
        }

        $urlData = $product->find('div.goodItemImage a', 0);
        if ($urlData) {
            $aUrl = html_clear_var($companySite . $urlData->href);
        }

        $nameData = $product->find('p a', 0);
        if ($nameData) {
            $name = mb_strtolower(html_clear_var($nameData->plaintext), 'UTF-8');
        }

        $priceData = $product->find('div.goodItemDetail', 0);
        if ($priceData) {
            $price = str_replace(["руб.", "&nbsp;", " "], '', $priceData->plaintext);
            $price = html_clear_var($price, 'double');
        }

        if (empty($price) || empty($pid) || empty($name)) {
            continue;
        }

        $html_article = new simple_html_dom();
        $html_article->load(html_getSaitData($aUrl));

        $descriptionData = $html_article->find('.descr p', 0);
        if ($descriptionData) {
            $description = html_clear_var($descriptionData->plaintext);
        }

        $imgData = $html_article->find('#mainImageLink', 0);
        if ($imgData) {
            $image[] = html_clear_var($companySite . $imgData->href);
        }

        $imgSubData = $html_article->find('#subImage a');
        if ($imgSubData) {
            foreach ($imgSubData as $_image) {
                $_image = $companySite . $_image->href;

                if (array_search($_image, $image) === false)
                    $image[] = $_image;
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