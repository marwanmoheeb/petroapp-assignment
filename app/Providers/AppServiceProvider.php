<?php

namespace App\Providers;

use App\Contracts\TransferEventStoreInterface;
use App\Stores\MysqlTransferEventStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TransferEventStoreInterface::class, MysqlTransferEventStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
