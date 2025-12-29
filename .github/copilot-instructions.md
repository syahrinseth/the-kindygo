<laravel-boost-guidelines>
=== .ai/02-javascript rules ===

# Using Bun for JavaScript Development

I recommend using Bun.sh to run and bundle JavaScript code for this project. Bun is a fast all-in-one JavaScript runtime, package manager, and bundler that can significantly improve development speed and efficiency. Here are the steps to get started with Bun:

1. For any task that involves running or bundling JavaScript code, use Bun instead of Node.js or other tools.
2. Only use npm or yarn if Bun does not support a specific package or feature required for the task.


=== .ai/03-tests rules ===

# Tests Directive

- Always create tests for each feature, and group them by domain
- Always prepare test for Feature, API and Browser
- Use mocking for any external request
- Always prepare any migration to support both SQLite and MariaDB/MySQL syntax so during test we can use SQLite in memory.


=== .ai/01-markdown rules ===

# Markdown Format and Guidelines

- Please, use the right formating and structure when preparing or updating markdown files.
- Follow proper content structure for markdown linting, such as start with heading 1.


=== .ai/00-feature-domain rules ===

# Feature Domains

- Always create an API for each new feature or route introduces
- Implement versioning for API
- Only create new API version if told to do so or updated in this document.
- Starts with v1 or V1 and proper prefixing.
- All spellings are based ok Malaysian English, which based on British English.

## Feature Structure

- Controller
- Route
- Model
- Action Class
- Service Class
- Filament v4 required files and structures
- Pest v4 test files and structures

## System Tenancy Structure

- 3 main tenancy user types:
  - System Owner - super admin owner, special access to 
    - is_super_admin flag on users table
    - routes:
      - console.{domain}* route for all system admin
      - console.{domain}/ checking for auth sessions, then redirect to /dashboard or /login accordingly
      - console.{domain}/dashboard for system admin dashboard
      - console.{domain}/settings for system settings
      - console.{domain}/reports for system reports
      - console.{domain}/tenants for tenant management
      - console.{domain}/users for all user management
      - console.{domain}/invoices for system invoicing (subscription management for tenants)
      - console.{domain}/payments for system payment management
      - console.{domain}/transactions for system transaction history
      - console.{domain}/finance for system finance dashboard
      - console.{domain}/products for system product management (subscription plans)
      - console.{domain}/features for system feature management (enable/disable features per tenant)
      - console.{domain}/logs for system logs and monitoring
      - console.{domain}/notifications for system-wide notifications
    - can access
      - System Dashboard in Filament
      - System Settings in Filament

  - Tenant (company) - tenant owner, can have multiple users
    - routes:
      - app.{domain}/admin/ route for tenant admin dashboard checking for auth sessions, then redirect to /admin/dashboard or /dashboard accordingly
      - app.{domain}/admin/dashboard route for tenant admin dashboard
      - app.{domain}/admin/settings for tenant settings
      - app.{domain}/admin/branches for centre management
      - app.{domain}/admin/children for child management
    - owner_id flag on tenants table
    - can manage tenant settings in Filament
    - can invite other users to tenant
    - can has multiple Tenant (Subscribe to multiple companies plan)
    - can switch between tenants they belong to
    - cannot:
      - delete tenant if they are the last owner
      - leave tenant if they are the last owner
      - delete their own account if they are the last owner
    - can access
      - Tenant Dashboard in Filament
      - Tenant Settings in Filament
      - Branches Management in Filament
      - Child Management in Filament
      - Enrolment Management in Filament
      - Invoicing in Filament
      - Invite Users to Tenant
      - Payment Management in Filament
      - Finance Dashboard in Filament
      - User Management in Filament (Limited to tenant users only, read only for tenant owners)
      - Product Management in Filament

  - Tenant (staff) - general user under tenant, can have multiple roles
    - routes:
      - app.{domain}/admin/ route for tenant admin dashboard
      - app.{domain}/admin/settings for tenant settings
      - app.{domain}/admin/branches for centre management
      - app.{domain}/admin/children for child management
      - app.{domain}/admin/enrolments for enrolment management
      - app.{domain}/admin/invoices for invoicing
      - app.{domain}/admin/payments for payment management
      - app.{domain}/{tenant:slug}/login for tenant login
      - app.{domain}/{tenant:slug}/register (alias, registration) for register new parent directly to tenant
    - can have multiple roles (e.g., Staff, Accountant, Teacher) assigned by tenant owner
    - can switch between tenants they belong to
    - permissions based on roles assigned
    - can access
      - Tenant Dashboard in Filament
      - Tenant Settings in Filament (based on role permissions)
      - Branches Management in Filament (based on role permissions)
      - Child Management in Filament (based on role permissions)
      - Enrolment Management in Filament (based on role permissions)
      - Invoicing in Filament (based on role permissions)
      - Payment Management in Filament (based on role permissions)
      - Finance Dashboard in Filament (based on role permissions)
      - User Management in Filament (based on role permissions)
      - Product Management in Filament (based on role permissions)

  - Parent - general user under tenant, can have multiple children, belongs to multiple tenants
    - routes:
      - app.{domain}/ route for parent dashboard checking for auth sessions, then redirect to /dashboard or /login accordingly
      - app.{domain}/dashboard route for parent dashboard
      - app.{domain}/profile for profile management
      - app.{domain}/children/{child_id} for child details
      - app.{domain}/invoices for viewing invoices
      - app.{domain}/payments for making payments (support multiple invoices at once)
      - app.{domain}/transactions for payments history
      - app.{domain}/{tenant:slug}/login for tenant login
      - app.{domain}/{tenant:slug}/register (alias, registration) for register new parent directly to tenant
    - all users are parents by default
    - can have multiple children enrolled in multiple centres under the tenant
    - can belong to multiple tenants (e.g., siblings in different companies)
    - can switch between tenants they belong to
    - once registered, parent is linked to tenant via TenantUser and TenantChild
    - add child only once registered
    - children can linked to multiple tenants via TenantChild
    - children enrolment managed via ChildEnrolment model
    - can access
      - Parent Dashboard in Filament
      - Edit Profile Page in Filament
      - View Child Details in Filament
      - View Invoices in Filament
      - Make Payments in Filament

