<?php

namespace App\Console\Commands;

use App\Jobs\SendDustToFlovatars;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendDustOgBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-dust-og-bonus {--prod}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Dust to Flovatar';

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

        $maxBatch = 40;


        $count = 1;
        $subCount = 0;
        $maxMintable = 6000;
        $amounts = [];
        $ids = [];

        while($count <= $maxMintable) {
            $amount = (6000 - ($count-1)) * 0.02;
            $this->info('Sending OG Dust for Flovatar ' . $count . ' - ' . $amount);
            $amounts[] = $amount;
            $ids[] = $count;
            $subCount++;
            if($subCount >= $maxBatch) {
                $this->info('Sending OG Dust for batch Flovatar ' . count($amounts));
                $isProd = $this->option('prod');
                if ($isProd) {
                    SendDustToFlovatars::dispatch($amounts, $ids);
                }
                $amounts = [];
                $ids = [];
                $subCount = 0;
            }
            $count++;
        }

        return true;
    }
}
