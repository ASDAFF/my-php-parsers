<?php
/**
 * User: gaalferov
 * Date: 09.02.16
 * Time: 11:00
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://novapizza.ru/";
$companySite = "http://novapizza.ru";
$company = $companyName = "novapizza";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

if ($argv[1] != 'loc') {
    $outPath = OUTPUT_PATH;
} else {
    $outPath = '';
}

$outXml = $outPath . $company . '.' . 'xml';

@unlink($outXml);

$categories = parcing($inParceUrl);

writeStartFile($outXml, $categories, $companySite, $company, $companyName);

foreach ($categories as $value) {
    createProducts($outXml, $value['href'], $value['id']);
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

    $menu = $html_cat->find('.products .product-category');
    if (!$menu) {
        return false;
    }

    foreach ($menu as $value) {

        $a = $value->find('a', 0);
        $href = clear_var($a->href);
        $name = clear_var($a->plaintext);
        $name = mb_strtolower($name, 'UTF-8');

        if ($name == '') {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => null,
            'name' => $name,
            'href' => $href,
        ];

        ++$catID;
    }

    $html_cat->clear();
    return $categories;

}

function createProducts($outXml, $urlCatParce, $catId)
{

    $result = getSaitData($urlCatParce);
    $html_prod = new simple_html_dom();
    $html_prod->load($result);

    $prods_parcing = $html_prod->find('ul.products', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return;
    }

    foreach ($prods_parcing as $product) {

        $id = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('.add_to_cart_button', 0);
        if ($idData) {
            $id = $idData->getAttribute('data-product_id');
            $id = preg_replace("/[^0-9]/", '', $id);
            $id = clear_var($id);
        }

        $name = null;
        $nameData = $product->find('a h3', 0);
        if ($nameData) {
            $name = clear_var($nameData->plaintext);
            $name = mb_strtolower($name, 'UTF-8');
        }

        $priceData = $product->find('a .price', 0);
        if ($priceData) {
            $price = str_replace("руб.", '', $priceData->plaintext);
            $price = clear_var($price, 'double');
        }

        if (empty($price) || empty($id) || empty($name)) {
            continue;
        }

        $aUrlData = $product->find('a', 0);
        if ($aUrlData) {
            $aUrl = clear_var($aUrlData->href);
        }

        $result = getSaitData($aUrl);
        $html_article = new simple_html_dom();
        $html_article->load($result);

        $descriptionData = $html_article->find('div.summary', 0);
        if ($descriptionData) {
            $divs = $descriptionData->children();
            foreach ($divs as $des) {
                $desprop = $des->getAttribute('itemprop');
                if ($desprop == 'description') {
                    $description = clear_var($des->plaintext);
                }
            }
        }

        $imgData = $html_article->find('div.images a', 0);
        if ($imgData) {
            $image = clear_var($imgData->href);
        }

        if (empty($image)) {
            continue;
        }

        $good = [
            'id' => $id,
            'available' => true,
            'url' => $aUrl,
            'image' => $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        writeProductInFile($outXml, $catId, $good);
    }
    $html_prod->clear();
}

function getSaitData($url)
{

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0');
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
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
        $available = (isset($product['available'])) ? 'true' : 'false';
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
            "available" => $available,
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