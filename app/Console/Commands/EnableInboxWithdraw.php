<?php

namespace App\Console\Commands;

use App\Jobs\EnableInboxWithdraw as EnableInboxWithdrawJob;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EnableInboxWithdraw extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:enable-inbox {enabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable or Disable the Inbox Withdraws';

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

        $enabled = $this->argument('enabled') == 'true' ? true : false;

        info('Setting Inbox to '.$enabled);
        $this->info('Setting Inbox to '.$enabled);

        EnableInboxWithdrawJob::dispatch($enabled);

        info('Finished Setting Inbox to '.$enabled);
        $this->info('Finished Setting Inbox to '.$enabled);



        return true;
    }
}
