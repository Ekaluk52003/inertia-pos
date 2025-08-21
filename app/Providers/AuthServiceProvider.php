<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\QrCode;
use App\Models\Restaurant;
use App\Policies\BillPolicy;
use App\Policies\MenuPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\QrCodePolicy;
use App\Policies\RestaurantPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Restaurant::class => RestaurantPolicy::class,
        Menu::class => MenuPolicy::class,
        QrCode::class => QrCodePolicy::class,
        Order::class => OrderPolicy::class,
        Bill::class => BillPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define any additional gates here if needed
    }
}
