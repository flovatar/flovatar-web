<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Graffle webhook replaced by the built-in indexer (flow:stream daemon + REST catch-up).
        if (config('flovatar.indexerMode') === 'scheduled') {
            // No Supervisor daemons: poll the chain, process inline and drain the job queues every minute.
            $schedule->command('flow:catchup')->everyMinute()->withoutOverlapping(10)->runInBackground();
            $schedule->command('flow:process')->everyMinute()->withoutOverlapping(10)->runInBackground();
            $schedule->command('queue:work redis --queue=flow-events,default,images --stop-when-empty --max-time=50 --tries=3 --memory=192')
                ->everyMinute()->withoutOverlapping(10)->runInBackground();
        } else {
            $schedule->command('flow:catchup')->everyFiveMinutes()->withoutOverlapping(30)->runInBackground();
            $schedule->command('flow:process --queue')->everyMinute()->withoutOverlapping();
        }
        $schedule->command('flovatar:refresh-collectible-templates')->hourly();
        if (config('render.enabled')) {
            // safety net for the event hook: static items without any image (throttled, resumable); animated ones come from the browser renderer
            $schedule->command('render:images all --missing --static-only --limit=60 --sleep=500')
                ->hourlyAt(23)->withoutOverlapping(60)->runInBackground();
        }
        if (config('headless.enabled')) {
            // static headless site (R2): shells hourly (template lists/stats snapshots), full rebuild nightly as the
            // safety net for the per-item regeneration jobs (HeadlessRegen). Unchanged objects are skipped.
            $schedule->command('headless:build --shells --sitemap --quiet-puts')->hourlyAt(41)->withoutOverlapping(30)->runInBackground();
            $schedule->command('headless:build --quiet-puts')->dailyAt('03:30')->withoutOverlapping(120)->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
