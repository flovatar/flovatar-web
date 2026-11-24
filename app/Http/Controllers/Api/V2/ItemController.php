<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Headless\ItemSnapshot;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v2/{flovatars|flobots|collectibles|components}/{id}: DB snapshot of one item (owner, listing,
 * sales, images). Public + edge-cached 60 s; the page confirms owner/listing on chain.
 */
class ItemController extends Controller
{
    public function showSegment(string $segment, int $id)
    {
        return $this->show((string) array_search($segment, ItemSnapshot::SEGMENTS, true), $id);
    }

    public function show(string $type, int $id)
    {
        $snapshot = Cache::flexible('apiv2:item:'.$type.':'.$id, [15, 120], fn () => ItemSnapshot::build($type, $id) ?? false);
        abort_unless($snapshot, 404);

        return $snapshot;
    }
}
