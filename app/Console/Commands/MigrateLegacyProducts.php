<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use App\Services\Migration\OrphanLogger;
use App\Services\Migration\StatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateLegacyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-products
                            {--dry-run : Run without making changes}
                            {--chunk=500 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}
                            {--skip-existing : Skip products already migrated (faster re-runs)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy products (1_product → products, product_prices, product_centre)';

    /**
     * Track used product codes to avoid duplicates within the batch.
     *
     * @var array<string>
     */
    private array $usedCodes = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $tenantId = (int) $this->option('tenant-id');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy products migration...');

        // Step 1: Migrate products
        $result = $this->migrateProducts($tenantId, $chunkSize, $dryRun);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $this->newLine();
        $this->info('Legacy products migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Migrate legacy products and all related data.
     */
    private function migrateProducts(int $tenantId, int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Products ---');

        $logger = new MigrationLogger('phase_2c', '1_product', 'products');
        $skipExisting = $this->option('skip-existing');

        // Pre-load existing product IDs if skipping
        $existingProductIds = $skipExisting ? DB::table('products')->pluck('id')->toArray() : [];

        // Pre-load existing product codes to avoid duplicates
        $this->usedCodes = DB::table('products')->pluck('code')->toArray();

        $totalCount = DB::connection('legacy')
            ->table('1_product')
            ->whereNull('deleted_at')
            ->count();

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy products to migrate.");

        if ($skipExisting && count($existingProductIds) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingProductIds).' already-migrated products.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        // Pre-load valid centre IDs for validation
        $validCentreIds = DB::table('centres')->pluck('id')->toArray();

        $pricesCreated = 0;
        $centresLinked = 0;

        DB::connection('legacy')
            ->table('1_product')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($chunkSize, function ($legacyProducts) use ($tenantId, $dryRun, $logger, $bar, $validCentreIds, $skipExisting, $existingProductIds, &$pricesCreated, &$centresLinked) {
                foreach ($legacyProducts as $legacy) {
                    try {
                        if ($skipExisting && in_array($legacy->id, $existingProductIds)) {
                            $logger->incrementSkipped();
                            $bar->advance();

                            continue;
                        }

                        $counts = $this->migrateProduct($legacy, $tenantId, $dryRun, $validCentreIds);
                        $pricesCreated += $counts['prices'];
                        $centresLinked += $counts['centres'];
                        $logger->incrementMigrated();
                    } catch (\Exception $e) {
                        $logger->logError("Product {$legacy->id} ({$legacy->name}): {$e->getMessage()}", $legacy->id);
                        $this->newLine();
                        $this->error("Error migrating product {$legacy->id}: {$e->getMessage()}");
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $logger->complete();
        $this->newLine();

        $log = $logger->getLog();
        $this->table(['Metric', 'Count'], [
            ['Source', $log->total_source],
            ['Migrated', $log->total_migrated],
            ['Skipped', $log->total_skipped],
            ['Errors', $log->total_errors],
            ['Prices Created', $pricesCreated],
            ['Centre Links', $centresLinked],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Migrate a single legacy product and all related data.
     *
     * @param  array<int>  $validCentreIds
     * @return array{prices: int, centres: int}
     */
    private function migrateProduct(object $legacy, int $tenantId, bool $dryRun, array $validCentreIds): array
    {
        $counts = ['prices' => 0, 'centres' => 0];

        // Generate a unique product code from the name
        $code = $this->generateUniqueCode($legacy->name, $legacy->id);

        // Map product type
        $productType = StatusMapper::productType((int) $legacy->product_type);

        // Map status
        $status = $legacy->status === 'active' ? 'active' : 'inactive';

        // Determine priority based on product type
        $priority = $productType->getDefaultPriority()->value;

        // 1. Insert/update products table
        $productData = [
            'id' => $legacy->id,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $legacy->name,
            'description' => $legacy->remarks ?: null,
            'status' => $status,
            'type' => $productType->value,
            'priority' => $priority,
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at ?? now(),
        ];

        if (! $dryRun) {
            DB::table('products')->updateOrInsert(
                ['id' => $legacy->id],
                $productData
            );
        }

        // 2. Create product_prices from current price and price_history
        $counts['prices'] = $this->migrateProductPrices($legacy, $dryRun);

        // 3. Create product_centre pivot records
        $counts['centres'] = $this->migrateProductCentres($legacy, $dryRun, $validCentreIds);

        return $counts;
    }

    /**
     * Generate a unique product code from the product name.
     */
    private function generateUniqueCode(string $name, int $productId): string
    {
        // Generate base code: uppercase slug from name, max 20 chars
        $baseCode = Str::upper(Str::slug($name, '-'));

        // Truncate to reasonable length
        if (strlen($baseCode) > 20) {
            $baseCode = substr($baseCode, 0, 20);
        }

        // If empty after slug, use fallback
        if (empty($baseCode)) {
            $baseCode = 'PROD';
        }

        // Ensure uniqueness
        $code = $baseCode;
        $suffix = 1;

        while (in_array($code, $this->usedCodes)) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        $this->usedCodes[] = $code;

        return $code;
    }

    /**
     * Migrate product prices from current price and price_history JSON.
     */
    private function migrateProductPrices(object $legacy, bool $dryRun): int
    {
        $count = 0;

        // Collect all price entries: current price + history
        $priceEntries = [];

        // Parse price_history JSON
        if (! empty($legacy->price_history) && $legacy->price_history !== '[]') {
            $history = json_decode($legacy->price_history, true);
            if (is_array($history)) {
                foreach ($history as $entry) {
                    if (isset($entry['year']) && isset($entry['price'])) {
                        $year = (int) $entry['year'];
                        $price = (int) $entry['price'];

                        // Price is in whole units (RM), convert to cents
                        $priceEntries[$year] = $price * 100;
                    }
                }
            }
        }

        // Add current price if it has a year and isn't already covered
        if ($legacy->price > 0 && ! empty($legacy->year)) {
            $currentYear = (int) $legacy->year;
            // Current price also in whole units, convert to cents
            $priceEntries[$currentYear] = (int) $legacy->price * 100;
        }

        // Sort by year ascending
        ksort($priceEntries);

        if ($dryRun) {
            return count($priceEntries);
        }

        // Create product_prices entries with start_date as Jan 1 of each year
        $years = array_keys($priceEntries);

        foreach ($priceEntries as $year => $priceInCents) {
            $startDate = "{$year}-01-01";

            // Set end_date as Dec 31 of that year (last day before next year's price kicks in)
            // For the last entry, leave end_date null
            $yearIndex = array_search($year, $years);
            $endDate = ($yearIndex < count($years) - 1) ? "{$year}-12-31" : null;

            DB::table('product_prices')->updateOrInsert(
                [
                    'product_id' => $legacy->id,
                    'start_date' => $startDate,
                ],
                [
                    'price' => $priceInCents,
                    'end_date' => $endDate,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at ?? now(),
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Migrate product-centre relationships from preschool JSON field.
     *
     * @param  array<int>  $validCentreIds
     */
    private function migrateProductCentres(object $legacy, bool $dryRun, array $validCentreIds): int
    {
        $count = 0;

        if (empty($legacy->preschool) || $legacy->preschool === '[]') {
            return 0;
        }

        $preschoolIds = json_decode($legacy->preschool, true);
        if (! is_array($preschoolIds)) {
            return 0;
        }

        foreach ($preschoolIds as $preschoolId) {
            $centreId = (int) $preschoolId;

            if ($centreId <= 0) {
                continue;
            }

            if (! in_array($centreId, $validCentreIds)) {
                OrphanLogger::log(
                    '1_product',
                    $legacy->id,
                    "centre_id {$centreId} not found in centres table",
                    ['product_id' => $legacy->id, 'centre_id' => $centreId]
                );

                continue;
            }

            if (! $dryRun) {
                DB::table('product_centre')->updateOrInsert(
                    [
                        'product_id' => $legacy->id,
                        'centre_id' => $centreId,
                    ],
                    [
                        'created_at' => $legacy->created_at ?? now(),
                        'updated_at' => $legacy->updated_at ?? now(),
                    ]
                );
            }

            $count++;
        }

        return $count;
    }
}
