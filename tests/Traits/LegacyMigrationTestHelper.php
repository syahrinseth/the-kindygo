<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sets up legacy database tables in the test SQLite database,
 * redirecting the 'legacy' connection to use the default connection.
 */
trait LegacyMigrationTestHelper
{
    /**
     * Set up the legacy connection and tables for migration testing.
     *
     * The trick: We point the 'legacy' connection config to the same SQLite
     * settings, then purge the cached connection so Laravel creates a fresh
     * PDO instance. After that, we share the default connection's PDO with
     * the legacy connection so both operate on the same in-memory database.
     */
    protected function setUpLegacyDatabase(): void
    {
        // Copy the default sqlite connection config to 'legacy'
        config(['database.connections.legacy' => config('database.connections.sqlite')]);

        // Purge any existing cached 'legacy' connection
        DB::purge('legacy');

        // Share the same PDO instance so both connections use the same in-memory DB
        $defaultPdo = DB::connection()->getPdo();
        DB::connection('legacy')->setPdo($defaultPdo);

        $this->createLegacyTables();
        $this->resetLegacyTables();
    }

    /**
     * Clear legacy fixtures before each test while keeping the shared schema intact.
     */
    protected function resetLegacyTables(): void
    {
        foreach ([
            '1_transactions',
            '1_invoices',
            '1_child',
            '1_product',
            '1_model_has_roles',
            '1_users',
            '1_roles',
            '1_preschool',
            '1_campuses',
            '1_state',
        ] as $table) {
            DB::connection('legacy')->table($table)->delete();
        }
    }

    /**
     * Create all legacy tables needed for migration commands.
     */
    protected function createLegacyTables(): void
    {
        $this->createLegacyCampusesTable();
        $this->createLegacyPreschoolTable();
        $this->createLegacyRolesTable();
        $this->createLegacyModelHasRolesTable();
        $this->createLegacyUsersTable();
        $this->createLegacyProductTable();
        $this->createLegacyChildTable();
        $this->createLegacyStateTable();
        $this->createLegacyInvoicesTable();
        $this->createLegacyTransactionsTable();
    }

