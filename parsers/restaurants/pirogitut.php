<?php
/**
 * User: gaalferov
 * Date: 29.03.16
 * Time: 15:24
 * ООО «Кулинаръ»
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://www.pirogitut.ru/";
$companySite = "http://www.pirogitut.ru";
$company = $companyName = "pirogitut";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$categories = parcing($inParceUrl.'12-plakuntosy');

html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);
foreach ($categories as $value) {
    createProducts($outXml, $value['href'], $value['id'], $companySite);
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
    
    $menus = $html_cat->find('menu[data-role=filtration]', 0);
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

        if ($name == '' || $name == 'новинки') {
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
   //print_r($categories) ; die;
    $html_cat->clear();
    return $categories;
}

function createProducts($outXml, $urlCatParce, $catId, $companySite)
{
 
    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('menu[data-type=quaternary]', 0);
     
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
        
    } else {
        return false;
    }

    foreach ($prods_parcing as $product) {
        
        $pid = $name = $aUrl = $price = $description = $image = null;
        
        $nameData = $product->find('a[data-role=name]', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            $name = mb_strtolower($name, 'UTF-8');
            $aUrl = html_clear_var($nameData->href);
        }
        //---------------- состав
        $html_prod_intro = new simple_html_dom();
        $html_prod_intro->load(html_getSaitData($aUrl));

        $descriptionData = $html_prod_intro->find('ul[aria-labelledby=tab-01]', 0);
        if ($descriptionData) {
            $description = html_clear_var($descriptionData->plaintext);
        }
        //---------------- фото
        $imgData = $product->find('img[itemprop=image]', 0);
        if ($imgData) {
            $image = html_clear_var($imgData->getAttribute('src'));
        }

        if (empty($image))
            continue;
        
        foreach($product->find('menu[role=listbox]', 0)->children() as $prod){
            $name_opt ="";
            $idData = $prod->find('.not_uniform', 0);
            if ($idData) {
                $pid = preg_replace('/[^0-9]/', '', $idData->getAttribute('value'));
                $pid = html_clear_var($pid, 'int');
            }
            $isNameOpt = $prod->find('label[data-type=article] small', 0);
            if ($isNameOpt) {
                if(html_clear_var($isNameOpt->plaintext) != 'цена') 
                    $name_opt =" ".html_clear_var($isNameOpt->plaintext);
            }
            $priceData = $prod->find('label[data-type=article]', 0);
            if ($priceData) {
                $price = $priceData->getAttribute('data-cost');
                $price = html_clear_var($price, 'double');
            }
            
            //---------------------------------------
            if (empty($price) || empty($pid) || empty($name) || empty($aUrl)) {
                continue;
            }

            

            $good = [
                'id' => $pid,
                'available' => true,
                'url' => $aUrl,
                'image' => $image,
                'name' => $name.$name_opt,
                'description' => $description,
                'price' => $price,
            ];
            
            html_writeProductInFile($outXml, $catId, $good);
        }

        

        

        
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
