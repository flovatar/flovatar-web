<?php

namespace App\Console\Commands;

use App\Jobs\SendPack;
use App\Models\Pack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendPacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-packs {pack} {file} {--batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all the packs';

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
        $packName = ($this->argument('pack') == 'deluxe') ? 'Genesis Deluxe Pack' : 'Genesis Standard Pack';
        $packName = ($this->argument('pack') == 'flobit') ? 'Flobit Pack' : $packName;
        $packName = ($this->argument('pack') == 'terrakit') ? 'TerraKit Pack' : $packName;
        $packName = ($this->argument('pack') == 'dust') ? 'Dust Flobit Pack' : $packName;

        info('Sending '.$packName);
        $this->info('Sending '.$packName);

        $file = Storage::disk('localstorage')->get('airdrop/'.$this->argument('file'));

        info('Reading file '.$this->argument('file'));
        $this->info('Reading file '.$this->argument('file'));

        $addressArray = explode("\n", $file);

        foreach($addressArray as $line){

            $quantity = 1;
            if($this->option('batch')){
                $lineArray = explode(",", $line);
                $address = $lineArray[0];
                $quantity = $lineArray[1];
            } else {
                $address = $line;
            }

            for($i=0; $i < $quantity; $i++){
                $pack = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName($packName)->first();
                $pack->update([
                    'user_id' => 1,
                    'assigned_at' => Carbon::now()
                ]);

                if($pack){
                    info('Sending pack '.$pack->flow_id.' to '.$address);
                    $this->info('Sending pack '.$pack->flow_id.' to '.$address);
                    SendPack::dispatch($pack, $address);
                    $output = $output.$pack->flow_id.' - '.$address."\n";
                }
            }
        }

        Storage::disk('localstorage')->put('airdrop/archive/Completed-'.$this->argument('file'), $output);

        info('Finished sending '.$packName);
        $this->info('Finished sending '.$packName);



        return true;
    }
}