    protected function createLegacyCampusesTable(): void
    {
        if (Schema::hasTable('1_campuses')) {
            return;
        }

        Schema::create('1_campuses', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('no_phone')->nullable();
            $table->string('add_1')->nullable();
            $table->string('add_2')->nullable();
            $table->string('postcode')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLegacyPreschoolTable(): void
    {
        if (Schema::hasTable('1_preschool')) {
            return;
        }

        Schema::create('1_preschool', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->integer('campus_id')->default(0);
            $table->string('status')->default('active');
            $table->string('no_phone')->nullable();
            $table->string('add_1')->nullable();
            $table->string('add_2')->nullable();
            $table->string('postcode')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('ssm_comp_name')->nullable();
            $table->string('ssm_no')->nullable();
            $table->integer('capacity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLegacyRolesTable(): void
    {
        if (Schema::hasTable('1_roles')) {
            return;
        }

        Schema::create('1_roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });
    }

    protected function createLegacyModelHasRolesTable(): void
    {
        if (Schema::hasTable('1_model_has_roles')) {
            return;
        }

        Schema::create('1_model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
    }

    protected function createLegacyUsersTable(): void
    {
        if (Schema::hasTable('1_users')) {
            return;
        }

        Schema::create('1_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('ic_no')->nullable();
            $table->string('phone_no')->nullable();
            $table->string('occupation')->nullable();
            $table->integer('user_status')->default(1);
            $table->integer('preschool')->default(0);
            $table->text('other_preschools')->nullable();
            $table->string('add_1')->nullable();
            $table->string('add_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('state')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_add_1')->nullable();
            $table->string('company_add_2')->nullable();
            $table->string('company_city')->nullable();
            $table->string('company_postcode')->nullable();
            $table->string('company_state')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_ic_no')->nullable();
            $table->string('spouse_phone_no')->nullable();
            $table->string('spouse_email')->nullable();
            $table->string('spouse_occupation')->nullable();
            $table->string('spouse_add_1')->nullable();
            $table->string('spouse_add_2')->nullable();
            $table->string('spouse_city')->nullable();
            $table->string('spouse_postcode')->nullable();
            $table->string('spouse_state')->nullable();
            $table->string('spouse_company_add_1')->nullable();
            $table->string('spouse_company_add_2')->nullable();
            $table->string('spouse_company_city')->nullable();
            $table->string('spouse_company_postcode')->nullable();
            $table->string('spouse_company_state')->nullable();
            $table->text('discount_by_month')->nullable();
            $table->string('discount_by_month_amount')->nullable();
            $table->string('discount_by_month_reason')->nullable();
            $table->text('discount_by_month_year')->nullable();
            $table->string('monthly_discount_amount')->nullable();
            $table->string('monthly_discount_reason')->nullable();
            $table->text('discount_histories')->nullable();
            $table->text('guardians')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLegacyProductTable(): void
    {
        if (Schema::hasTable('1_product')) {
            return;
        }

        Schema::create('1_product', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('product_type')->default(1);
            $table->string('status')->default('active');
            $table->integer('price')->default(0);
            $table->integer('year')->nullable();
            $table->text('price_history')->nullable();
            $table->text('preschool')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLegacyChildTable(): void
    {
        if (Schema::hasTable('1_child')) {
            return;
        }

        Schema::create('1_child', function ($table) {
            $table->id();
            $table->string('fullname');
            $table->date('dob')->nullable();
            $table->integer('gender')->nullable();
            $table->integer('race')->nullable();
            $table->integer('religion')->nullable();
            $table->integer('parent_id')->default(0);
            $table->integer('preschool_id')->default(0);
            $table->integer('product')->default(0);
            $table->text('other_products')->nullable();
            $table->integer('status')->default(1);
            $table->string('is_registered')->nullable();
            // Production columns (matching legacy DB schema)
            $table->string('mykid_no')->nullable();
            $table->string('cert_no')->nullable();
            $table->string('pob')->nullable();
            $table->integer('post_of_child')->nullable();
            $table->string('family_clinic')->nullable();
            $table->string('family_clinic_phone')->nullable();
            $table->longText('languages')->nullable();
            $table->string('allergies')->nullable();
            $table->longText('diseases')->nullable();
            $table->string('type')->nullable();
            $table->integer('language')->nullable(); // old int column
            $table->integer('classroom_id')->nullable();
            $table->integer('year')->nullable();
            $table->integer('december_product_id')->nullable();
            $table->string('email')->nullable();
            $table->string('passport_sized_image')->nullable();
            $table->string('immunization_card')->nullable();
            $table->string('child_birth_certificate')->nullable();
            $table->integer('alumni')->nullable();
            $table->integer('discount')->nullable();
            $table->text('others')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLegacyStateTable(): void
    {
        if (Schema::hasTable('1_state')) {
            return;
        }

        Schema::create('1_state', function ($table) {
            $table->id();
            $table->string('name');
        });

        // Seed the 16 Malaysian states
        DB::table('1_state')->insert([
            ['id' => 1, 'name' => 'Johor'],
            ['id' => 2, 'name' => 'Kedah'],
            ['id' => 3, 'name' => 'Kelantan'],
            ['id' => 4, 'name' => 'Melaka'],
            ['id' => 5, 'name' => 'Negeri Sembilan'],
            ['id' => 6, 'name' => 'Pahang'],
            ['id' => 7, 'name' => 'Pulau Pinang'],
            ['id' => 8, 'name' => 'Perak'],
            ['id' => 9, 'name' => 'Perlis'],
            ['id' => 10, 'name' => 'Selangor'],
            ['id' => 11, 'name' => 'Terengganu'],
            ['id' => 12, 'name' => 'WP Kuala Lumpur'],
            ['id' => 13, 'name' => 'WP Labuan'],
            ['id' => 14, 'name' => 'WP Putrajaya'],
            ['id' => 15, 'name' => 'Sabah'],
            ['id' => 16, 'name' => 'Sarawak'],
        ]);
    }

    protected function createLegacyInvoicesTable(): void
    {
        if (Schema::hasTable('1_invoices')) {
            return;
        }

        Schema::create('1_invoices', function ($table) {
            $table->id();
            $table->integer('parent')->default(0);
            $table->integer('preschool')->default(0);
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('payment_status')->default(1);
            $table->integer('deposit')->default(0);
            $table->integer('is_pos_invoice')->default(0);
            $table->string('billplz_collection_id')->nullable();
            $table->string('billplz_bill_id')->nullable();
            $table->string('billplz_url')->nullable();
            // Production columns
            $table->integer('price')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('child_id')->default(0);
            $table->integer('locked')->default(0);
            $table->string('transaction_id')->nullable();
            $table->integer('payment_method')->default(0);
            $table->integer('immediate')->default(0);
            $table->date('future_date')->nullable();
            $table->string('billplz_pending_bill_id')->nullable();
            $table->string('last_mailgun_message_id')->nullable();
            $table->integer('is_enrollment')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLegacyTransactionsTable(): void
    {
        if (Schema::hasTable('1_transactions')) {
            return;
        }

        Schema::create('1_transactions', function ($table) {
            $table->id();
            $table->integer('invoice_id')->default(0);
            $table->integer('child_id')->default(0);
            $table->integer('product_id')->default(0);
            $table->integer('parent_id')->default(0);
            $table->integer('preschool_id')->default(0);
            $table->string('type')->default('bill');
            // Production columns (matching legacy DB schema)
            $table->string('label')->nullable();
            $table->string('remarks')->nullable();
            $table->integer('amount')->default(0);
            $table->integer('quantity')->default(1);
            $table->integer('payment_method')->default(0);
            $table->integer('paid_status')->default(0);
            $table->integer('paid_amount')->default(0);
            $table->datetime('paid_at')->nullable();
            $table->string('billplz_bill_id')->nullable();
            $table->string('billplz_collection_id')->nullable();
            $table->string('payment_by')->nullable();
            $table->string('payment_slip')->nullable();
            $table->string('reference_id')->nullable();
            $table->integer('discount_amount')->default(0);
            $table->integer('is_discount')->default(0);
            $table->date('bill_date')->nullable();
            $table->integer('prev_invoice_id')->nullable();
            $table->text('waived_products')->nullable();
            $table->string('overtime_stayin')->nullable();
            $table->string('product_type')->nullable();
            $table->string('product_recurring')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Seed legacy campuses with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyCampuses(int $count = 2): array
    {
        $campuses = [];

        for ($i = 1; $i <= $count; $i++) {
            $campus = [
                'id' => $i,
                'name' => "Test Campus {$i}",
                'no_phone' => "01234567{$i}",
                'add_1' => "No. {$i} Campus Street",
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'state' => (string) $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_campuses')->insert($campus);
            $campuses[] = $campus;
        }

        return $campuses;
    }

    /**
     * Seed legacy preschools (centres) with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyPreschools(int $count = 3, int $campusId = 1): array
    {
        $preschools = [];
        $statuses = ['active', 'close', 'licensee'];

        for ($i = 1; $i <= $count; $i++) {
            $preschool = [
                'id' => $i,
                'name' => "Test Preschool {$i}",
                'short_name' => "TP{$i}",
                'campus_id' => $campusId,
                'status' => $statuses[($i - 1) % count($statuses)],
                'no_phone' => "0112345{$i}",
                'add_1' => "No. {$i} Preschool Lane",
                'city' => 'Shah Alam',
                'postcode' => '40000',
                'state' => '10',
                'ssm_comp_name' => "Test SSM Company {$i}",
                'ssm_no' => "SSM-{$i}",
                'capacity' => 50 + $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_preschool')->insert($preschool);
            $preschools[] = $preschool;
        }

        return $preschools;
    }

    /**
     * Seed legacy roles.
     */
    protected function seedLegacyRoles(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Super Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Account', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Principal', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Account Staff', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Teacher', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Parent', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Staff', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'HR', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Application', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Auditor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'name' => 'Owner', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::connection('legacy')->table('1_roles')->insert($roles);
    }

    /**
     * Seed legacy users with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyUsers(int $count = 3, int $preschoolId = 1): array
    {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $user = [
                'id' => $i,
                'name' => "Test User {$i}",
                'email' => "testuser{$i}@example.com",
                'password' => '$2y$10$hashedpassword',
                'email_verified_at' => now(),
                'ic_no' => "990101-01-000{$i}",
                'phone_no' => "0112345{$i}",
                'occupation' => 'Engineer',
                'user_status' => 1,
                'preschool' => $preschoolId,
                'other_preschools' => json_encode([]),
                'add_1' => "No. {$i} User Street",
                'city' => 'Petaling Jaya',
                'postcode' => '47000',
                'state' => '10',
                'company_name' => "Test Company {$i}",
                'company_phone' => "0312345{$i}",
                'company_add_1' => "Suite {$i}",
                'company_city' => 'Kuala Lumpur',
                'company_postcode' => '50000',
                'company_state' => '12',
                'spouse_name' => $i <= 2 ? "Spouse {$i}" : null,
                'spouse_ic_no' => $i <= 2 ? "880101-01-000{$i}" : null,
                'spouse_phone_no' => $i <= 2 ? "0198765{$i}" : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_users')->insert($user);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Seed legacy model_has_roles for test users.
     */
    protected function seedLegacyModelHasRoles(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            DB::connection('legacy')->table('1_model_has_roles')->insert([
                'role_id' => $assignment['role_id'],
                'model_type' => 'App\\User',
                'model_id' => $assignment['model_id'],
            ]);
        }
    }

    /**
     * Seed legacy products with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyProducts(int $count = 3, array $centreIds = [1]): array
    {
        $products = [];
        $types = [1, 2, 6]; // programme, event, service

        for ($i = 1; $i <= $count; $i++) {
            $product = [
                'id' => $i,
                'name' => "Test Product {$i}",
                'product_type' => $types[($i - 1) % count($types)],
                'status' => 'active',
                'price' => 100 + ($i * 50),
                'year' => 2025,
                'price_history' => json_encode([
                    ['year' => 2024, 'price' => 80 + ($i * 50)],
                ]),
                'preschool' => json_encode($centreIds),
                'remarks' => "Test product {$i} remarks",
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_product')->insert($product);
            $products[] = $product;
        }

        return $products;
    }

    /**
     * Seed legacy children with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyChildren(int $count = 3, int $parentId = 1, int $preschoolId = 1, int $productId = 1): array
    {
        $children = [];
        $statuses = [1, 2, 3]; // new, return, alumni

        for ($i = 1; $i <= $count; $i++) {
            $child = [
                'id' => $i,
                'fullname' => "Child Firstname{$i} Lastname{$i}",
                'dob' => '2020-06-'.str_pad($i, 2, '0', STR_PAD_LEFT),
                'gender' => ($i % 2) + 1,
                'race' => 1,
                'religion' => 1,
                'parent_id' => $parentId,
                'preschool_id' => $preschoolId,
                'product' => $productId,
                'other_products' => json_encode([]),
                'status' => $statuses[($i - 1) % count($statuses)],
                'is_registered' => '2024-01-15',
                // Production column names
                'mykid_no' => "MYKID-{$i}",
                'cert_no' => "CERT-{$i}",
                'pob' => 'Kuala Lumpur',
                'post_of_child' => $i,
                'family_clinic' => "Clinic {$i}",
                'family_clinic_phone' => "0312345{$i}",
                'languages' => json_encode(['Malay', 'English']),
                'allergies' => 'Peanuts',
                'diseases' => json_encode([]),
                'type' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_child')->insert($child);
            $children[] = $child;
        }

        return $children;
    }

    /**
     * Seed legacy invoices with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyInvoices(int $count = 3, int $parentId = 1, int $preschoolId = 1): array
    {
        $invoices = [];
        $statuses = [1, 7, 5]; // pending, paid, cancelled

        for ($i = 1; $i <= $count; $i++) {
            $invoice = [
                'id' => $i,
                'parent' => $parentId,
                'preschool' => $preschoolId,
                'invoice_no' => "INV 2025-{$i}",
                'invoice_date' => '2025-01-'.str_pad($i, 2, '0', STR_PAD_LEFT),
                'due_date' => '2025-02-'.str_pad($i, 2, '0', STR_PAD_LEFT),
                'payment_status' => $statuses[($i - 1) % count($statuses)],
                'price' => 30000 * $i,
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-31',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_invoices')->insert($invoice);
            $invoices[] = $invoice;
        }

        return $invoices;
    }

    /**
     * Seed legacy transactions (bills) with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyBills(int $count = 3, int $invoiceId = 1, int $childId = 1, int $productId = 1, int $parentId = 1, int $preschoolId = 1): array
    {
        $bills = [];

        for ($i = 1; $i <= $count; $i++) {
            $amount = 10000 + ($i * 5000);
            $bill = [
                'id' => $i,
                'invoice_id' => $invoiceId,
                'child_id' => $childId,
                'product_id' => $productId,
                'parent_id' => $parentId,
                'preschool_id' => $preschoolId,
                'type' => 'bill',
                'label' => "Bill Item {$i}",
                'remarks' => "Description for bill item {$i}",
                'amount' => $amount,
                'quantity' => 1,
                'discount_amount' => 0,
                'is_discount' => 0,
                'bill_date' => '2025-01-15',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_transactions')->insert($bill);
            $bills[] = $bill;
        }

        return $bills;
    }

    /**
     * Seed legacy transactions (payments) with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyPayments(int $startId = 100, int $count = 2, int $invoiceId = 1, int $parentId = 1, int $preschoolId = 1, int $amount = 15000): array
    {
        $payments = [];

        for ($i = 0; $i < $count; $i++) {
            $id = $startId + $i;
            $payment = [
                'id' => $id,
                'invoice_id' => $invoiceId,
                'child_id' => 0,
                'product_id' => 0,
                'parent_id' => $parentId,
                'preschool_id' => $preschoolId,
                'type' => 'payment',
                'label' => "Payment {$id}",
                'remarks' => "Payment remarks for {$id}",
                'amount' => 0,
                'quantity' => 1,
                'payment_method' => 2,
                'paid_status' => 1,
                'paid_amount' => $amount,
                'paid_at' => now()->toDateTimeString(),
                'payment_by' => 'Online',
                'payment_slip' => "/transactions/bills/payment_slips/{$id}.jpg",
                'reference_id' => "REF-{$id}",
                'billplz_bill_id' => null,
                'billplz_collection_id' => null,
                'discount_amount' => 0,
                'is_discount' => 0,
                'bill_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_transactions')->insert($payment);
            $payments[] = $payment;
        }

        return $payments;
    }

    /**
     * Seed legacy transactions (deposits) with test data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seedLegacyDeposits(int $startId = 200, int $count = 1, int $invoiceId = 1, int $parentId = 1, int $preschoolId = 1, int $amount = 5000): array
    {
        $deposits = [];

        for ($i = 0; $i < $count; $i++) {
            $id = $startId + $i;
            $deposit = [
                'id' => $id,
                'invoice_id' => $invoiceId,
                'child_id' => 0,
                'product_id' => 0,
                'parent_id' => $parentId,
                'preschool_id' => $preschoolId,
                'type' => 'deposit',
                'label' => "Deposit {$id}",
                'remarks' => "Deposit remarks for {$id}",
                'amount' => $amount,
                'quantity' => 1,
                'payment_method' => 2,
                'paid_status' => 1,
                'paid_amount' => 0,
                'paid_at' => now()->toDateTimeString(),
                'payment_by' => 'Counter',
                'payment_slip' => null,
                'reference_id' => "DEP-{$id}",
                'billplz_bill_id' => null,
                'billplz_collection_id' => null,
                'discount_amount' => 0,
                'is_discount' => 0,
                'bill_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::connection('legacy')->table('1_transactions')->insert($deposit);
            $deposits[] = $deposit;
        }

        return $deposits;
    }

    /**
     * Create a tenant record for testing migration (tenant_id = 1).
     */
    protected function createTestTenant(int $tenantId = 1): void
    {
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Tenant Owner',
            'email' => 'owner@test.com',
            'password' => '$2y$10$hashedpassword',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'user_id' => $ownerId,
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
