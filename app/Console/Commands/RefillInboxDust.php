<?php

namespace App\Console\Commands;

use App\Jobs\RefillInboxDust as RefillInboxDustJob;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RefillInboxDust extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refill-inbox-dust {amount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refills the Flobit Upgrader';

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

        $amount = $this->argument('amount');

        info('Refilling '.$amount);
        $this->info('Refilling '.$amount);

        RefillInboxDustJob::dispatch($amount);

        info('Finished refilling '.$amount);
        $this->info('Finished refilling '.$amount);



        return true;
    }
}
