<?php

use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;

it('uses clear human-readable labels for all invoice statuses', function (): void {
    expect(collect(InvoiceStatus::cases())
        ->mapWithKeys(fn (InvoiceStatus $status): array => [$status->value => $status->label()])
        ->all())
        ->toBe([
            'draft' => 'Draft',
            'pending' => 'Pending',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ]);
});

it('uses consistent Filament colours for invoice and payment statuses', function (): void {
    expect(collect(InvoiceStatus::cases())
        ->mapWithKeys(fn (InvoiceStatus $status): array => [$status->value => $status->color()])
        ->all())
        ->toBe([
            'draft' => 'gray',
            'pending' => 'warning',
            'partially_paid' => 'info',
            'paid' => 'success',
            'overdue' => 'danger',
            'cancelled' => 'gray',
            'refunded' => 'info',
        ])
        ->and(collect(PaymentStatus::cases())
            ->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->color()])
            ->all())
        ->toBe([
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger',
            'cancelled' => 'gray',
            'refunded' => 'info',
            'partially_paid' => 'warning',
            'unpaid' => 'gray',
        ]);
});

it('uses human-readable payment methods and Malaysian currency formatting', function (): void {
    expect(collect(Gateway::cases())
        ->mapWithKeys(fn (Gateway $gateway): array => [$gateway->value => $gateway->label()])
        ->all())
        ->toBe([
            'bank_transfer' => 'Bank transfer',
            'chip' => 'CHIP',
            'billplz' => 'Billplz',
            'stripe' => 'Stripe',
            'cash' => 'Cash',
        ])
        ->and(Invoice::formatMoney(12345))->toBe('RM 123.45')
        ->and(Invoice::formatMoney(12345, includeCurrency: false))->toBe('123.45');
});
