<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Queues\BizPlus\BizPlus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBizPlus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private BizPlus $bizPlus;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BizPlus $bizPlus)
    {
        $this->bizPlus = $bizPlus;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->bizPlus->sendKakao();
    }
}