### Example Routes Domain Structure

- System Owner (console):
  - console.kindygo.com/
  - console.kindygo.com/settings
  - console.kindygo.com/tenants
  - console.kindygo.com/users
  - console.kindygo.com/invoices
  - console.kindygo.com/payments
  - console.kindygo.com/transactions
  - console.kindygo.com/finance
  - console.kindygo.com/products
  - console.kindygo.com/features
  - console.kindygo.com/logs
  - console.kindygo.com/notifications
- Tenant Owner/Admin (app):
  - app.kindygo.com/admin/
  - app.kindygo.com/admin/settings
  - app.kindygo.com/admin/branches
  - app.kindygo.com/admin/children
  - app.kindygo.com/admin/enrolments
  - app.kindygo.com/admin/invoices
  - app.kindygo.com/admin/payments
  - app.kindygo.com/admin/finance
  - app.kindygo.com/admin/users
  - app.kindygo.com/admin/products
- Tenant Staff (app):
  - app.kindygo.com/staff/
  - app.kindygo.com/staff/settings
  - app.kindygo.com/staff/branches
  - app.kindygo.com/staff/children
  - app.kindygo.com/staff/enrolments
  - app.kindygo.com/staff/invoices
  - app.kindygo.com/staff/payments
  - app.kindygo.com/staff/finance
  - app.kindygo.com/staff/users
  - app.kindygo.com/staff/products
- Parent (app):
  - app.kindygo.com/dashboard
  - app.kindygo.com/profile
  - app.kindygo.com/children/{child_id}
  - app.kindygo.com/invoices
  - app.kindygo.com/payments
  - app.kindygo.com/transactions
