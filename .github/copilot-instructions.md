<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.27
- filament/filament (FILAMENT) - v3
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/telescope (TELESCOPE) - v5
- livewire/livewire (LIVEWIRE) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.


=== filament/core rules ===

## Filament
- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan
- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features
- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships
- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>


## Testing
- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>


=== filament/v3 rules ===

## Filament 3

## Version 3 Changes To Focus On
- Resources are located in `app/Filament/Resources/` directory.
- Resource pages (List, Create, Edit) are auto-generated within the resource's directory - e.g., `app/Filament/Resources/PostResource/Pages/`.
- Forms use the `Forms\Components` namespace for form fields.
- Tables use the `Tables\Columns` namespace for table columns.
- A new `Filament\Forms\Components\RichEditor` component is available.
- Form and table schemas now use fluent method chaining.
- Added `php artisan filament:optimize` command for production optimization.
- Requires implementing `FilamentUser` contract for production access control.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== .ai/feature-domain rules ===

# Feature Domains

- Always create an API for each new feature or route introduces
- Implement versioning for API
- Only create new API version if told to do so or updated in this document.
- Starts with v1 or V1 and proper prefixing.


=== .ai/markdown rules ===

# Markdown Format and Guidelines

- Please, use the right formating and structure when preparing or updating markdown files.
- Follow proper content structure for markdown linting, such as start with heading 1.


=== .ai/migration rules ===

# Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

Use this as a checklist and playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

## Goals / success criteria

- Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
- Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
- Minimize downtime and provide a clear rollback strategy.
- Keep the codebase ready for an easier upgrade to Filament v4.
- Delete all non-prefix tables
- Tenant data has prefix 1_*.
- Implement table rename to proper plural to meet Laravel's magic requirements.
- Update and rename Foreign Keys to meet new naming changes.

## Contract (inputs / outputs / error modes)

- Inputs:
  - Current codebase & migrations (legacy project).
  - Database dumps (central and per-tenant if multi-db tenancy).
  - Knowledge of tenancy package used (e.g., stancl/tenancy, tenancy/tenancy, custom).
- Outputs:
  - New migrations that move/rename/transform tables/columns safely.
  - Updated Eloquent models, factories, Filament resources.
  - Small test-suite proving migration success for both central and tenant data.
- Error modes / failure cases:
  - Missing or incomplete backups (Mitigation: stop and require full backups).
  - Data loss from destructive changes (Mitigation: use copy-and-swap migrations, staging verification, and chunked backfills).

## Assumptions

- You can run migrations in a staging environment first.
- You have SQL backups for central and tenant DBs and can restore them.
- The tenancy topology (single DB with tenant_id vs multi-DB per tenant) is known or detectable.

## High-level plan

1. Inventory & backups
2. Map schema differences and design non-destructive migrations
3. Implement reversible migration files + backfill jobs
4. Add an artisan runner (or use tenancy package helpers) to run tenant migrations
5. Update models, Filament resources, Livewire components
6. Write tests (Pest) and run verification
7. Staged rollout (staging → canary tenants → production)

---

## 1) Inventory & backups

- Export schema and a small data sample for every table (central + tenants).
- Identify tenancy pattern:
  - Single DB with tenant_id columns? — then migrations apply globally but must be tenant-aware.
  - Multi-DB per tenant? — then migrations must be executed per-connection (per-tenant).
- Take full backups for central and each tenant DB. Verify restores in staging.
- Create a mapping doc: old_table => new_table (or keep), old_column => new_column, cast/type changes.

## 2) Schema mapping & non-destructive prototyping

- Prefer add-column/copy-backfill/drop-old (copy-and-swap) rather than in-place destructive changes.
- For each change record:
  - Reason for change, backward-compatibility plan (dual-read/dual-write window), and backfill approach.
- Handle special cases explicitly: pivot tables, polymorphic relations, JSON columns, enum replacements.

## 3) Reversible migrations and backfills

- Migration A (add): add the new column/table (nullable) and keep old field.
- Backfill job: a chunked queued job or artisan command that copies existing values into new columns.
- Dual-write deployment: update app code to write to both old and new columns until cutover.
- Migration B (swap): after verification, make new column NOT NULL and drop the old column in a reversible migration.

Notes:

- Use transactions where possible but avoid long-running locks on large tables — prefer chunked background jobs.
- For huge tables, backfill with indexed filters or add temporary indexing to speed up copy jobs.

## 4) Tenant-aware execution

