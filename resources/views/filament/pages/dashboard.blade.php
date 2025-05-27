<x-filament::page>
    <x-filament::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="$this->getHeaderWidgetsColumns()"
    />

    {{ $slot }}

    <x-filament::widgets
        :widgets="$this->getFooterWidgets()"
        :columns="$this->getFooterWidgetsColumns()"
    />
</x-filament::page>
