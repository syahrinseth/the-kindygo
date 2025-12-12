@php
    use Illuminate\Support\Facades\Auth;
@endphp

<div {{ 
    $attributes->class([
        'fi-topbar sticky top-0 z-20 overflow-x-clip',
        'fi-topbar-with-navigation' => filament()->hasTopNavigation(),
    ]) 
}}>
    <nav
        class="flex h-16 items-center gap-x-4 bg-white px-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:px-6 lg:px-8"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook('panels::topbar.start') }}

        @if (filament()->hasNavigation() && (! filament()->hasTopNavigation()))
            <x-filament::icon-button
                color="gray"
                icon="heroicon-o-bars-3"
                icon-alias="panels::topbar.open-sidebar-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.open()"
                x-show="! $store.sidebar.isOpen"
                class="fi-topbar-open-sidebar-btn shrink-0"
            />

            <x-filament::icon-button
                color="gray"
                icon="heroicon-o-x-mark"
                icon-alias="panels::topbar.close-sidebar-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.close()"
                x-show="$store.sidebar.isOpen"
                class="fi-topbar-close-sidebar-btn shrink-0"
            />
        @endif

        <div class="me-6 flex items-center">
            <x-filament::link :href="filament()->getHomeUrl()" class="flex items-center gap-3">
                @if ($logo = filament()->getLogo())
                    <img
                        alt="{{ filament()->getBrandName() }}"
                        src="{{ $logo }}"
                        class="h-10"
                    />
                @endif

                <span class="text-xl font-bold tracking-tight">
                    {{ filament()->getBrandName() }}
                </span>
            </x-filament::link>
            
            <div class="ml-4">
                <x-filament::current-centre-display />
            </div>

            @if (filament()->hasTenancy() && filament()->hasTenantMenu())
                <div class="ms-4">
                    <x-filament-panels::tenant-menu />
                </div>
            @endif
        </div>

        {{-- Tenant menu moved next to logo for better discoverability --}}

        @if (filament()->hasTopNavigation() && filament()->hasNavigation())
            <ul class="me-4 hidden items-center gap-x-4 lg:flex">
                @foreach ($navigation as $group)
                    @if ($groupLabel = $group->getLabel())
                        <x-filament::dropdown
                            placement="bottom-start"
                            teleport
                            :attributes="
                                \Filament\Support\prepare_inherited_attributes(
                                    new \Illuminate\View\ComponentAttributeBag([
                                        'button' => [
                                            'color' => 'gray',
                                            'icon' => 'heroicon-m-chevron-down',
                                            'iconSuffix' => true,
                                            'label' => $groupLabel,
                                            'size' => 'sm',
                                        ],
                                    ]),
                                )
                                    ->class(['fi-topbar-group'])
                            "
                        >
                            <x-slot name="trigger">
                                {{ $groupLabel }}
                            </x-slot>

                            @foreach ($group->getItems() as $item)
                                @php
                                    $icon = $item->getIcon();
                                @endphp

                                <x-filament::dropdown.list.item
                                    :badge="$item->getBadge()"
                                    :badge-color="$item->getBadgeColor()"
                                    :href="$item->getUrl()"
                                    :icon="$icon"
                                    tag="a"
                                    :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
                                >
                                    {{ $item->getLabel() }}
                                </x-filament::dropdown.list.item>
                            @endforeach
                        </x-filament::dropdown>
                    @else
                        @foreach ($group->getItems() as $item)
                            <x-filament-panels::topbar.item
                                :active="$item->isActive()"
                                :icon="$item->getIcon()"
                                :active-icon="$item->getActiveIcon()"
                                :sort="$item->getSort()"
                                :url="$item->getUrl()"
                                :badge="$item->getBadge()"
                                :badge-color="$item->getBadgeColor()"
                                :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                            >
                                {{ $item->getLabel() }}
                            </x-filament-panels::topbar.item>
                        @endforeach
                    @endif
                @endforeach
            </ul>
        @endif

        <div class="ms-auto flex items-center gap-x-4">
            {{ \Filament\Support\Facades\FilamentView::renderHook('panels::global-search.before') }}

            @if (filament()->isGlobalSearchEnabled())
                @livewire(Filament\Livewire\GlobalSearch::class, ['lazy' => true])
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook('panels::global-search.after') }}

            @if (filament()->auth()->check())
                @if (filament()->hasDatabaseNotifications())
                    @livewire(Filament\Livewire\DatabaseNotifications::class, ['lazy' => true])
                @endif

                <x-filament-panels::user-menu />
            @endif
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook('panels::topbar.end') }}
    </nav>
</div>
