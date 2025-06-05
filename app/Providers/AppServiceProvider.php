<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot(): void
  {
    Schema::defaultStringLength(191);
    if ($this->app->environment('prod')) {
      URL::forceScheme('https');
    }

    // Add DB::listen() to the boot method of the AppServiceProvider
    // to log all queries to the log file.
    DB::listen(function ($query) {
        //Log::info($query->sql);
        //Log::info($query->bindings);
        //Log::info($query->time);
        // query sql with bindings
        //Log::info($query->sql, $query->bindings);

    });

  }

  /**
   * Register any application services.
   *
   * @return void
   */
  public function register(): void
  {
  }
}
