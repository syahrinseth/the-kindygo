<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Quick Pay Multiple Invoices')"
        :description="__('Select invoices to pay together. Payment will be allocated to oldest invoices first (FIFO).')"
        icon="heroicon-o-credit-card"
    >
        <div wire:poll.30s>
            @php
                $invoices = $this->getInvoices();
            @endphp

            @if($invoices->isEmpty())
                <div class="flex items-center justify-center py-8 text-gray-500 dark:text-gray-400">
                    <div class="text-center">
                        <x-filament::icon
                            icon="heroicon-o-check-circle"
                            class="mx-auto h-12 w-12 text-success-500"
                        />
                        <p class="mt-2 text-sm font-medium">All invoices are paid!</p>
                        <p class="text-xs">You have no outstanding invoices.</p>
                    </div>
                </div>
            @else
                <div x-data="{
                    selected: [],
                    get totalSelected() {
                        return this.selected.reduce((sum, id) => {
                            const checkbox = document.querySelector(`input[value='${id}']`);
                            const balance = parseFloat(checkbox?.dataset.balance || 0);
                            return sum + balance;
                        }, 0);
                    }
                }">
                    <div class="space-y-2">
                        @foreach($invoices as $invoice)
                            @php
                                $balance = $invoice->getRemainingBalance();
                                $isOverdue = $invoice->due_at->isPast();
                            @endphp
                            <label class="flex items-center gap-3 rounded-lg border p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                <input
                                    type="checkbox"
                                    value="{{ $invoice->id }}"
                                    data-balance="{{ $balance / 100 }}"
                                    x-model="selected"
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                />
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-medium text-sm">{{ $invoice->number }}</span>
                                            @if($isOverdue)
                                                <x-filament::badge color="danger" size="xs" class="ml-2">
                                                    OVERDUE
                                                </x-filament::badge>
                                            @endif
                                        </div>
                                        <span class="font-bold text-sm">RM {{ number_format($balance / 100, 2) }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Due: {{ $invoice->due_at->format('d M Y') }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t pt-4">
                        <div>
                            <p class="text-sm text-gray-500">Selected Total:</p>
                            <p class="text-xl font-bold" x-text="'RM ' + totalSelected.toFixed(2)">RM 0.00</p>
                            <p class="text-xs text-gray-400" x-show="selected.length > 0">
                                <span x-text="selected.length"></span> invoice(s) selected (max 10)
                            </p>
                        </div>
                        <div>
                            <x-filament::button
                                color="success"
                                icon="heroicon-o-credit-card"
                                size="lg"
                                x-bind:disabled="selected.length === 0 || selected.length > 10"
                                x-on:click="
                                    if (selected.length > 0 && selected.length <= 10) {
                                        window.location.href = '/make-payment?preselect=' + selected.join(',');
                                    }
                                "
                            >
                                Pay Selected Invoices
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
