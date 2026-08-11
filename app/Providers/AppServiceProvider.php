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
            $view->with('branding', [
                'product_name' => RentalSupport::productName(),
                'store_name' => RentalSupport::storeName(),
                'store_initials' => RentalSupport::storeInitials(),
                'support_email' => config('branding.support_email'),
                'support_phone' => config('branding.support_phone'),
            ]);
        });
    }
}
