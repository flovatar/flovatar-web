<?php

namespace App\Http\Controllers;

use App\Models\DustAccessory;
use App\Models\DustTemplate;
use App\Models\Sale;
use App\Models\SaleOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Cache;
use Flow;

class DustTokenController extends Controller
{
    public function __invoke(){

        return Cache::remember('dust-total-available', now()->addMinutes(10), function() {
            $results = null;
            try {
                $results = Flow::script('./scripts/get_dust_available.cdc')->run();
            } catch (ProcessFailedException $e) {
                return 500000000.0;
            }

            $resJson = json_decode($results);

            if($resJson && isset($resJson->value)) {
                return floatval($resJson->value);
            } else {
                return 500000000.0;
            }

        });
    }
}
