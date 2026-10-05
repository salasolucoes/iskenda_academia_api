<?php

use App\Jobs\ExpiredCartCleanupJob;
use App\Jobs\LiveStartingSoonJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new LiveStartingSoonJob)->everyMinute();
Schedule::job(new ExpiredCartCleanupJob)->daily();
