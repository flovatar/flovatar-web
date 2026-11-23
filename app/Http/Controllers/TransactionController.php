<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;

class TransactionController extends Controller
{
    public function __invoke($address = null)
    {

        $templates = $this->templatesLean('all');


        $stats = $this->getStats();


        return view('transactions')->with(['address' => $address, 'templates' => $templates, 'stats' => $stats]);
    }
}
