<?php
namespace Imtigger\LaravelCRUD;

use Illuminate\Support\ServiceProvider;

class LaravelCRUDServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }

    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'laravel-crud');
    }
}
