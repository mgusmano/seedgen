<?php

namespace Mgusmano\Seedgen;

// use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
// use Psr\Http\Client\ClientInterface;
use Mgusmano\Seedgen\Console\Commands\seedgen;
// use Recca0120\LaravelErd\Console\Commands\InstallBinary;

class SeedgenServiceProvider extends ServiceProvider
{
  /**
   * Bootstrap the application services.
   */
  public function boot()
  {
    // if ($this->app->runningInConsole()) {
    //     $this->publishes([
    //         __DIR__.'/../config/config.php' => base_path('config/erd-generator.php'),
    //     ], 'config');
    // }
  }

  /**
   * Register the application services.
   */
  public function register()
  {
    //$this->mergeConfigFrom(__DIR__.'/../config/config.php', 'erd-generator');

    $this->app->bind('command.seedgen', seedgen::class);

    $this->commands([
      'command.seedgen',
    ]);
  }
}
