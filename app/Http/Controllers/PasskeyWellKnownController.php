<?php

namespace App\Http\Controllers;

use App\Services\Passkey\WebAuthnOrigins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /.well-known/webauthn — WebAuthn Related Origin Requests: origins allowed to use this site's rp_id
 * (config passkey.origins). Registered only when the passkey feature is enabled (routes/passkey.php); public, cacheable,
 * no cookies (browsers fetch it without credentials).
 */
class PasskeyWellKnownController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(WebAuthnOrigins::wellKnown($request))
            ->header('Cache-Control', 'public, max-age=300')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
