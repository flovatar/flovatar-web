<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Cache;

class HomeController extends Controller
{
    public function __invoke()
    {

        $flovatars = Cache::remember('db-flovatars-latest-20-no-bg-v2', now()->addMinutes(1), function() {
            $fotw = [
                3980,
                629,
                4927,
                5020,
                4666,
                638,
                4332,
                5518,
                5370,
                741,
                4552,
                927,
                5370,
                5518
            ];
            $flovatars = Flovatar::whereIn('flow_id', $fotw)->get()->makeHidden(['svg', 'full_svg', 'full_svg_no_bg', 'flow_metadata']);
            // Pick random ids first: ORDER BY RAND() over full rows (large svg/json columns) takes ~25 s.
            $randomIds = Flovatar::inRandomOrder()->take(10)->pluck('id');
            $randFlovatars = Flovatar::whereIn('id', $randomIds)->get()->makeHidden(['svg', 'full_svg', 'full_svg_no_bg', 'flow_metadata']);
            return $flovatars->concat($randFlovatars);
        });

        return view('home')->with(['flovatars' => $flovatars, 'templates' => []]);
    }
}
