<?php

namespace App\Console\Commands;

use App\Jobs\CleanPack;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;

class UpdateComponents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:update-components';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update componets to link them to templates';

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

        info('Update Components');

        $components = FlovatarComponent::all();

        foreach ($components as $component) {
            $template = FlovatarTemplate::whereFlowId($component->flow_metadata['templateId'])->first();
            if($template) {
                $component->update([
                    'flovatar_template_id' => $template->id
                ]);
            }
        }

        $this->info('All done!');
        return true;

    }
}
