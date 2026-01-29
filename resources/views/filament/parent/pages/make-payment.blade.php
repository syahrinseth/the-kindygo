<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Section --}}
        <x-filament::section>
            <x-slot name="heading">
                Select Invoices to Pay
            </x-slot>
            <x-slot name="description">
                Choose which invoices you want to pay and customise the amount for each. Payments are allocated to items by priority (tuition fees first, then other items).
            </x-slot>

            @if ($this->isMultiCentreSelection())
                <x-slot name="headerEnd">
                    <x-filament::badge color="primary" icon="heroicon-o-building-office-2">
                        Multi-Centre Payment
                    </x-filament::badge>
                </x-slot>
            @endif

            {{-- Bulk Actions --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        wire:click="selectAll"
                        icon="heroicon-o-check-circle"
                    >
                        Select All
                    </x-filament::button>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        wire:click="deselectAll"
                        icon="heroicon-o-x-circle"
                    >
                        Deselect All
                    </x-filament::button>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ count(array_filter($selectedInvoices)) }} invoice(s) selected
                </div>
            </div>
        </x-filament::section>

        {{-- Invoice Selection Section --}}
        @php
            $invoiceGroups = $this->getInvoicesByCentre();
        @endphp

        @if (count($invoiceGroups) > 0)
            <div class="space-y-4">
                @foreach ($invoiceGroups as $centreGroup)
                    <x-filament::section
                        :collapsible="count($invoiceGroups) > 1"
                        :collapsed="false"
                    >
                        @if (count($invoiceGroups) > 1)
                            <x-slot name="heading">
                                {{ $centreGroup['centre_name'] }}
                            </x-slot>
                            <x-slot name="description">
                                {{ count($centreGroup['invoices']) }} invoice(s)
                            </x-slot>
                        @endif

                        <div class="space-y-3">
                            @foreach ($centreGroup['invoices'] as $invoice)
                                <div
                                    class="rounded-lg border p-4 transition-all duration-300 {{ ($selectedInvoices[$invoice['id']] ?? false) ? 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-900/20' : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600' }}"
                                    wire:key="invoice-{{ $invoice['id'] }}"
                                >
                                    <div class="flex items-start gap-4">
                                        {{-- Checkbox --}}
                                        <div class="flex items-center pt-1">
                                            <input
                                                type="checkbox"
                                                wire:click="toggleInvoice({{ $invoice['id'] }})"
                                                @checked($selectedInvoices[$invoice['id']] ?? false)
                                                class="h-5 w-5 rounded border-gray-300 text-primary-600 transition-colors duration-200 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                id="invoice-{{ $invoice['id'] }}"
                                            />
                                        </div>

                                        <div class="flex flex-1 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            {{-- Invoice Details --}}
                                            <div class="flex-1">
                                                <label for="invoice-{{ $invoice['id'] }}" class="cursor-pointer">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-base font-semibold text-gray-900 dark:text-white">
                                                            {{ $invoice['number'] }}
                                                        </span>
                                                        @if ($invoice['centre_name'] && count($invoiceGroups) === 1)
                                                            <x-filament::badge color="gray" size="sm">
                                                                {{ $invoice['centre_name'] }}
                                                            </x-filament::badge>
                                                        @endif
                                                        @if ($invoice['due_at'] && \Carbon\Carbon::parse($invoice['due_at'])->isPast())
                                                            <x-filament::badge color="danger" size="sm">
                                                                Overdue
                                                            </x-filament::badge>
                                                        @endif
                                                    </div>
                                                </label>

                                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400">Due:</span>
                                                        <span class="font-medium text-gray-900 dark:text-white">
                                                            {{ $invoice['due_at'] ? \Carbon\Carbon::parse($invoice['due_at'])->format('d M Y') : 'No due date' }}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400">Balance:</span>
                                                        <span class="font-medium text-gray-900 dark:text-white">
                                                            RM {{ number_format($invoice['balance'] / 100, 2) }}
                                                        </span>
                                                    </div>
                                                </div>

                                                {{-- Invoice Items (expandable) --}}
                                                @if (count($invoice['items']) > 0)
                                                    <div class="mt-3" x-data="{ expanded: false }">
                                                        <button
                                                            type="button"
                                                            @click="expanded = !expanded"
                                                            class="inline-flex items-center gap-1 text-sm text-primary-600 transition-colors duration-200 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                                        >
                                                            <x-filament::icon
                                                                icon="heroicon-o-chevron-right"
                                                                class="h-4 w-4 transition-transform duration-200"
                                                                x-bind:class="{ 'rotate-90': expanded }"
                                                            />
                                                            <span x-text="expanded ? 'Hide items' : 'Show {{ count($invoice['items']) }} item(s)'"></span>
                                                        </button>

                                                        <div
                                                            x-show="expanded"
                                                            x-collapse
                                                            x-cloak
                                                            class="mt-2 space-y-2"
                                                        >
                                                            @foreach ($invoice['items'] as $item)
                                                                <div class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                    <div class="flex-1">
                                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                                            {{ $item['description'] }}
                                                                        </div>
                                                                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                                            <span>Balance: RM {{ number_format($item['balance'] / 100, 2) }}</span>
                                                                            @if ($item['priority'])
                                                                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                                                                <span>Priority:
                                                                                    @if ($item['priority'] == 4)
                                                                                        <span class="text-danger-600 dark:text-danger-400">Critical</span>
                                                                                    @elseif ($item['priority'] == 3)
                                                                                        <span class="text-warning-600 dark:text-warning-400">High</span>
                                                                                    @elseif ($item['priority'] == 2)
                                                                                        <span class="text-info-600 dark:text-info-400">Medium</span>
                                                                                    @else
                                                                                        <span class="text-success-600 dark:text-success-400">Low</span>
                                                                                    @endif
                                                                                </span>
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
                                            <div class="w-full flex-shrink-0 sm:w-44">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Amount to Pay
                                                </label>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">RM</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="{{ $invoice['balance'] / 100 }}"
                                                        wire:model.live.debounce.500ms="selectedAmounts.{{ $invoice['id'] }}"
                                                        wire:change="calculateTotal"
                                                        @disabled(!($selectedInvoices[$invoice['id']] ?? false))
                                                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition-all duration-200 focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:disabled:bg-gray-800 dark:disabled:text-gray-500"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                    Max: RM {{ number_format($invoice['balance'] / 100, 2) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endforeach
            </div>

            {{-- Payment Summary --}}
            <x-filament::section>
                <x-slot name="heading">
                    Payment Summary
                </x-slot>

                @if ($this->isMultiCentreSelection())
                    {{-- Multi-Centre Summary --}}
                    <div class="mb-4 space-y-2">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Centre Breakdown</h4>
                        @foreach ($this->getCentreTotals() as $centreTotal)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ $centreTotal['centre_name'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    RM {{ number_format($centreTotal['total'], 2) }}
                                </span>
                            </div>
                        @endforeach
                        <hr class="border-gray-200 dark:border-gray-700" />
                    </div>
                @endif

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Total Payment Amount</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            This amount will be charged via CHIP payment gateway
                        </p>
                    </div>
                    <div class="text-3xl font-bold text-primary-600 transition-all duration-300 dark:text-primary-400">
                        RM {{ number_format($totalAmount / 100, 2) }}
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <x-filament::button
                        wire:click="processPayment"
                        color="primary"
                        size="lg"
                        icon="heroicon-o-credit-card"
                        :disabled="$totalAmount <= 0"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="processPayment">Proceed to Payment</span>
                        <span wire:loading wire:target="processPayment">Processing...</span>
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            {{-- Empty State --}}
            <x-filament::section>
                <div class="flex flex-col items-center justify-center py-12">
                    <x-filament::icon
                        icon="heroicon-o-document-text"
                        class="h-16 w-16 text-gray-400"
                    />
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No Unpaid Invoices</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        You don't have any outstanding invoices at the moment.
                    </p>
                    <div class="mt-6">
                        <x-filament::button
                            :href="route('filament.parent.pages.dashboard')"
                            tag="a"
                            color="gray"
                        >
                            Back to Dashboard
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
