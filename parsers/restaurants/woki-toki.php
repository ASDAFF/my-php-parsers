<?php
/**
 * User: gaalferov
 * Date: 18.02.16
 * Time: 15:00
 */
require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://woki-toki.com/";
$companySite = "http://woki-toki.com";
$company = "woki-toki";
$companyName = null;
if ($companyName == null) {
    $companyName = $company;
}

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

$page = new simple_html_dom();
$page->load(iconv('WINDOWS-1251', 'UTF-8', file_get_contents($inParceUrl)));

$categories = parcing($page);
unlink($outXml);

writeStartFile($outXml, $categories, $companySite, $company, $companyName);

foreach ($categories as $value) {
    createProducts($outXml, $value['href'], $value['id'], $companySite, $page);
}
writeEndFile($outXml);
$page->clear();


function parcing($html_cat)
{

    $catID = 1;
    $categories = [];

    $menu = $html_cat->find('#menutop ul', 0);
    if ($menu) {
        $menu = $menu->children();
    }

    foreach ($menu as $value) {

        $a = $value->find('a', 0);
        $href = $a->getAttribute('rel');
        $name = $a->getAttribute('title');
        $name = trim($name);

        $name = mb_strtolower($name, 'UTF-8');

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

    return $categories;

}

function createProducts($outXml, $urlCatParce, $catId, $companySite, $html_prod)
{

    $prods_parcing = $html_prod->find('#products .'.$urlCatParce);
    if (!$prods_parcing) {
        return;
    }

    foreach ($prods_parcing as $product) {

        $id = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('a.ajaxcart', 0);
        if ($idData) {
            $id = $idData->getAttribute('id');
            $id = str_replace('tocart_', '', $id);
            $id = clear_var($id);

        }

        $nameData = $product->find('.desc .name', 0);
        if ($nameData) {
            $name = clear_var($nameData->plaintext);
            $name = mb_strtolower($name, 'UTF-8');
        }

        $priceData = $product->find('#sprice'.$id, 0);
        if ($priceData) {
            $price = clear_var($priceData->value, 'double');
        }

        if (empty($price) || empty($id) || empty($name)) {
            continue;
        }

        $descriptionData = $product->find('.desc .description', 0);
        if ($descriptionData) {
            $description = clear_var($descriptionData->plaintext);
        }

        $imgData = $product->find('.img a.cloud-zoom', 0);
        if ($imgData) {
            $image = substr($imgData->href, 1);
            $image = clear_var($companySite.$image);
        }

        if (empty($image)) {
            continue;
        }

        $good = [
            'id' => $id,
            'available' => true,
            'url' => $companySite,
            'image' => $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        writeProductInFile($outXml, $catId, $good);
    }
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