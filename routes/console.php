<?php

use Illuminate\Support\Facades\Schedule;

// Operating-day + already-done + once-per-day guards live inside the commands.
Schedule::command('hub:remind-register')->dailyAt('12:00')->timezone('Europe/London');
Schedule::command('hub:remind-end-of-day')->dailyAt('14:30')->timezone('Europe/London');
Schedule::command('hub:run-automations')->hourly()->withoutOverlapping();
