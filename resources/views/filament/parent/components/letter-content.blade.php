<div class="space-y-4 p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            {{ $tenant->name }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            <strong>{{ $letter->title }}</strong> (Version {{ $letter->version }})
        </p>
    </div>

    <div class="prose dark:prose-invert max-w-none">
        {!! $letter->content !!}
    </div>
</div>
