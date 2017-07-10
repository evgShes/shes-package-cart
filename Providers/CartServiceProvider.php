<?php
/**
 * Created by PhpStorm.
 * User: Kris
 * Date: 03.07.2017
 * Time: 23:49
 */

namespace ShesShoppingCart\Providers;


use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
//        $this->loadMigrationsFrom(__DIR__.'\..\migrations');
        $this->publishes([
            __DIR__.'/../migrations/'=>database_path('migrations'),
            __DIR__.'/../Model/'=>app_path(),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}