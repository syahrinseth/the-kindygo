<div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
    <div class="flex items-start space-x-3">
        <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
        </svg>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700 mb-1">Selected Centres (Locked)</p>
            <div class="flex flex-wrap gap-2">
                @foreach(explode(', ', $centres) as $centre)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $centre }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</div>
