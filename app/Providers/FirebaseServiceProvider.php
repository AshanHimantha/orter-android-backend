<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase;
use Kreait\Firebase\Factory;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Firebase\Auth::class, function ($app) {
            return (new Factory)
                ->withServiceAccount(config('firebase.credentials'))
                ->withProjectId(config('firebase.project_id'))
                ->createAuth();
        });
    }

    public function boot()
    {
        //
    }
}
