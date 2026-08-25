<?php

namespace App\Providers;

use App\Support\RentalSupport;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view): void {
            $storeName = RentalSupport::storeName();
            $view->with('branding', [
                'product_name' => RentalSupport::productName(),
                'store_name' => $storeName,
                'store_initials' => RentalSupport::storeInitials($storeName),
                'use_locx_logo' => (bool) config('branding.use_locx_logo'),
                'support_email' => config('branding.support_email'),
                'support_phone' => config('branding.support_phone'),
            ]);
        });
    }
}