- Public / Guest:
  - app.kindygo.com/ - redirects to kindygo.com (suggestion: landing page?)
  - app.kindygo.com/{tenant:slug}/login
  - app.kindygo.com/{tenant:slug}/register
  - app.kindygo.com/login - loaded current_tenant_id if any on previous session, else load the first tenant user belongs to
  - app.kindygo.com/password/reset
  - app.kindygo.com/password/reset/{token}
  - app.kindygo.com/email/verify
  - app.kindygo.com/company/register - register new tenant/company

## Media Upload Structure

- All media uploads are stored in the `storage/app/private/tenants/{tenant_uuid}/users/{user_uuid}/` directory structure for multi-tenancy support and privacy.
- Create signed URLs for accessing private media files to ensure secure access with expiration times.
- Directory structure breakdown:
  - `storage/app/private/` - Base directory for private media files.
  - `tenants/` - Subdirectory to separate media files by tenant.
  - `{tenant_uuid}/` - Unique identifier for each tenant (company) to isolate their data.
  - `users/` - Subdirectory to separate media files by user within the tenant.
  - `{user_uuid}/` - Unique identifier for each user to isolate their personal media files within the tenant.
- Example: `storage/app/private/tenants/123e4567-e89b-12d3-a456-426614174000/users/987e6543-e21b-12d3-a456-426614174999/profile-picture.jpg`
- This structure ensures that each tenant's data is isolated and secure, while also organizing files by user for easy retrieval.
- The `private` directory is used to restrict direct public access to uploaded files, ensuring that only authorized users can access their respective media.
- When a user uploads a file, the system automatically creates the necessary directories if they do not exist.
- File naming conventions should avoid using special characters and spaces to ensure compatibility across different file systems.
- Access to media files should be managed through application logic, ensuring that users can only access files associated with their tenant and user account.
- When retrieving media files, the application should construct the file path using the tenant's UUID and the user's UUID.
- tenant_uuid is fetched from the Tenant model based on the current tenant context
- user_uuid is fetched from the User model based on the authenticated user
- This structure ensures media files are organized by tenant and user for easy management and retrieval
- Signed URLs should be generated with appropriate expiration times to balance security and usability and proper caching headers for performance.

## Models structure

- Tenant has uuid columns for usage in
  - Upload directory structure

- User has uuid columns for usage in
  - Upload directory structure

- Both Tenant and User models use tenant_id and user_id as primary keys for relationships and foreign keys in other models. The uuid columns are supplementary and not used as primary keys.

### Models (app/Models)

- app/Models/Campus.php
- app/Models/Centre.php
- app/Models/CentreChild.php
- app/Models/Child.php
- app/Models/ChildEnrolment.php
- app/Models/ChildUser.php
- app/Models/Invoice.php
- app/Models/InvoiceItem.php
- app/Models/InvoiceItemsLedger.php
- app/Models/Payment.php
- app/Models/Product.php
- app/Models/ProductPrice.php
- app/Models/Scopes/ (folder containing model scopes)
- app/Models/Tenant.php
- app/Models/TenantChild.php
- app/Models/TenantInvitation.php
- app/Models/TenantUser.php
- app/Models/User.php
- app/Models/UserAddress.php
- app/Models/UserOfficeInfo.php
- app/Models/UserProfile.php

If you want, I can expand this to include the primary attributes, casts, relationships, and any custom query scopes for each model.

### Filament top-level Pages (app/Filament/Pages)

- app/Filament/Pages/FinanceDashboard.php
- app/Filament/Pages/EditProfile.php
- app/Filament/Pages/Tenancy/EditTenantProfilePage.php
- app/Filament/Pages/Tenancy/RegisterTenancyPage.php

### Filament Resource Pages (app/Filament/Resources/*/Pages)

Grouped by resource directory (resource name -> pages):

- CentreResource
  - app/Filament/Resources/CentreResource/Pages/ListCentres.php
  - app/Filament/Resources/CentreResource/Pages/EditCentre.php
  - app/Filament/Resources/CentreResource/Pages/CreateCentre.php

- ChildResource
  - app/Filament/Resources/ChildResource/Pages/ListChildren.php
  - app/Filament/Resources/ChildResource/Pages/CreateChild.php
  - app/Filament/Resources/ChildResource/Pages/EditChild.php
  - app/Filament/Resources/ChildResource/Pages/ViewChild.php

