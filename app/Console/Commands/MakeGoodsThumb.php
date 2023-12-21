<?php

namespace App\Console\Commands;

use App\Services\BatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MakeGoodsThumb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Scheduler:makeGoodsThumb';

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
        $partnerDirs = Storage::directories('partner');
        BatchService::makeThumbnailBatch($partnerDirs, [150, 400]);

        return Command::SUCCESS;
    }
}
