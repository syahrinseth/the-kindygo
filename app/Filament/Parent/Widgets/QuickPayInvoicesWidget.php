<?php

namespace App\Filament\Parent\Widgets;

use App\Models\Invoice;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuickPayInvoicesWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.parent.widgets.quick-pay-invoices';

    public function getInvoices()
    {
        return Invoice::where('user_id', Auth::id())
            ->where('tenant_id', Auth::user()->current_tenant_id)
            ->whereIn('status', ['pending', 'overdue'])
            ->orderBy('due_at', 'asc')
            ->limit(10)
            ->get()
            ->filter(fn ($invoice) => $invoice->getRemainingBalance() > 0);
    }
}