- ChildEnrolmentResource
  - app/Filament/Resources/ChildEnrolmentResource/Pages/ListChildEnrolments.php
  - app/Filament/Resources/ChildEnrolmentResource/Pages/CreateChildEnrolment.php
  - app/Filament/Resources/ChildEnrolmentResource/Pages/EditChildEnrolment.php
  - app/Filament/Resources/ChildEnrolmentResource/Pages/ViewChildEnrolment.php

- PaymentResource
  - app/Filament/Resources/PaymentResource/Pages/ListPayments.php
  - app/Filament/Resources/PaymentResource/Pages/CreatePayment.php
  - app/Filament/Resources/PaymentResource/Pages/EditPayment.php
  - app/Filament/Resources/PaymentResource/Pages/ViewPayment.php

- InvoiceItemsLedgerResource
  - app/Filament/Resources/InvoiceItemsLedgerResource/Pages/ListInvoiceItemsLedgers.php
  - app/Filament/Resources/InvoiceItemsLedgerResource/Pages/CreateInvoiceItemsLedger.php
  - app/Filament/Resources/InvoiceItemsLedgerResource/Pages/EditInvoiceItemsLedger.php
  - app/Filament/Resources/InvoiceItemsLedgerResource/Pages/ViewInvoiceItemsLedger.php

- UserResource
  - app/Filament/Resources/UserResource/Pages/ListUsers.php
  - app/Filament/Resources/UserResource/Pages/CreateUser.php
  - app/Filament/Resources/UserResource/Pages/EditUser.php

- ProductResource
  - app/Filament/Resources/ProductResource/Pages/ListProducts.php
  - app/Filament/Resources/ProductResource/Pages/CreateProduct.php
  - app/Filament/Resources/ProductResource/Pages/EditProduct.php

- ParentResource
  - app/Filament/Resources/ParentResource/Pages/ListParents.php
  - app/Filament/Resources/ParentResource/Pages/CreateParent.php
  - app/Filament/Resources/ParentResource/Pages/EditParent.php

- InvoiceResource
  - app/Filament/Resources/InvoiceResource/Pages/ListInvoices.php
  - app/Filament/Resources/InvoiceResource/Pages/CreateInvoice.php
  - app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php
  - app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php

### Notes

- This inventory was generated from scanning `app/Models` and `app/Filament` directories. It intentionally lists files and their relative paths so you can quickly map features to code.
- Next steps I can take on request:
  - Expand each model with its attributes, casts, relationships and common methods.
  - Add a short feature-to-model mapping (e.g., Invoicing -> Invoice, InvoiceItem, Payment).
  - Generate a visual feature map (Markdown or simple diagram) linking pages/resources to models and controllers.

Feel free to tell me which of the next steps you'd like and I'll update this document further.


=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.24
- filament/filament (FILAMENT) - v4
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/telescope (TELESCOPE) - v5
- livewire/livewire (LIVEWIRE) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
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


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
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
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

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
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
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
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

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


=== filament/filament rules ===

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


### Important Version 4 Changes
- File visibility is now `private` by default.
- The `deferFilters` method from Filament v3 is now the default behavior in Filament v4, so users must click a button before the filters are applied to the table. To disable this behavior, you can use the `deferFilters(false)` method.
- The `Grid`, `Section`, and `Fieldset` layout components no longer span all columns by default.
- The `all` pagination page method is not available for tables by default.
- All action classes extend `Filament\Actions\Action`. No action classes exist in `Filament\Tables\Actions`.
- The `Form` & `Infolist` layout components have been moved to `Filament\Schemas\Components`, for example `Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.
- A new `Repeater` component for Forms has been added.
- Icons now use the `Filament\Support\Icons\Heroicon` Enum by default. Other options are available and documented.

### Organize Component Classes Structure
- Schema components: `Schemas/Components/`
- Table columns: `Tables/Columns/`
- Table filters: `Tables/Filters/`
- Actions: `Actions/`
</laravel-boost-guidelines>
