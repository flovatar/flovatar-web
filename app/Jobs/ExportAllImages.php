<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExportAllImages extends BaseJob
{

    protected $flovatarId;
    protected $model;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($flovatarId, $model = 'flovatar')
    {
        $this->flovatarId = $flovatarId;
        $this->model = $model;
        $this->onQueue('images');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        ExportImage::dispatch($this->flovatarId, true, 'svg', $this->model);
        ExportImage::dispatch($this->flovatarId,true, 'gif', $this->model);
        ExportImage::dispatch($this->flovatarId, true, 'png', $this->model);
        //ExportStl::dispatch($this->flovatarId);
        ExportImage::dispatch($this->flovatarId, false, 'svg', $this->model);
        ExportImage::dispatch($this->flovatarId, false, 'gif', $this->model);
        ExportImage::dispatch($this->flovatarId, false, 'png', $this->model);

        return true;
    }
}
