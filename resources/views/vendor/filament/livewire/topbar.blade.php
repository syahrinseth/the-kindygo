
        @if ($hasTopNavigation || (! $hasNavigation))
            {{-- Tenant menu moved to custom topbar view; don't render here to avoid duplication --}}

            @if ($hasNavigation)
                @php
                    $navigation = filament()->getNavigation();
                @endphp

                <ul class="fi-topbar-nav-groups">
                    @foreach ($navigation as $group)
                        @php
                            $groupLabel = $group->getLabel();
                            $groupExtraTopbarAttributeBag = $group->getExtraTopbarAttributeBag();
                            $isGroupActive = $group->isActive();
                            $groupIcon = $group->getIcon();
                        @endphp

                        @if ($groupLabel)
                            <x-filament::dropdown
                                placement="bottom-start"
                                teleport
                                :attributes="\Filament\Support\prepare_inherited_attributes($groupExtraTopbarAttributeBag)"
                            >
                                <x-slot name="trigger">
                                    <x-filament-panels::topbar.item
                                        :active="$isGroupActive"
                                        :icon="$groupIcon"
                                    >
                                        {{ $groupLabel }}
                                    </x-filament-panels::topbar.item>
                                </x-slot>

                                @php
                                    $lists = [];

                                    foreach ($group->getItems() as $item) {
                                        if ($childItems = $item->getChildItems()) {
                                            $lists[] = [
                                                $item,
                                                ...$childItems,
                                            ];
                                            $lists[] = [];

                                            continue;
                                        }

                                        if (empty($lists)) {
                                            $lists[] = [$item];

                                            continue;
                                        }

                                        $lists[count($lists) - 1][] = $item;
                                    }

                                    if (empty($lists[count($lists) - 1])) {
                                        array_pop($lists);
                                    }
                                @endphp

                                @foreach ($lists as $list)
                                    <x-filament::dropdown.list>
                                        @foreach ($list as $item)
                                            @php
                                                $isItemActive = $item->isActive();
                                                $itemBadge = $item->getBadge();
                                                $itemBadgeColor = $item->getBadgeColor();
                                                $itemBadgeTooltip = $item->getBadgeTooltip();
                                                $itemUrl = $item->getUrl();
                                                $itemIcon = $isItemActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon();
                                                $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                            @endphp

                                            <x-filament::dropdown.list.item
                                                :badge="$itemBadge"
                                                :badge-color="$itemBadgeColor"
                                                :badge-tooltip="$itemBadgeTooltip"
                                                :color="$isItemActive ? 'primary' : 'gray'"
                                                :href="$itemUrl"
                                                :icon="$itemIcon"
                                                tag="a"
                                                :target="$shouldItemOpenUrlInNewTab ? '_blank' : null"
                                            >
                                                {{ $item->getLabel() }}
                                            </x-filament::dropdown.list.item>
                                        @endforeach
                                    </x-filament::dropdown.list>
                                @endforeach
                            </x-filament::dropdown>
                        @else
                            @foreach ($group->getItems() as $item)
                                @php
                                    $isItemActive = $item->isActive();
                                    $itemActiveIcon = $item->getActiveIcon();
                                    $itemBadge = $item->getBadge();
                                    $itemBadgeColor = $item->getBadgeColor();
                                    $itemBadgeTooltip = $item->getBadgeTooltip();
                                    $itemIcon = $item->getIcon();
                                    $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                    $itemUrl = $item->getUrl();
                                @endphp

                                <x-filament-panels::topbar.item
                                    :active="$isItemActive"
                                    :active-icon="$itemActiveIcon"
                                    :badge="$itemBadge"
                                    :badge-color="$itemBadgeColor"
                                    :badge-tooltip="$itemBadgeTooltip"
                                    :icon="$itemIcon"
                                    :should-open-url-in-new-tab="$shouldItemOpenUrlInNewTab"
                                    :url="$itemUrl"
                                >
                                    {{ $item->getLabel() }}
                                </x-filament-panels::topbar.item>
                            @endforeach
                        @endif
                    @endforeach
                </ul>
            @endif
        @endif

        <div
            @if ($hasTenancy)
                x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
            @else
                x-persist="topbar.end.panel-{{ filament()->getId() }}"
            @endif
            class="fi-topbar-end"
        >
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === \Filament\Enums\GlobalSearchPosition::Topbar)
                @livewire(Filament\Livewire\GlobalSearch::class)
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

            @if (filament()->auth()->check())
                @if (filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === \Filament\Enums\DatabaseNotificationsPosition::Topbar)
                    @livewire(Filament\Livewire\DatabaseNotifications::class, [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                @if (filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Topbar)
                    <x-filament-panels::user-menu />
                @endif
            @endif
        </div>

*** End Patch