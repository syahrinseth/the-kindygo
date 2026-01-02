<div class="flex items-center gap-2 ml-4" style="margin-left: 10px;">
    <select
        id="tenant-switcher"
        wire:model.live="selectedTenant"
        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500"
    >
        @foreach($tenants as $tenant)
            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
        @endforeach
    </select>
</div>
