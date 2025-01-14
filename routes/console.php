<?php

use App\Jobs\UpdateAllAccountJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

//Artisan::command('inspire', function () {
//    $this->comment(Inspiring::quote());
//})->purpose('Display an inspiring quote')->hourly();

//Schedule::command('distribute:sales-bonus')->weekly();
Schedule::command('update:exchange-rate')
    ->timezone('Asia/Kuala_Lumpur')
    ->at('10:00');
Schedule::command('update:vnd-exchange-api-key')->weekly();
Schedule::call(function () {
    dispatch(new UpdateAllAccountJob());
})->timezone('Asia/Kuala_Lumpur')->at('8:00');
