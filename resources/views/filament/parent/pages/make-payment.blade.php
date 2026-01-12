<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Select Invoices to Pay</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Choose which invoices you want to pay and customise the amount for each. Payments are allocated to items by priority (tuition fees first, then other items).
                    </p>
                </div>
                @if ($this->isMultiCentreSelection())
                    <div class="flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-2 dark:bg-primary-900/20">
                        <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-sm font-medium text-primary-600 dark:text-primary-400">Multi-Centre Payment</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Invoice List --}}
        @if (count($invoices) > 0)
            <div class="space-y-6">
                @foreach ($this->getInvoicesByCentre() as $centreGroup)
                    {{-- Centre Header --}}
                    @if (count($this->getInvoicesByCentre()) > 1)
                        <div class="rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-900">
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $centreGroup['centre_name'] }}</h3>
                        </div>
                    @endif

                    {{-- Invoices for this centre --}}
                    <div class="space-y-4">
                        @foreach ($centreGroup['invoices'] as $invoice)
                    <div class="rounded-lg bg-white p-6 shadow transition-all dark:bg-gray-800 {{ ($selectedInvoices[$invoice['id']] ?? false) ? 'ring-2 ring-primary-500' : '' }}">
                        <div class="flex items-start gap-4">
                            {{-- Checkbox --}}
                            <div class="flex items-center pt-1">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedInvoices.{{ $invoice['id'] }}"
                                    wire:click="toggleInvoice({{ $invoice['id'] }})"
                                    class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                    id="invoice-{{ $invoice['id'] }}"
                                />
                            </div>

                            <div class="flex flex-1 items-start justify-between">
                                <div class="flex-1">
                                    <label for="invoice-{{ $invoice['id'] }}" class="cursor-pointer">
                                        <div class="flex items-center gap-4">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ $invoice['number'] }}
                                            </h3>
                                            @if ($invoice['centre_name'] && count($this->getInvoicesByCentre()) === 1)
                                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $invoice['centre_name'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </label>

                                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Due:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $invoice['due_at'] ? \Carbon\Carbon::parse($invoice['due_at'])->format('d M Y') : 'No due date' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Balance:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                RM {{ number_format($invoice['balance'] / 100, 2) }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Invoice Items (expandable) --}}
                                    @if (count($invoice['items']) > 0)
                                        <div class="mt-4" x-data="{ expanded: false }">
                                            <button
                                                @click="expanded = !expanded"
                                                class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                            >
                                                <span x-show="!expanded">Show items ({{ count($invoice['items']) }})</span>
                                                <span x-show="expanded">Hide items</span>
                                            </button>

                                            <div x-show="expanded" x-collapse class="mt-2 space-y-2">
                                                @foreach ($invoice['items'] as $item)
                                                    <div class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                        <div class="flex-1">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                                {{ $item['description'] }}
                                                            </div>
                                                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                                                Balance: RM {{ number_format($item['balance'] / 100, 2) }}
                                                                @if ($item['priority'])
                                                                    · Priority:
                                                                    @if ($item['priority'] == 4)
                                                                        <span class="text-danger-600">Critical</span>
                                                                    @elseif ($item['priority'] == 3)
                                                                        <span class="text-warning-600">High</span>
                                                                    @elseif ($item['priority'] == 2)
                                                                        <span class="text-info-600">Medium</span>
                                                                    @else
                                                                        <span class="text-success-600">Low</span>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Amount Input --}}
                                <div class="ml-6 w-48 flex-shrink-0">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Amount to Pay
                                    </label>
                                    <div class="mt-1 flex items-center">
                                        <span class="mr-2 text-sm text-gray-600 dark:text-gray-400">RM</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="{{ $invoice['balance'] / 100 }}"
                                            wire:model.live.debounce.500ms="selectedAmounts.{{ $invoice['id'] }}"
                                            wire:change="calculateTotal"
                                            {{ !($selectedInvoices[$invoice['id']] ?? false) ? 'disabled' : '' }}
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:disabled:bg-gray-800 sm:text-sm"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Max: RM {{ number_format($invoice['balance'] / 100, 2) }}
                                    </p>
                                </div>
                            </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @endforeach
            </div>

            {{-- Payment Summary --}}
            <div class="rounded-lg bg-primary-50 p-6 dark:bg-primary-900/20">
                @if ($this->isMultiCentreSelection())
                    {{-- Multi-Centre Summary --}}
                    <div class="mb-4 space-y-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Centre Breakdown</h3>
                        @foreach ($this->getCentreTotals() as $centreTotal)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ $centreTotal['centre_name'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    RM {{ number_format($centreTotal['total'], 2) }}
                                </span>
                            </div>
                        @endforeach
                        <hr class="border-gray-300 dark:border-gray-700" />
                    </div>
                @endif

                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Total Payment Amount</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">This amount will be charged via CHIP payment gateway</p>
                    </div>
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        RM {{ number_format($totalAmount / 100, 2) }}
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-lg bg-gray-50 p-12 text-center dark:bg-gray-900">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Unpaid Invoices</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    You don't have any outstanding invoices at the moment.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
