<?php
/**
 * User: gaalferov
 * Description: All products and categories in JSON
 * Date: 29.03.16
 * Time: 15:24
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://tai-sushi.ru/category/rolly-zapiechiennyie-rolly";
$companySite = "http://tai-sushi.ru";
$company = $companyName = "tai-sushi";

$currencies = $json = $categories = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$count = preg_match_all("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is", html_getSaitData($inParceUrl), $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

foreach ($matches as $match) {

    $pos = stripos($match[0][0], 'application/json');
    if ($pos !== false) {
        $json = $match[1][0];
    }
}

if ($json) {
    $json = json_decode(json_decode($json), true);
    if (is_array($json)) {

        foreach ($json['data']['categories'] as $category) {
            $categories[] = [
                'id' => $category['id'],
                'parentId' => $category['parent_id'],
                'name' => $category['name'],
                'href' => $category['slug'],
            ];
        }

        html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);

        foreach ($json['data']['products'] as $product) {

            $good = [
                'id' => $product['id'],
                'available' => true,
                'url' => $product['slug'],
                'image' => $product['images']['large'],
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['params'][0]['price'],
            ];

            html_writeProductInFile($outXml, $product['category_id'], $good);
        }

        html_writeEndFile($outXml);
    }
}