<?php

namespace App\Console\Commands;

use App\Jobs\SendDust as SendDustJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SendDustBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-dust-batch {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Dust in batch';

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


        $file = Storage::disk('localstorage')->get('airdrop/'.$this->argument('file'));

        info('Reading file '.$this->argument('file'));
        $this->info('Reading file '.$this->argument('file'));

        $addressArray = explode("\n", $file);


        foreach($addressArray as $line){


            $lineArray = explode(",", $line);
            $address = $lineArray[0];
            $amount = $lineArray[1];


            info('Sending '.$amount.' DUST to '.$address);
            $this->info('Sending '.$amount.' DUST to '.$address);
            SendDustJob::dispatch($amount, $address);
            $output .= $amount.' - '.$address."\n";
        }

        Storage::disk('localstorage')->put('airdrop/archive/Completed-'.$this->argument('file'), $output);

        info('Finished sending Dust');
        $this->info('Finished sending Dust');



        return true;
    }
}
