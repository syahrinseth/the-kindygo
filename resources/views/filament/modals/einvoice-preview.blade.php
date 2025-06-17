<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-3">E-Invoice Data Preview</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            This is the data structure that will be sent to LHDN e-Invoice system.
        </p>
        
        <div class="bg-white dark:bg-gray-900 rounded border p-3">
            <pre class="text-xs overflow-x-auto whitespace-pre-wrap"><code>{{ json_encode($data, JSON_PRETTY_PRINT) }}</code></pre>
        </div>
    </div>
    
    <div class="border-t pt-4">
        <h4 class="font-medium mb-2">Key Information:</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium">Invoice ID:</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $data['Invoice']['ID'] ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="font-medium">Issue Date:</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $data['Invoice']['IssueDate'] ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="font-medium">Due Date:</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $data['Invoice']['DueDate'] ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="font-medium">Currency:</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $data['Invoice']['DocumentCurrencyCode'] ?? 'N/A' }}</span>
            </div>
        </div>
    </div>
</div>
