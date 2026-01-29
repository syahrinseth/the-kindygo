<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Quick Pay')"
        :description="__('Select invoices to pay together. Payments are allocated to oldest invoices first.')"
        icon="heroicon-o-credit-card"
    >
        <div wire:poll.30s>
            @php
                $invoices = $this->getInvoices();
            @endphp

            @if($invoices->isEmpty())
                <div class="flex flex-col items-center justify-center py-8">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="h-12 w-12 text-success-500"
                    />
                    <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">All invoices are paid!</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You have no outstanding invoices.</p>
                </div>
            @else
                <div
                    x-data="{
                        selected: [],
                        get totalSelected() {
                            return this.selected.reduce((sum, id) => {
                                const checkbox = document.querySelector(`input[value='${id}']`);
                                const balance = parseFloat(checkbox?.dataset.balance || 0);
                                return sum + balance;
                            }, 0);
                        },
                        toggleAll() {
                            const allIds = [...document.querySelectorAll('input[data-invoice-checkbox]')].map(el => el.value);
                            if (this.selected.length === allIds.length) {
                                this.selected = [];
                            } else {
                                this.selected = allIds;
                            }
                        }
                    }"
                    class="space-y-3"
                >
                    {{-- Select All / Deselect All --}}
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                        <button
                            type="button"
                            @click="toggleAll()"
                            class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200"
                        >
                            <span x-show="selected.length < {{ $invoices->count() }}">Select All</span>
                            <span x-show="selected.length === {{ $invoices->count() }}">Deselect All</span>
                        </button>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $invoices->count() }} invoice(s) available
                        </span>
                    </div>

                    {{-- Invoice List --}}
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach($invoices as $invoice)
                            @php
                                $balance = $invoice->getRemainingBalance();
                                $isOverdue = $invoice->due_at && $invoice->due_at->isPast();
                            @endphp
                            <label
                                class="group flex items-center gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer transition-all duration-200 hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-700 dark:hover:border-primary-600 dark:hover:bg-primary-900/20"
                                :class="{ 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-900/30': selected.includes('{{ $invoice->id }}') }"
                            >
                                <input
                                    type="checkbox"
                                    value="{{ $invoice->id }}"
                                    data-balance="{{ $balance / 100 }}"
                                    data-invoice-checkbox
                                    x-model="selected"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 transition-colors duration-200"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="font-medium text-sm text-gray-900 dark:text-white truncate">
                                                {{ $invoice->number }}
                                            </span>
                                            @if($isOverdue)
                                                <x-filament::badge color="danger" size="xs">
                                                    Overdue
                                                </x-filament::badge>
                                            @endif
                                        </div>
                                        <span class="font-semibold text-sm text-gray-900 dark:text-white whitespace-nowrap">
                                            RM {{ number_format($balance / 100, 2) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            Due: {{ $invoice->due_at ? $invoice->due_at->format('d M Y') : 'No due date' }}
                                        </span>
                                        @if($invoice->centre)
                                            <span class="text-xs text-gray-400 dark:text-gray-500 truncate ml-2">
                                                {{ $invoice->centre->name }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    {{-- Summary and Action --}}
                    <div class="flex flex-col gap-4 border-t border-gray-200 pt-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Selected Total:</p>
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 transition-all duration-300" x-text="'RM ' + totalSelected.toFixed(2)">
                                RM 0.00
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500" x-show="selected.length > 0">
                                <span x-text="selected.length"></span> invoice(s) selected
                            </p>
                        </div>
                        <div class="sm:text-right">
                            <x-filament::button
                                color="primary"
                                icon="heroicon-o-credit-card"
                                size="lg"
                                x-bind:disabled="selected.length === 0"
                                x-on:click="
                                    if (selected.length > 0) {
                                        window.location.href = '/make-payment?preselect=' + selected.join(',');
                                    }
                                "
                                class="w-full sm:w-auto transition-all duration-200"
                            >
                                Pay Selected
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
