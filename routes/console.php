<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('documents:purge-tmp-uploads')->hourly();
Schedule::command('documents:purge-expired-trash')->dailyAt('02:00');
