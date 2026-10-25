<?php

use App\Models\Secret;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [Secret::class]])
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();
