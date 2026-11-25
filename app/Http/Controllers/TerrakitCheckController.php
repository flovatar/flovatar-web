<?php

namespace App\Http\Controllers;

use App\Support\TerrakitCheck;

/**
 * /terrakit/check — TerraKit wallet check (KB notes/terrakit-check.md). Static shell: no user data, no DB; the browser
 * reads the address with one FCL script (resources/js/app/terrakit-check.js). 404 unless TERRAKIT_CHECK_ENABLED.
 */
class TerrakitCheckController extends Controller
{
    public function __invoke()
    {
        abort_unless(TerrakitCheck::enabled(), 404);

        return response()->view('terrakit.check')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
