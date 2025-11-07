<?php

namespace App\Console\Commands;

use App\Jobs\CheckAccountLink as CheckAccountLinkJob;
use App\Models\Pack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CheckAccountLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:check-link {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check account links';

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
        $this->info('Checking addresses');

        $file = Storage::disk('localstorage')->get('airdrop/'.$this->argument('file'));

        info('Reading file '.$this->argument('file'));
        $this->info('Reading file '.$this->argument('file'));

        $addressArray = explode("\n", $file);

        foreach($addressArray as $address){

            info('Checking address '.$address);
            $this->info('Checking address '.$address);
            CheckAccountLinkJob::dispatch($address);
        }

        Storage::disk('localstorage')->put('airdrop/archive/Completed-'.$this->argument('file'), $output);

        info('Finished checking');
        $this->info('Finished checking');



        return true;
    }
}
