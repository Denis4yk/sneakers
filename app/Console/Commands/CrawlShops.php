<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use League\Csv\Writer;
use PHPHtmlParser\Dom;

class CrawlShops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:shops';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl specified shops.';

    /**
     * Execute the console command.
     *
     * @param \GuzzleHttp\Client $client
     * @param \PHPHtmlParser\Dom $parser
     *
     * @return mixed
     */
    public function handle(Client $client, Dom $parser)
    {
        $eversizePrices = $parser->load(
            $client->get('https://www.everysize.com/adidas-originals-superstar-foundation-sneaker-c77154.html')
                   ->getBody(true)
        )->find('#shops-scroller .info .price');

        $sneakerjagersPircesRaw = $parser->load(
            $client->get('https://www.sneakerjagers.com/de/de_de/sneaker/adidas-superstar-junior-sneakers/76978')
                   ->getBody(true)
        )->find('.sneaker-content .container .column')[1]->find('.column.is-1.is-paddingless.has-text-right.is-size-7 span');
        $sneakerjagersPirces    = collect($sneakerjagersPircesRaw)->reject(function ($item) {
            return str_contains($item->innerHtml(), '<del>');
        })->values();

        //$sneakers123Pirces = $parser->load(
        //    $client->get('https://sneakers123.com/adidas-superstar-foundation-c77154')
        //           ->getBody(true)
        //)
        //->find('#shops-list .shop-price-section');


        $results = [
            'brand' => 'adidas',
            'sku'   => 'C77154',
            'shops' => [
                'everysize'     => [
                    'lowest'     => collect($eversizePrices->toArray())->map(function ($item) {
                        return $this->parse_float($item->text);
                    })->min(),
                    'shopsCount' => $eversizePrices->count(),
                ],
                'sneakers123'   => [
                    'lowest'     => '',
                    'shopsCount' => '',
                ],
                'sneakerjagers' => [
                    'lowest'     =>
                        $sneakerjagersPirces->map(function ($item) {
                            $some = 'else';
                            $else = $some;
                            return $this->parse_float($item->text);
                        })->min(),
                    'shopsCount' => $sneakerjagersPirces->count(),
                ],
            ],

        ];

        $writer = Writer::createFromPath(storage_path('file.csv'), 'w+');
        $writer->insertAll([
                ['', '', 'Sneakers123', 'Sneakers123', 'everysize.com', 'everysize.com', 'sneakerjagers.com', 'sneakerjagers.com'],
                ['Brand', 'SKU', 'Lowest Price', 'Available Shops', 'Lowest Price', 'Available Shops', 'Lowest Price', 'Available Shops'],
                ['adidas', 'C77154',
                 $results['shops']['sneakers123']['lowest'],
                 $results['shops']['sneakers123']['shopsCount'],
                 $results['shops']['everysize']['lowest'],
                 $results['shops']['everysize']['shopsCount'],
                 $results['shops']['sneakerjagers']['lowest'],
                 $results['shops']['sneakerjagers']['shopsCount'],
                ],
            ]
        );

        $some = 'else';
        $else = $some;
        dd('hi');
    }


    /**
     * THIS SHOULD BE REMOVED TO helpers.php, HAD NO TIME TO SET IT UP
     *
     * Parse float number string into a float.
     *
     * Also works with prices.
     *
     * @param $numstr
     *
     * @return float
     * @see \Tests\Feature\HelpersTest@testParseFloat
     *
     */
    function parse_float($numstr): float
    {
        // convert "," to "."
        $numstr = str_replace(',', '.', $numstr);
        // remove all but numbers "."
        $numstr = preg_replace("/[^0-9\.]/", "", $numstr);

        // check for cents
        $hasCents = (substr($numstr, -3, 1) == '.');
        // remove all seperators
        $numstr = str_replace('.', '', $numstr);
        // insert cent seperator
        if ($hasCents) {
            $numstr = substr($numstr, 0, -2) . '.' . substr($numstr, -2);
        }
        // return float
        return (float)$numstr;
    }
}
