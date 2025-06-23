<?php

namespace App\Providers;

use App\Filament\Pages\FinanceDashboard;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Policies\CentrePolicy;
use App\Policies\ChildPolicy;
use App\Policies\EInvoicePolicy;
use App\Policies\FinanceDashboardPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Centre::class => CentrePolicy::class,
        Child::class => ChildPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        Product::class => ProductPolicy::class,
        FinanceDashboard::class => FinanceDashboardPolicy::class,
        // EInvoiceResource uses Invoice model but with EInvoicePolicy for specific e-invoice permissions
        'App\Filament\Resources\EInvoiceResource' => EInvoicePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
