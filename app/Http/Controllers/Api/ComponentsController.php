<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\FilteredController;
use App\Models\FlovatarComponent;
use Cache;

class ComponentsController extends FilteredController
{
    public function __invoke($id)
    {
        $component = FlovatarComponent::whereFlowId($id)->first();
        return $component ?  : abort(404);

    }
}
