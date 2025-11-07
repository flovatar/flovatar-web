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

class ExportImage extends BaseJob
{

    protected $type;
    protected $flovatarId;
    protected $useBackground;
    protected $model;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($flovatarId, $useBackground = true, $type = 'gif', $model = 'flovatar')
    {
        $this->flovatarId = $flovatarId;
        $this->useBackground = $useBackground;
        $this->type = $type;
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

        info('CREATING IMAGE FOR '.$this->model.' '.$this->flovatarId);
        $nobg = $this->useBackground ? '' : 'nobg/';
        $nobgOut = $this->useBackground ? '' : '-nobg';

        $appBasePath = 'app/public/'.$this->model.'/'.$this->type;

        $pngDir = 'public/'.$this->model.'/'.$this->type.'/'.$this->flovatarId;
        $pngNobgDir = $pngDir.'-nobg';

        Storage::makeDirectory('public/'.$this->model.'/'.$this->type);
        if($this->type == 'png'){
            Storage::makeDirectory($pngDir);
            Storage::makeDirectory($pngNobgDir);
        }
        if($this->type == 'gif'){
            Storage::makeDirectory('public/'.$this->model.'/'.$this->type.'/temp/'.$this->flovatarId.$nobgOut);
        }

        if($this->type == 'svg'){

            $flovatar = null;
            if($this->model == 'flobot') {
                $flovatar = Flobot::whereFlowId($this->flovatarId)->first();
            } else if($this->model == 'collectible'){
                $flovatar = DustCollectible::whereFlowId($this->flovatarId)->first();
            } else {
                $flovatar = Flovatar::whereFlowId($this->flovatarId)->first();
            }

            if($flovatar){
                $baseSvgPath = 'public/'.$this->model.'/'.$this->type.'/'.$this->flovatarId;
                if($this->useBackground){
                    Storage::put($baseSvgPath.'.svg', $flovatar->full_svg);
                } else {
                    Storage::put($baseSvgPath.'-nobg.svg', $flovatar->full_svg_no_bg);
                }
            }
        } else {
            $urlPath = ($this->model == 'flobot' || $this->model == 'collectible') ? $this->model.'/' : '';

            $cmd = ['webgif'];

            $cmd[] = '-u';
            $cmd[] = 'https://flovatar.com/api/image/'.$nobg.$urlPath.$this->flovatarId;

            $cmd[] = '-o';
            $cmd[] = $this->flovatarId.$nobgOut;

            $cmd[] = '-d';
            $cmd[] = '40';
            $cmd[] = '-f';
            $cmd[] = '75';
            $cmd[] = '-q';
            $cmd[] = ($this->type == 'gif') ? '50' : '100';
            $cmd[] = '-t';
            $cmd[] = $this->type;


            $process = new Process($cmd, storage_path('app/public/'.$this->model.'/'.$this->type));
            $process->setTimeout(3600)->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if($this->type == 'gif'){
                Storage::deleteDirectory('public/'.$this->model.'/'.$this->type.'/temp/'.$this->flovatarId.$nobgOut);
            }
        }




        $filePath = $appBasePath.'/'.$this->flovatarId.$nobgOut.'.'.$this->type;
        Storage::disk('s3')->putFileAs($this->model.'/'.$this->type, new File(storage_path($filePath)), $this->flovatarId.$nobgOut.'.'.$this->type);
        Storage::delete('public/'.$this->model.'/'.$this->type.'/'.$this->flovatarId.$nobgOut.'.'.$this->type);



        if($this->type == 'png') {
            $this->createVideo();
            $filePathMp4 = $appBasePath.'/'.$this->flovatarId.$nobgOut.'/'.$this->flovatarId.'.mp4';
            Storage::disk('s3')->putFileAs($this->model.'/mp4', new File(storage_path($filePathMp4)), $this->flovatarId.$nobgOut.'.mp4');
            Storage::deleteDirectory('public/'.$this->model.'/png/'.$this->flovatarId.$nobgOut);
        }

        return true;
    }

    protected function movePngImages($folder){

        $files = Storage::allFiles($folder);
        $nobg = $this->useBackground ? '' : '-nobg';
        foreach($files as $file) {
            if (!Str::contains($file, ['.DS_Store'])) {
                $name = str_replace($folder.'/','', $file);
                Storage::disk('s3')->putFileAs($this->model.'/png/'.$this->flovatarId.$nobg, new File(storage_path('app/'.$file)), $name);
            }
        }
        Storage::deleteDirectory($folder);
    }


    protected function createVideo(){
        $cmd = ['ffmpeg'];

        $cmd[] = '-r';
        $cmd[] = '25';
        $cmd[] = '-f';
        $cmd[] = 'image2';
        $cmd[] = '-s';
        $cmd[] = '480x480';
        $cmd[] = '-i';
        $cmd[] = '%d.png';
        $cmd[] = '-vcodec';
        $cmd[] = 'libx264';
        $cmd[] = '-crf';
        $cmd[] = '25';
        $cmd[] = '-pix_fmt';
        $cmd[] = 'yuv420p';

        $cmd[] = $this->flovatarId.'.mp4';


        $nobg = $this->useBackground ? '' : '-nobg';
        $process = new Process($cmd, storage_path('app/public/'.$this->model.'/'.$this->type.'/'.$this->flovatarId.$nobg));
        $process->setTimeout(3600)->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
