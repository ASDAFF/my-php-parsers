<?php
/**
 * User: gaalferov
 * Date: 02.03.16
 * Time: 16:21
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');
header('Content-type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Moscow');


$inParceUrl = "http://hurryfox.ru/";
$companySite = "http://hurryfox.ru";
$company = $companyName = "hurryfox";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$categories = parcing($inParceUrl);
unlink($outXml);

writeStartFile($outXml, $categories, $companySite, $company, $companyName);
foreach ($categories as $value) {
    createProducts($outXml, $companySite . $value['href'] . '?PAGEN_1=1&AJAX=Y', $value['id'], $companySite, true);
}
writeEndFile($outXml);

//Удаляем пустые категорие, дублирующиеся товары, выставляем корректно id категории
$unique = restoreXML($outXml);
if ($unique) {
    @unlink($outXml);
    writeStartFile($outXml, $unique['categories'], $companySite, $company, $companyName);
    foreach ($unique['offers'] as $offer) {
        writeProductInFile($outXml, $offer['categoryId'], $offer);
    }
    writeEndFile($outXml);
}


function parcing($url)
{

    $catID = 1;
    $categories = [];

    $result = getSaitData($url);
    $html_cat = new simple_html_dom();
    $html_cat->load($result);

    $menus = $html_cat->find('.b-grid ul.b-main-menu');
    if (!$menus) {
        return false;
    }


    foreach ($menus as $menu) {

        $menu = $menu->children();
        if (!$menu) {
            continue;
        }

        foreach ($menu as $value) {

            $a = $value->find('a', 0);
            $href = clear_var($a->href);
            $name = $a->plaintext;
            $name = mb_strtolower(clear_var($name), 'UTF-8');

            if ($name == '') {
                continue;
            }

            if ($name != 'еще')
                $categories[] = [
                    'id' => $catID,
                    'parentId' => null,
                    'name' => $name,
                    'href' => $href
                ];

            $podmenuInstance = $value->find('ul', 0);
            if ($podmenuInstance) {
                $podmenuInstance = $podmenuInstance->children();
                $podcatID = $catID + 1;

                foreach ($podmenuInstance as $podmenuvalue) {

                    $podmenu_a = $podmenuvalue->find('a', 0);
                    $podmenu_href = clear_var($podmenu_a->href);
                    $podmenu_name = mb_strtolower(clear_var($podmenu_a->plaintext), 'UTF-8');

                    if ($podmenu_name == '' || $podmenu_name == 'еще') {
                        continue;
                    }

                    $categories[] = [
                        'id' => $podcatID,
                        'parentId' => null,
                        'name' => $podmenu_name,
                        'href' => $podmenu_href
                    ];

                    ++$podcatID;
                }
                $catID = $podcatID;
            } else {
                ++$catID;
            }
        }
    }
    $html_cat->clear();
    return $categories;
}

function checkpagination($outXml, $urlCatParce, $catId, $companySite, $fp_id)
{
    for ($i = 1; $i <= 100; ) {
        $urlCatParce = str_replace('1='.$i, '1='.++$i, $urlCatParce);
        $res = createProducts($outXml, $urlCatParce, $catId, $companySite, false, $fp_id);
        if (!$res)
            break;
    }
}

function createProducts($outXml, $urlCatParce, $catId, $companySite, $pagination = false, $fp_id = null)
{

    $result =  getSaitData($urlCatParce);
    $html_prod = new simple_html_dom();
    $html_prod->load($result);

    $prods_parcing = $html_prod->find('.b-offer');
    if (!$prods_parcing) {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $id = $name = $aUrl = $price = $description = $image = null;

        $id = html_clear_var($product->getAttribute('data-product-id'));

        //Если пагинация закончилась, выводятся товары с первой страницы. Если видим данный момент, заканчиваем перебор страниц
        if ($id == $fp_id)
            return false;

        $nameData = $product->find('span.b-offer__caption', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            $name = mb_strtolower($name, 'UTF-8');
        }

        $priceData = $product->find('span.b-offer__price', 0);
        if ($priceData) {
            $price = str_replace("руб.", '', $priceData->plaintext);
            $price = html_clear_var($price, 'double');
        }

        $descriptionData = $product->find('div.b-offer__description', 0);
        if ($descriptionData) {
            $description = html_clear_var($descriptionData->plaintext);
        }

        $imgData = $product->find('a.js-fancy-box-offer', 0);
        if ($imgData) {
            $image = html_clear_var($imgData->href);
        }

        if (empty($price) || empty($id) || empty($name) || empty($image)) {
            continue;
        } else {
            if (is_null($fp_id))
                $fp_id = $id;
        }

        $good = [
            'id' => $id,
            'available' => true,
            'url' => str_replace('&AJAX=Y', '', $urlCatParce),
            'image' => $companySite . $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        writeProductInFile($outXml, $catId, $good);
    }

    $html_prod->clear();

    if ($pagination)
        checkpagination($outXml, $urlCatParce, $catId, $companySite, $fp_id);

    return true;
}

function getSaitData($url)
{

    $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36';
    $timeout = 0;

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    curl_setopt($ch, CURLOPT_REFERER, $url);

    $content = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'error:' . curl_error($ch);
        $res = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        var_dump($res);
        exit('');
    }

    curl_close($ch);

    return $content;
}

function writeStartFile($outXml, $categories, $companySite, $company, $companyName)
{

    $fd = fopen($outXml, "a");

    $txt = "";
    $txt .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $txt .= "<yml_catalog date=\"" . date("Y-m-d H:i") . "\">\n";
    $txt .= "  <shop>\n";
    $txt .= "    <name>" . $companyName . "</name>\n";
    $txt .= "    <company>" . $company . "</company>\n";
    $txt .= "    <url>" . $companySite . "</url>\n";
    $txt .= "    <currencies>\n";
    foreach ($GLOBALS['currencies'] as $currency) {
        $txt .= "      <currency id=\"" . $currency['id'] . "\" rate=\"" . $currency['rate'] . "\"/>\n";
    }
    $txt .= "    </currencies>\n";
    $txt .= "    <categories>\n";
    foreach ($categories as $category) {
        if (!empty($category)) {
            $txt .= "      <category id='" . $category['id'] . "'" .
                (($category['parentId']) ? " parentId=\"" . $category['parentId'] . "\"" : "") . ">" .
                $category['name'] . "</category>\n";
        }
    }
    $txt .= "    </categories>\n";
    $txt .= "    <offers>\n";

    fwrite($fd, $txt);
    fclose($fd);
}

function writeProductInFile($outXml, $catId, $product)
{
    $txt = '';

    $fd = fopen($outXml, "a");

    if ($product) {
        $available = $product['available'] ? 'true' : 'false';
        $txt .= "      <offer id=\"" . $product['id'] . "\" available=\"" . $available . "\">\n";
        $txt .= "        <url><![CDATA[" . $product['url'] . "]]></url>\n";
        $txt .= "        <currencyId>RUR</currencyId>\n";
        $txt .= "        <categoryId>" . $catId . "</categoryId>\n";
        $txt .= "        <picture>" . $product['image'] . "</picture>\n";
        $txt .= "        <name><![CDATA[" . $product['name'] . "]]></name>\n";
        $txt .= "        <description><![CDATA[" . $product['description'] . "]]></description>\n";
        $txt .= "        <price>" . $product['price'] . "</price>\n";
        $txt .= "      </offer>\n";
    }

    fwrite($fd, $txt);
    fclose($fd);
}

function writeEndFile($outXml)
{
    $txt = '';

    $fd = fopen($outXml, "a");
    $txt .= "    </offers>\n";
    $txt .= "  </shop>\n";
    $txt .= "</yml_catalog>\n";
    fwrite($fd, $txt);
    fclose($fd);
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

function clear_var($var, $type = 'string')
{
    if ($type == 'int') $var = (int)$var;
    else if ($type == 'double') $var = (double)$var;
    else $var = (string)$var;

    $var = preg_replace('|[\s]+|s', ' ', $var);
    $var = str_replace('&quot;', '', $var);
    $var = str_replace("&amp;", '&', $var);
    $var = trim($var);
    return $var;
}