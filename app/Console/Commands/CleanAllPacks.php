<?php

namespace App\Console\Commands;

use App\Jobs\CleanAllPacks as CleanAllPacksJob;
use Illuminate\Console\Command;
use App\Models\Pack;
use Carbon\Carbon;

class CleanAllPacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:clean-all-packs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans all the packs';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        info('Check Packs');

        CleanAllPacksJob::dispatch();
        $this->info('All done!');
        return true;

    }
}
