@php
    use App\Enums\Gateway;
    use App\Filament\Admin\Resources\Invoices\InvoiceResource;
    use Filament\Facades\Filament;
    use Illuminate\Support\Facades\Auth;

    $tenant = $record->tenant;
    $centre = $record->centre;
    $parent = $record->user;
    $parentAddress = $parent?->userAddress?->getFormattedAddress();
    $paidAmount = $record->getTotalPaid();
    $outstandingBalance = max(0, $record->total_amount - $paidAmount);
    $canUpdate = Filament::getCurrentPanel()?->getId() === 'admin'
        && (Auth::user()?->can('update', $record) ?? false);
    $editUrl = $canUpdate ? InvoiceResource::getUrl('edit', ['record' => $record]) : null;
    $manageItemsUrl = $canUpdate ? InvoiceResource::getUrl('edit', ['record' => $record, 'relation' => 0]) : null;
    $einvoiceColor = match ($record->einvoice_status) {
        'submitted', 'valid' => 'success',
        'processing' => 'warning',
        'invalid', 'rejected' => 'danger',
        default => 'gray',
    };
    $einvoiceLabel = match ($record->einvoice_status) {
        'submitted' => 'Submitted',
        'processing' => 'Processing',
        'valid' => 'Valid',
        'invalid' => 'Invalid',
        'rejected' => 'Rejected',
        default => 'Not submitted',
    };
@endphp

