<?php

namespace App\Jobs;

use App\Services\Market\FetchMarket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshMarket implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 40;

    public function handle(FetchMarket $fetch): void
    {
        $fetch->run();
    }
}
