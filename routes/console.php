<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

//Artisan::command('inspire', function () {
//    $this->comment(Inspiring::quote());
//})->purpose('Display an inspiring quote')->hourly();

//Schedule::command('distribute:sales-bonus')->weekly();
Schedule::command('update:exchange-rate')->dailyAt('08:00');
Schedule::command('update:vnd-exchange-api-key')->weekly();
