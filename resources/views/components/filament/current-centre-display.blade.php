@php
    $user = Auth::user();
    $currentCentre = $user?->currentCentre;
@endphp

@if ($currentCentre)
    <div class="px-2 py-1 text-sm font-medium bg-amber-100 text-amber-800 rounded-md">
        {{ $currentCentre->name }}
    </div>
@endif
