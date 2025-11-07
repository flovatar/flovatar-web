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

class ExportStl extends BaseJob
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
        $this->onQueue('stl');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        info('CREATING STL FOR '.$this->flovatarId);

        $flovatar = Flovatar::whereFlowId($this->flovatarId)->firstOrFail();
        $meta = $flovatar->flow_metadata;

        $components = [
            $meta['metadata']['components']['body'],
            $meta['metadata']['components']['clothing'],
            $meta['metadata']['components']['hair'],
            $meta['metadata']['components']['eyes'],
            $meta['metadata']['components']['nose'],
            $meta['metadata']['components']['mouth']
        ];

        if($meta['metadata']['components']['facialHair']){
            $components[] = $meta['metadata']['components']['facialHair'];
        }
        if($meta['eyeglassesId']){
            $components[] = $meta['eyeglassesId'];
        }
        if($meta['hatId']){
            $components[] = $meta['hatId'];
        }
        if($meta['accessoryId']){
            $components[] = $meta['accessoryId'];
        }

        $cmd = ['./run.sh'];

        $cmd[] = '-i';
        $cmd[] = implode(' ', $components);

        $cmd[] = '-o';
        $cmd[] = $this->flovatarId;


        $basePath3d = config('flovatar.base3dHomePath');

        $process = new Process($cmd, $basePath3d.config('flovatar.combinerFolder'));
        $process->setTimeout(3600)->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);

        }



        $filePath = $basePath3d.'OutputModels/'.$this->flovatarId.'.stl';
        $s3Folder = 'stl';
        Storage::disk('s3')->putFileAs($s3Folder, new File($filePath), $this->flovatarId.'.stl');
        unlink($filePath);


        return true;
    }

}
