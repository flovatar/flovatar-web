<?php

namespace App\Jobs;


use App\Models\Event;
use App\Models\Sync;
use Flow;
use Illuminate\Support\Facades\Log;

class SyncEvents extends BaseJob
{

    protected $all;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($all = false)
    {
        $this->onConnection('database')->onQueue('single');
        $this->all = $all;
        parent::__construct();
    }

    public function getName(){
        return 'SyncEvents';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $latestHeight = $this->getLatestBlockHeight();

        $list = ($this->all) ? $this->eventsList : $this->eventsBaseList;
        foreach($list as $key => $val){
            $sync = Sync::whereName($key)->first();

            if(!$sync){
                $sync = Sync::create([
                    'name' => $key,
                    'height' => config('services.flow.minBlockHeight')
                ]);
            }

            while($sync->height < $latestHeight) {

                $maxHeight = min($latestHeight, $sync->height + 149);


                $address = str_replace('0x', '', config('services.flow.address'));
                $results = null;
                try {
                $results = Flow::event('A.'.$address.'.'.$val)
                    ->minBlock($sync->height)
                    ->maxBlock($maxHeight)
                    ->run();
                } catch (\Exception $exception) {
                    Log::error($exception);
                    $results = null;
                }
                if(!$results) {
                    try {
                        $results = Flow::event('A.' . $address . '.' . $val)
                            ->minBlock($sync->height+1)
                            ->maxBlock($maxHeight)
                            ->run();
                    } catch (\Exception $exception) {
                        Log::error($exception);
                        $results = Flow::event('A.' . $address . '.' . $val)
                            ->minBlock($latestHeight)
                            ->maxBlock($latestHeight)
                            ->run();
                    }
                }
                if($results){
                    $this->saveFlowEvents($results);

                    $sync->height = $maxHeight;
                    $sync->save();
                }

            }
        }
    }


    protected function saveFlowEvents($results){

        $flowMetaArr = $this->parseFlowEvents($results);

        foreach ($flowMetaArr as $flowMeta) {

            Event::firstOrCreate(
                [
                    'block_id' => $flowMeta['blockID'],
                    'transaction_id' => $flowMeta['transactionId'],
                    'type' => $flowMeta['type'],
                    'index' => $flowMeta['index'],
                ],
                [
                    'flow_metadata' => $flowMeta['values']
                ]
            );
        }
    }

}
