<?php

namespace App\Console\Commands;

use App\Jobs\SendDustToFlovatar as SendDustToFlovatarJob;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendDustToFlovatar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-dust-to-flovatar {amount} {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Dust';

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
        $output = "";

        $amount = $this->argument('amount');
        $id = $this->argument('id');

        info('Sending '.$amount.' DUST to '.$id);
        $this->info('Sending '.$amount.' DUST to '.$id);
        SendDustToFlovatarJob::dispatch($amount, $id);

        info('Finished sending DUST '.$amount);
        $this->info('Finished sending DUST '.$amount);

        return true;
    }
}
