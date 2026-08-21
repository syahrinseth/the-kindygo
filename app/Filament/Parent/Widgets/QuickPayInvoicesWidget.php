<?php

namespace App\Filament\Parent\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuickPayInvoicesWidget extends Widget
{
    protected static ?int $sort = 2;

    // Single column on mobile, takes 1 column on md+ (half width in 2-col grid)
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
        'lg' => 1,
        'xl' => 1,
    ];

    protected string $view = 'filament.parent.widgets.quick-pay-invoices';

    /**
     * Get unpaid invoices for quick payment selection.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function getInvoices()
    {
        $user = Auth::user();

        if (! $user || ! $user->current_tenant_id) {
            return collect();
        }

        return Invoice::where('user_id', $user->id)
            ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
            ->where('total_amount', '>', 0)
            ->orderBy('due_at', 'asc')
            ->limit(10)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingBalance() > 0);
    }
}
