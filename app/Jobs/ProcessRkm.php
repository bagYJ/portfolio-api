<?php

namespace App\Jobs;

use App\Queues\Rkm\Rkm;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRkm implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private Rkm $rkm;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Rkm $rkm)
    {
        $this->rkm = $rkm;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws GuzzleException
     */
    public function handle()
    {
        $this->rkm->init();
    }
}
