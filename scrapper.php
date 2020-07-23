<?php

require 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;
use League\Csv\Writer;

/**
 * Scrape Etsy search result content and output the result to csv files
 */
$options = getopt('s:p:');

$search = $options['s'] ?? 'art';
$pagesToGet = $options['p'] ?? 1;

echo "\nGetting Etsy search result for $search for $pagesToGet page\n";

$items = [];
$shops = [];

for ($page = 1; $page <= $pagesToGet; $page++) {
    echo "\nPage $page\n";
    $crawler = new Crawler(getContent('https://www.etsy.com/search?q=' . $search . '&ref=pagination&page=' . $page));

    $crawler
        ->filter('div.v2-listing-card__info')
        ->each(function (Crawler $node, $i) use (&$items, &$shops) {
            echo "i";

            $title = $node->filter('h3.text-body')->eq(0)->text('');
            $shop = $node->filter('div.v2-listing-card__shop p.text-gray-lighter')->eq(0)->text('');
            $stars = $node->filter('div.v2-listing-card__shop span.v2-listing-card__rating span.screen-reader-only')->eq(0)->text('');
            $rating = $node->filter('div.v2-listing-card__shop span.v2-listing-card__rating span.screen-reader-only')->eq(1)->text('');
            $currency = $node->filter('span.n-listing-card__price span.currency-symbol')->eq(0)->text();
            $price = $node->filter('span.n-listing-card__price span.currency-value')->eq(0)->text();

            $items[] = ['title' => $title, 'shop' => $shop, 'stars' => $stars, 'rating' => $rating, 'currency' => $currency, 'price' => $price];

            if (!isset($shops[$shop])) {
                $shops[$shop] = getShopDetails($shop);
            }
        });
}

echo "\n\nDone!\n\n";

// Create csv for items
$csv = Writer::createFromString('');
$csv->insertOne(['title', 'shop', 'stars', 'rating', 'currency', 'price']);
$csv->insertAll($items);
$fh = fopen('items.csv', 'w');
fwrite($fh, $csv->getContent());

// Creaste csv for shops
$csv = Writer::createFromString('');
$csv->insertOne(['shop', 'since', 'sales', 'stars', 'owner']);
$csv->insertAll($shops);
$fh = fopen('stores.csv', 'w');
fwrite($fh, $csv->getContent());


/**
 * Returns html string
 * Implements file caching mechanism
 * 
 *  @param string $search
 *  @param int $page
 *  @return string
 */
function getContent($url)
{    
    $file = './cache/' . sha1($url) . '.html';

    if (
        file_exists($file)
        && time() - filemtime($file) <= 86400
    ) {
        return file_get_contents($file);
    }

    // get the html from web and save it to cache
    $html = file_get_contents($url);
    $fh = fopen($file, 'w');
    fwrite($fh, $html);
    fclose($fh);

    return $html;
}

/**
 * Returns shop information
 * 
 *  @param string $shop
 *  @return array
 */
function getShopDetails(string $shop)
{
    echo 's';
    $shopInfo = ['since' => '', 'sales' => '', 'stars' => '', 'owner' => ''];

    $crawler = new Crawler(getContent('https://www.etsy.com/shop/' . $shop));

    $name = $crawler->filter('div.shop-name-and-title-container h1')->eq(0)->text('');
    $since = $crawler->filter('div.shop-info span.etsy-since')->eq(0)->text('');
    $sales = $crawler->filter('div.shop-info div.display-flex-lg span')->eq(0)->text('');
    $stars = $crawler->filter('a.reviews-link-shop-info span.stars-svg  span.screen-reader-only')->eq(0)->text('');
    $owner = $crawler->filter('div.shop-owner div div.img-container a p')->eq(0)->text('');

    if ('' != $name) {
        $shopInfo = ['shop' => $shop, 'since' => $since, 'sales' => $sales, 'stars' => $stars, 'owner' => $owner];
    }

    return $shopInfo;
}
