<?php

namespace App\Providers;

use App\Services\Flow\UserMessageSigner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Pack signer: key file from PACK_SIGNER_KEY_FILE (read per call, never logged).
        $this->app->bind(UserMessageSigner::class, fn () => UserMessageSigner::fromConfig());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
