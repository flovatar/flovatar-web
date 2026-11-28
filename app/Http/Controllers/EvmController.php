<?php

namespace App\Http\Controllers;

/** Flow EVM gallery page (EVM-A 2026-11). Static shell: the browser reads Flow EVM + Flow itself. */
class EvmController extends Controller
{
    public function gallery()
    {
        return view('evm');
    }
}
