<?php

namespace App\Http\Controllers;

use App\Services\Headless\ItemSnapshot;
use Illuminate\Support\Facades\Cache;

/**
 * Flobit TYPE page /flobits/{templateFlowId}: one page per Flobit template (supply, floor, cheapest listings, sales).
 * Prebuilt on R2 by headless:build and regenerated on template-relevant events (mint/burn/listing/sale of a Flobit of
 * this template); the same route serves it on the server-rendered site.
 */
class FlobitTypeController extends Controller
{
    public function __invoke(int $templateId)
    {
        // static build/regeneration renders fresh data (a cached copy could predate the event that triggered it)
        $data = \App\Support\Headless::building() ? (ItemSnapshot::flobitType($templateId) ?? false)
            : Cache::remember(\App\Support\Stealth::cacheKey('flobit-type-'.$templateId), now()->addMinute(), fn () => ItemSnapshot::flobitType($templateId) ?? false);
        abort_unless($data, 404);

        return view('flobit-type')->with(['templateId' => $templateId, 'flobitType' => $data]);
    }
}
