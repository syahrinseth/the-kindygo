<x-filament::page>
    <div>
        <p class="text-gray-500 dark:text-gray-400">
            Monitor your financial metrics and invoice status
        </p>
    </div>

    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :columns="$this->getWidgetsColumns()"
    />
</x-filament::page>