- If your tenancy package provides a helper to run migrations across tenants, use it (safer and battle-tested).
- Otherwise implement an artisan command like `tenants:migrate` that iterates tenant connections and runs migrations and backfill jobs per-tenant. Log progress and failures.

Example (pseudocode):

- For each tenant:
  - Switch DB connection to tenant
  - Run `php artisan migrate --path=database/migrations/tenants` (or run specific migrations)
  - Dispatch backfill jobs scoped to the tenant

## 5) Code updates for Filament v3

- Models:
  - Update casts via `casts()` method (Laravel 12 style) and add new accessors/mutators for compatibility.
  - Implement Filament-related contracts (e.g., FilamentUser) if required.
- Filament resources:
  - Resources live in `app/Filament/Resources/YourModelResource`.
  - Update forms/tables to use the new fields and relationships.
- Livewire / Frontend:
  - If using Livewire v3, adapt `wire:model.live` as needed and confirm component namespaces.

## 6) Tests & verification

- Write Pest tests covering:
  - Migration happy path: migration + backfill produces expected schema & sample rows migrated.
  - Filament resource smoke test: Livewire test for list/create/edit flows against migrated model.
  - Tenant runner: run a test tenant migration on a fixture DB.
- Quality gates:
  - Build: app boots with new migrations applied in staging.
  - Lint/Format: run `vendor/bin/pint` and fix issues.
  - Tests: pass targeted Pest tests.

Commands to try locally (examples):

```zsh
vendor/bin/pint
php artisan test --filter=YourMigrationTest
php artisan migrate --path=database/migrations/2025_xxx_add_new_columns.php --seed
```

## 7) Staged rollout & monitoring

- Staging: run full migration on staging with production-like data and run tests and manual QA.
- Canary tenants: pick a small subset of tenants for initial production run.
- Production: perform migration during a maintenance window if final cutover is disruptive.
- Monitoring: after migration run checksums, row counts, sample record comparisons, and error log monitoring.

## Rollback plan

- Keep old columns in place until you verify migration results. That allows quick rollback: revert code to use old columns and drop any partial new indexes.
- For destructive operations, ensure a tested restore-from-backup runbook is available.

## Filament v4 considerations (prepare now)

- Keep business logic out of Filament resources; keep it in models/services so UI changes are isolated.
- Avoid tight coupling to Filament helpers/APIs that may change between v3 → v4.
- Add small compatibility shims where necessary rather than broad, risky refactors.

## Practical tips & gotchas

- Large tables: chunk backfills and consider temporary indexes.
- Foreign keys: plan FK changes carefully (drop/recreate order) and verify constraints after migration.
- Enums: prefer PHP Enums in `app/Enums` and keep numeric values stable.

## Next actions I can take for you

1. (A) Inspect this repository to detect the tenancy package, current migrations, and propose concrete migration skeletons.
2. (B) Create example reversible migration files + a `tenants:migrate` artisan command scaffold for your tenancy topology.

Tell me which you want (A or B) and I will proceed: for A I'll scan the repo and produce a concrete plan; for B I'll create migration & command stubs and a couple of Pest tests.

## Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

Use this as a checklist and a playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

### Goals / success criteria

- Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
- Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
- Minimize downtime and provide rollback steps.
- Keep the codebase ready for an easier upgrade to Filament v4.

### Contract (inputs / outputs / error modes)