<div class="invoice-view-grid">
    <article class="invoice-paper" aria-label="Invoice {{ $record->number }}">
        <header class="invoice-paper__header">
            <div class="invoice-brand">
                <div class="invoice-brand__mark" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-sun" class="size-8" />
                </div>

                <div>
                    <p class="invoice-brand__name">{{ $tenant?->name ?? config('app.name') }}</p>

                    @if (filled($tenant?->business_id_value))
                        <p>{{ $tenant->business_id_value }}</p>
                    @endif

                    @if (filled($tenant?->tax_identification_number))
                        <p>TIN: {{ $tenant->tax_identification_number }}</p>
                    @endif

                    @if ($centre)
                        <p class="invoice-brand__centre">{{ $centre->name }}</p>
                        @if (filled($centre->full_address))
                            <p>{{ $centre->full_address }}</p>
                        @endif
                        @if (filled($centre->phone) || filled($centre->email))
                            <p>{{ collect([$centre->phone, $centre->email])->filter()->join(' · ') }}</p>
                        @endif
                    @endif
                </div>
            </div>

            <div class="invoice-heading">
                <p>Invoice</p>
                <x-filament::badge :color="$record->status->color()">
                    {{ $record->status->label() }}
                </x-filament::badge>
            </div>
        </header>

        <section class="invoice-meta">
            <div class="invoice-meta__recipient">
                <p class="invoice-label">Billed to</p>
                <p class="invoice-meta__name">{{ $parent?->name ?? 'Parent not assigned' }}</p>
                <p>{{ $parentAddress ?: 'No billing address recorded' }}</p>
                @if (filled($parent?->email))
                    <p>{{ $parent->email }}</p>
                @endif
            </div>

            <dl class="invoice-meta__facts">
                <div>
                    <dt>Billing period</dt>
                    <dd>{{ $record->date?->format('F Y') ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Payment due</dt>
                    <dd @class(['invoice-text-danger' => $record->isOverdue()])>
                        {{ $record->due_at?->format('d M Y') ?? 'Not set' }}
                    </dd>
                </div>
                <div>
                    <dt>Invoice number</dt>
                    <dd>{{ $record->number }}</dd>
                </div>
            </dl>
        </section>

        <section class="invoice-items" aria-labelledby="invoice-items-heading">
            <h2 id="invoice-items-heading" class="sr-only">Invoice line items</h2>

            <div class="invoice-items__scroller">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Description</th>
                            <th scope="col">Child</th>
                            <th scope="col" class="invoice-number-cell">Qty</th>
                            <th scope="col" class="invoice-number-cell">Unit price</th>
                            <th scope="col" class="invoice-number-cell">Discount</th>
                            <th scope="col" class="invoice-number-cell">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record->invoiceItems as $item)
                            <tr wire:key="invoice-item-{{ $item->getKey() }}">
                                <td>
                                    <span class="invoice-item-name">{{ $item->name }}</span>
                                    @if (filled($item->description))
                                        <span class="invoice-item-description">{{ $item->description }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->child?->full_name ?? '—' }}</td>
                                <td class="invoice-number-cell">{{ number_format($item->quantity) }}</td>
                                <td class="invoice-number-cell">RM {{ number_format($item->price / 100, 2) }}</td>
                                <td class="invoice-number-cell">
                                    {{ $item->discount > 0 ? 'RM '.number_format($item->discount / 100, 2) : '—' }}
                                </td>
                                <td class="invoice-number-cell invoice-item-total">RM {{ number_format($item->total / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="invoice-items__empty">
                                    No line items have been added to this invoice.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="invoice-summary" aria-label="Invoice totals">
            <dl>
                <div>
                    <dt>Subtotal</dt>
                    <dd>RM {{ number_format($record->subtotal_amount / 100, 2) }}</dd>
                </div>
                <div>
                    <dt>Discounts</dt>
                    <dd>−RM {{ number_format($record->discount_amount / 100, 2) }}</dd>
                </div>
                <div class="invoice-summary__total">
                    <dt>Invoice total</dt>
                    <dd>RM {{ number_format($record->total_amount / 100, 2) }}</dd>
                </div>
                <div>
                    <dt>Paid to date</dt>
                    <dd>−RM {{ number_format($paidAmount / 100, 2) }}</dd>
                </div>
            </dl>
        </section>

        <footer class="invoice-paper__footer">
            <p>Thank you. Payment allocations and receipts are recorded alongside this invoice.</p>

            <div @class([
                'invoice-balance',
                'invoice-balance--settled' => $outstandingBalance === 0,
                'invoice-balance--overdue' => $outstandingBalance > 0 && $record->isOverdue(),
            ])>
                <p>Balance due</p>
                <strong>RM {{ number_format($outstandingBalance / 100, 2) }}</strong>
                <span>
                    {{ $outstandingBalance === 0 ? 'Paid in full' : 'Due on '.($record->due_at?->format('d M Y') ?? 'date not set') }}
                </span>
            </div>
        </footer>
    </article>

    <aside class="invoice-operations" aria-label="Invoice operations">
        <x-filament::section>
            <x-slot name="heading">Payment activity</x-slot>
            <x-slot name="description">Payments allocated to this invoice.</x-slot>

            <div class="invoice-payment-list">
                @forelse ($record->payments as $payment)
                    @php
                        $proof = $payment->getFirstMedia('payment_proof');
                        $proofUrl = null;

                        if ($proof) {
                            try {
                                $proofUrl = $proof->getTemporaryUrl(now()->addMinutes(30));
                            } catch (Throwable) {
                                $proofUrl = null;
                            }
                        }
                    @endphp

                    <article class="invoice-payment" wire:key="invoice-payment-{{ $payment->getKey() }}" x-data="{ expanded: false }">
                        <div class="invoice-payment__marker" aria-hidden="true">
                            <x-filament::icon icon="heroicon-m-check" class="size-3.5" />
                        </div>

                        <div class="invoice-payment__body">
                            <div class="invoice-payment__heading">
                                <div>
                                    <p>RM {{ number_format(($payment->pivot?->amount ?? 0) / 100, 2) }} received</p>
                                    <span>{{ $payment->gateway->label() }}{{ $payment->getChipPaymentMethod() ? ' · '.strtoupper($payment->getChipPaymentMethod()) : '' }}</span>
                                </div>

                                <x-filament::badge :color="$payment->status->color()" size="sm">
                                    {{ $payment->status->label() }}
                                </x-filament::badge>
                            </div>

                            <p class="invoice-payment__date">
                                {{ $payment->paid_at?->format('d M Y, H:i') ?? $payment->created_at?->format('d M Y, H:i') }}
                            </p>

                            @if ($payment->gateway === Gateway::CHIP || filled($payment->description) || $proof)
                                <button type="button" class="invoice-payment__toggle" x-on:click="expanded = ! expanded" x-bind:aria-expanded="expanded">
                                    <span x-text="expanded ? 'Hide payment details' : 'View payment details'"></span>
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="size-4" x-bind:class="{ 'rotate-180': expanded }" />
                                </button>

                                <div x-show="expanded" x-collapse x-cloak class="invoice-payment__details">
                                    @if (filled($payment->reference_no))
                                        <p><span>Reference</span>{{ $payment->reference_no }}</p>
                                    @endif
                                    @if (filled($payment->getChipTransactionId()))
                                        <p><span>Transaction ID</span>{{ $payment->getChipTransactionId() }}</p>
                                    @endif
                                    @if (filled($payment->getChipBankName()))
                                        <p><span>Bank</span>{{ $payment->getChipBankName() }}</p>
                                    @endif
                                    @if (filled($payment->description))
                                        <p><span>Note</span>{{ $payment->description }}</p>
                                    @endif
                                    @if ($proof)
                                        <p>
                                            <span>Payment proof</span>
                                            @if ($proofUrl)
                                                <a href="{{ $proofUrl }}" target="_blank" rel="noopener noreferrer">Open attachment</a>
                                            @else
                                                {{ $proof->file_name }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="invoice-operations__empty">
                        <x-filament::icon icon="heroicon-o-banknotes" class="size-6" />
                        <p>No payments recorded yet.</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section :collapsible="true" :collapsed="blank($record->einvoice_uuid)">
            <x-slot name="heading">E-Invoice</x-slot>
            <x-slot name="afterHeader">
                <x-filament::badge :color="$einvoiceColor" size="sm">{{ $einvoiceLabel }}</x-filament::badge>
            </x-slot>

            @if (blank($record->einvoice_uuid))
                <p class="invoice-operations__helper">Submit when the invoice is final and ready for LHDN validation.</p>
            @else
                <dl class="invoice-einvoice-details">
                    <div>
                        <dt>Submitted on</dt>
                        <dd>{{ $record->einvoice_submitted_at?->format('d M Y, H:i') ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt>E-Invoice UUID</dt>
                        <dd>{{ $record->einvoice_uuid }}</dd>
                    </div>
                    @if (filled($record->einvoice_submission_id))
                        <div>
                            <dt>Submission ID</dt>
                            <dd>{{ $record->einvoice_submission_id }}</dd>
                        </div>
                    @endif
                    @if (filled($record->einvoice_validation_url))
                        <div>
                            <dt>Validation</dt>
                            <dd><a href="{{ $record->einvoice_validation_url }}" target="_blank" rel="noopener noreferrer">Open validation page</a></dd>
                        </div>
                    @endif
                </dl>
            @endif
        </x-filament::section>

        @if ($canUpdate)
            <x-filament::section>
                <x-slot name="heading">Invoice controls</x-slot>

                <div class="invoice-control-list">
                    <x-filament::button tag="a" :href="$editUrl" color="gray" icon="heroicon-o-pencil-square" outlined>
                        Edit invoice
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$manageItemsUrl" color="gray" icon="heroicon-o-list-bullet" outlined>
                        Manage line items
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </aside>
</div>
