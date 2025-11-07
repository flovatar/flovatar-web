<?php

namespace App\Jobs;

use App\Models\DustCollectible;
use App\Models\Flovatar;
use App\Models\Flobot;
use Illuminate\Foundation\Bus\Dispatchable;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Storage;
use Illuminate\Http\File;
use Str;
use DeDmytro\CloudflareImages\Facades\CloudflareApi;
use DeDmytro\CloudflareImages\Http\Responses\DetailsResponse;
use DeDmytro\CloudflareImages\Http\Entities\Image;

class MoveStl extends BaseJob
{
    protected $flovatarId;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($flovatarId)
    {
        $this->flovatarId = $flovatarId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        info('MOVING STL FOR '.$this->flovatarId);

        $fname = $this->flovatarId.'.stl';
        //Storage::disk('s3')->move('/stl-temp/'.$fname, '/stl/'.$fname);


        return true;
    }

}
