<?php

namespace App\Console\Commands;

use App\Services\BatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MakeShopThumb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Scheduler:makeShopThumb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $partnerDirs = Storage::directories('shop');
        foreach ($partnerDirs as $partnerDir) {
            $shopDirs = Storage::directories(sprintf('%s', $partnerDir));

            BatchService::makeThumbnailBatch($shopDirs, [400]);
        }

        return Command::SUCCESS;
    }
}
