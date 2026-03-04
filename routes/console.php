<?php

use Illuminate\Support\Facades\Schedule;

// Scraper health check a cada 15 min
// Schedule::command('scrapers:health-check')->everyFifteenMinutes();

// Limpar logs antigos (> 30 dias)
// Schedule::command('logs:cleanup --days=30')->daily();
