<?php

namespace App\Console\Commands;

use App\Jobs\MintComponent as MintComponentJob;
use App\Jobs\MintComponentFake as MintComponentFakeJob;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;

class MintComponents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:mint-components {--prod} {template_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mints all the components!';

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


        if($this->argument('template_id')){
            $template = FlovatarTemplate::whereFlowId($this->argument('template_id'))->first();
            $this->mintComponent($template);
        } else {
            $templates = FlovatarTemplate::whereNotNull('flow_id')->get();
            foreach($templates as $template){
                $this->mintComponent($template);
            }
        }


        $this->info('All done!');
    }


    protected function mintComponent($template){
        $max_batch = 40;
        $skip_array = ['body', 'hair', 'facialHair', 'eyes', 'nose', 'mouth', 'clothing', 'arms', 'legs', 'face', 'head'];

        if(in_array($template->category, $skip_array)){
            $this->info('Skipping Template '.$template->id.' - '.$template->name);
        } else {
            $count = $template->flovatarComponents()->count();
            $maxMintable = $template->max_mintable;
            if($template->category == 'land' || $template->category == 'fub' || $template->category == 'flobotkit'){
                $maxMintable = round($maxMintable/4);
            }
            if($count < $maxMintable) {
                $this->info('Creating Components for Template ' . $template->id . ' - ' . $template->name);
                while ($count < $maxMintable) {
                    $quantity = min($max_batch, ($maxMintable - $count));
                    $isProd = $this->option('prod');
                    if ($isProd) {
                        MintComponentJob::dispatch($template, $quantity);
                    } else {
                        MintComponentFakeJob::dispatch($template, $quantity, $count);
                    }
                    $count += $quantity;
                }
            } else {
                $this->info('Skipping because already minted Template '.$template->id.' - '.$template->name);
            }
        }
    }
}
