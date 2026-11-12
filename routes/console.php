<?php

use App\Services\ForexAutoMonitorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('forex:monitor {--force : Jalankan scan meski auto_scan_enabled nonaktif}', function (ForexAutoMonitorService $monitor) {
    $res = $monitor->run((bool) $this->option('force'));
    $this->info(json_encode($res));
})->purpose('Scan peluang >55% (Scanner + Monte Carlo) & pantau posisi SL/TP ke Telegram');

Schedule::command('forex:monitor')->everyFiveMinutes()->withoutOverlapping();
