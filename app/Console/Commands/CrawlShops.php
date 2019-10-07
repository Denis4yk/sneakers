<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
     * @return mixed
     */
    public function handle()
    {
        $some='else';
        $else=$some;
        dd('hi');
    }
}