- Inputs:

  # Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

  This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

  Use this as a checklist and a playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

  ## Goals / success criteria

  - Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
  - Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
  - Minimize downtime and provide rollback steps.
  - Keep the codebase ready for an easier upgrade to Filament v4.

  ## Contract (inputs / outputs / error modes)

  - Inputs:
    - Current codebase & migrations (legacy project).
    - Database dumps (central and per-tenant if multi-db tenancy).
    - Knowledge of tenancy package used (e.g., stancl/tenancy, hyn, custom).
    - Remove non-prefix tables
    - All usefull data is located in table with 1_* prefix
  - Outputs:
    - New migrations that move/rename/transform tables/columns safely.
    - Updated Eloquent models, factories, and Filament resources.
  - Error modes / failure cases:
    - Missing backups or partial tenant dumps. (Mitigation: refuse action until full backups present.)
    - Data loss from destructive migrations. (Mitigation: run migrations in staging, ensure backups, use copy-and-swap strategy.)

  # Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

  This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

  Use this as a checklist and a playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

  ## Goals / success criteria

  - Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
  - Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
  - Minimize downtime and provide rollback steps.
  - Keep the codebase ready for an easier upgrade to Filament v4.

  ## Contract (inputs / outputs / error modes)

  - Inputs:
    - Current codebase & migrations (legacy project).
    - Database dumps (central and per-tenant if multi-db tenancy).
    - Knowledge of tenancy package used (e.g., stancl/tenancy, hyn, custom).
  - Outputs:
    - New migrations that move/rename/transform tables/columns safely.
    - Updated Eloquent models, factories, and Filament resources.
  - Error modes / failure cases:
    - Missing backups or partial tenant dumps. (Mitigation: refuse action until full backups present.)
    - Data loss from destructive migrations. (Mitigation: run migrations in staging, ensure backups, use copy-and-swap strategy.)

  ## Assumptions (make these explicit before starting)

  - You can take a maintenance window if some operations require downtime (or have a migration strategy that supports online migration).
  - You have SQL backups for central and all tenant DBs and can restore to a staging environment.
  - The tenancy package and version (or custom solution) is known — migration approach depends on whether you use separate DB per tenant or single DB with tenant_id columns.
  - The project uses Filament v3 now (or is being migrated to v3). If currently on Filament v2 or earlier, increase the scope to include Filament compatibility changes.

  ## High-level plan (ordered)

  1. Inventory & backups
  2. Schema mapping & non-destructive prototyping
  3. Create reversible migrations + migration runner for tenants
  4. Code updates (models, resources, policies, Filament configs)
  5. Tests & verification
  6. Staged rollout (staging → canary → production)
  7. Post-migration follow-up and v4 prep

  ---

  ## 1) Inventory & backups

  - Inventory DB structure: list all tables, columns, indexes, FK constraints, triggers, views, and stored procedures. Export schema and a data sample for each table.
  - Identify tenancy model:
    - Multi-database (one DB per tenant)? Or single database with tenant_id on tables?
    - Which package or custom code implements tenancy? (e.g., stancl/tenancy, tenancy/tenancy, hyn).
  - Take full backups: central DB and all tenant DBs. Keep copies off-host and verify restores in staging.
  - Create a short mapping document: old_table → new_table (if rename), columns that will be dropped/renamed/type-changed.

  Tip: Use tools like mysqldump/pg_dump for dumps, and create checksums on rows before/after migration to validate data integrity.

  ## 2) Schema mapping & prototyping (non-destructive)

  - Avoid in-place destructive schema changes. Prefer add-column/copy data then drop-old approach.
  - Create a directory for migration helpers (if not present): `database/migration-scripts` or a small artisan command to run tenant migrations.
  - Typical mapping tasks:
    - Rename tables to Filament-conventional names (or keep existing and adapt resources). Filament doesn't require specific table names, but models & resource code should match current tables.
    - Move polymorphic relations, pivot tables, and JSON columns carefully: ensure JSON casting in models.
    - Convert enum-like tinyints/strings into PHP Enum classes in `app/Enums` and update migrations to preserve values.
  - Prototype in a staging environment: run new migrations against a copy of production data and test the app for critical flows.

  ## 3) Create reversible migrations and tenant migration runner

  - Migration style guidance:
    - Always write migrations that can be rolled back or, if destructive, accompany them with a copy strategy: create new columns/tables, copy data, switch code to the new columns, and only then drop old columns after a safe delay.
    - Use explicit SQL for heavy data transforms to improve performance and clarity.
  - Example safe pattern for renaming a column:
    1. Migration A: add new column `new_col` (nullable), leaving `old_col` intact.
    2. A queued job or artisan command to backfill `new_col` from `old_col` for existing rows (use chunking).
    3. Deploy code that writes both `old_col` and `new_col` (dual-write) for a short window.
    4. Migration B (after verification): set `new_col` not-null and drop `old_col`.
  - Tenant migrations:
    - If single DB with tenant_id: normal migrations apply; but data transforms must be tenant-aware.
    - If multi-DB per tenant: you need to run migration per tenant DB. Implement an artisan command that iterates tenant list and runs migrations/seeders on each connection.
    - If your tenancy package provides a helper to run migrations across tenants, prefer it. If custom, create safe automation with logging and error handling.

  ## 4) Code updates: models, Filament resources & Livewire

  - Models:
    - Add or update casts via the `casts()` method as per Laravel 12 conventions.
    - Implement any new interfaces Filament requires (e.g., FilamentUser or other contracts) and ensure policies align.
    - Add helper accessors/mutators to smooth the transition between old/new column names during dual-write phase.
  - Filament specifics (v3):
    - Resources live under `app/Filament/Resources/YourModelResource` — check naming & field components.
    - Ensure `Forms\Components` and `Tables\Columns` usage matches v3 API.
    - If your users or admin model shape changed, update resource forms/tables accordingly.
  - Livewire & frontend:
    - If you use Livewire v3, update wiring (e.g., `wire:model.live` where needed) and ensure component namespaces are correct.

  ## 5) Tests & verification

  - Add migration tests (Pest) that run on a copy of production schema (or a smaller sample) to validate transforms.
  - Add integration tests that:
    - Create a tenant, seed with sample data, run tenant migrations, and assert expected tables/columns/data exist.
    - Use `Livewire::test()` for Filament pages to assert basic CRUD operations.
  - Verification checklist (quality gates):
    - Build: PASS (app boots, composer autoload generated)
    - Lint/Format: run `vendor/bin/pint` (format) — ensure PASS
    - Tests: run the subset of tests touching migration & Filament functionality. Ensure PASS.

  Commands to run locally (examples):

  ```bash
  # run formatting
  vendor/bin/pint

  # run a subset of pest tests
  php artisan test --filter=Migration
  ```

  Note: run the minimal set of tests relevant to changes during iteration. When ready, run the broader suite.

  ## 6) Staged rollout & monitoring

  - Staging: run full migration on staging with production-sized dataset and run tests and manual QA. Validate data using checksums.
  - Canary/Canary tenants: pick a small set of tenants and run migration there first.
  - Production: run migrations during a maintenance window or use zero-downtime approach with dual-read/write and a final cutover.
  - Monitoring: set up quick queries that check row counts and a few sample rows before/after migration; alert on discrepancy.

  ## 7) Rollback plan

  - If migration uses copy-and-swap: rollback is to keep serving old columns until you are sure; you can remove new columns if required and resume the old code paths.
  - For destructive one-shot changes: ensure you have tested DB backups and quick restore playbook; test the restore in staging beforehand.

  ## Filament v4 considerations (keep in mind now)

  - Keep Filament resource logic isolated and small. Avoid embedding heavy migration transforms inside resource classes.
  - Filament v4 may change resource APIs or folder layout; decouple business logic into model methods and services so UI layer can be upgraded with minimal DB changes.
  - Keep Livewire/Alpine interactions minimal and use server-side validation and policies.

  ## Example migration checklist (concrete)

  1. Backup central DB and all tenant DBs (verify restore).
  2. Freeze non-essential writes or put site into read-only mode for a short window if necessary.
  3. Run prototype migration in staging and run tests.
  4. Deploy code that supports dual-write for renamed columns (if required).
  5. Run data-copy migration jobs to fill new schema.
  6. Validate data with checksums and spot checks.
  7. Switch code to read from new columns.
  8. After 1–2 days of verification, drop old columns in a final migration.

  ## Practical tips & gotchas

  - Large tables: perform backfills in chunks to avoid long locks or high memory usage. Use DB-native tools (pt-online-schema-change) if needed.
  - Foreign keys: drop/recreate foreign keys in careful order; consider using disable/enable constraints around large transforms where supported.
  - Enums & constants: create PHP Enum classes early and keep integer values stable to avoid subtle bugs.
  - Tenants with custom data variations: test tenant-specific edge cases (custom columns, missing fields).

  ## Verification & quality gate summary

  - Build: app boots with new migrations applied in staging.
  - Lint/Format: `vendor/bin/pint` -> PASS
  - Tests: relevant Pest tests -> PASS
  - Manual QA: Filament admin resource pages load and basic CRUD flows work.

  ## Next steps / recommended actions

  1. Confirm the tenancy package & DB topology used in the project. Note that plan depends heavily on this.
  2. Create `database/migrations/XXXX_add_new_columns_for_migration.php` migration skeletons using the copy-and-swap pattern for each major table.
  3. Create an artisan command `php artisan tenants:migrate` (if multi-db) or adapt your tenancy package runner to handle tenant migrations safely.
  4. Add a small set of Pest tests that run migration steps on a sample tenant and assert the new schema/data.

  If you'd like, I can:

  - (A) Inspect the repo and detect the tenancy package and current migration state and then generate concrete migration skeletons and tenant migration command.
  - (B) Create example reversible migration files and a sample `tenants:migrate` artisan command tailored to your tenancy package.

  Tell me which next step you want (A or B) and I'll continue.


=== .ai/tests rules ===

# Tests Directive

- Always create tests for each feature, and group them by domain
- Always prepare test for Feature, API and Browser
- Use mocking for any external request
- Always prepare any migration to support both SQLite and MariaDB/MySQL syntax so during test we can use SQLite in memory.
</laravel-boost-guidelines>
