<?php

use App\Http\Controllers\TerrakitCheckController;
use Illuminate\Support\Facades\Route;

/*
 * TerraKit wallet check (read-only). Registered from routes/web.php only when App\Support\TerrakitCheck::enabled()
 * (TERRAKIT_CHECK_ENABLED). KB notes/terrakit-check.md.
 */
Route::get('terrakit/check', TerrakitCheckController::class)->name('terrakit.check');
