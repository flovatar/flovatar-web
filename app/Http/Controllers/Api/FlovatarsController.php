<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\FilteredController;
use App\Models\Flovatar;
use Cache;

class FlovatarsController extends FilteredController
{
    public function all()
    {
        return Cache::remember('db-flovatar-get-all', now()->addSeconds(60), function() {
            return Flovatar::select(
            'flow_id',
            'flow_address',
            'name',
            'flow_metadata->bio as bio',
            'flow_metadata->metadata->creatorAddress as creatorAddress',
            'flow_metadata->metadata->components->body as body',
            'flow_metadata->metadata->components->eyes as eyes',
            'flow_metadata->metadata->components->hair as hair',
            'flow_metadata->metadata->components->nose as nose',
            'flow_metadata->metadata->components->mouth as mouth',
            'flow_metadata->metadata->components->clothing as clothing',
            'flow_metadata->metadata->components->facialHair as facialHair',
            'flow_metadata->metadata->combination as combination',
            'flow_metadata->accessoryId as accessory',
            'flow_metadata->backgroundId as background',
            'flow_metadata->eyeglassesId as eyeglasses',
            'flow_metadata->hatId as hat',
            'rare_count',
            'epic_count',
            'legendary_count',
            'created_at',
            'updated_at')
            ->orderBy('flow_id')
            ->get();
        });
    }
    public function byId($id)
    {
        return Flovatar::whereFlowId($id)->first();

    }
    public function search()
    {

        $flovatars = Cache::remember('db-flovatar-search-'.$this->paramString(), now()->addSeconds(30), function() {
            return $this->getFilteredFlovatars(request());
        });

        return $flovatars;
    }
}
