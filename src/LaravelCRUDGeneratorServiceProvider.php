<?php
namespace Imtigger\LaravelCRUD;

use Illuminate\Support\ServiceProvider;

class LaravelCRUDGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(\Imtigger\LaravelCRUD\Console\Commands\MakeCRUD::class);
        $this->commands(\Imtigger\LaravelCRUD\Console\Commands\MakeCRUDHeader::class);
        $this->commands(\Imtigger\LaravelCRUD\Console\Commands\MakeCRUDTranslations::class);
    }

    public function boot()
    {
        
    }

}