<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prosec:recordar-disponibilidad')
    ->everyFifteenMinutes()
    ->between('09:00', '23:45')
    ->withoutOverlapping(15);
