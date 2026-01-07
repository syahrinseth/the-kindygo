<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            @foreach($this->getMissingItems() as $item)
                <div class="flex items-start space-x-4 p-4 rounded-lg border border-yellow-200 bg-yellow-50">
                    <div class="flex-shrink-0">
                        <x-filament::icon
                            :icon="$item['icon']"
                            class="w-6 h-6 text-yellow-600"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-900">
                            {{ $item['title'] }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $item['description'] }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        @if($item['action'] === 'addChild')
                            {{ ($this->addChildAction)(['item' => $item]) }}
                        @else
                            {{ ($this->{$item['action'] . 'Action'})(['item' => $item]) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
