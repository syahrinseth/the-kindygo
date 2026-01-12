
# Tenant Management Guidelines

This document outlines how tenant (multi-tenancy) is handled in this Laravel application. We manage our own tenant logic rather than using Filament's built-in tenancy features.

## Overview

The application uses a custom multi-tenancy system where:

- Tenants represent companies/organisations that use the system
- Each tenant is isolated with their own data
- Users can belong to multiple tenants
- Tenant context is maintained throughout the application lifecycle

## Database Structure

### Tenant Model

- **Primary Key**: `tenant_id` (integer, auto-increment)
- **UUID**: `uuid` (string) - Used for directory structures and external references
- **Key Attributes**:
    - `name` - Tenant/company name
    - `slug` - URL-friendly identifier
    - `owner_id` - References the user who owns this tenant
    - `status` - Tenant status (active, suspended, etc.)
    - Timestamps and soft deletes

### Tenant Relationships

- `TenantUser` - Pivot table linking users to tenants with roles
- `TenantChild` - Links children to tenants
- `TenantInvitation` - Manages user invitations to tenants

## Tenant Context Management

### Setting Current Tenant

The application maintains the current tenant context using:

1. **Session-based context** - Current tenant stored in session
2. **Middleware** - Ensures tenant context is set for each request
3. **Scopes** - Eloquent global scopes filter queries by tenant

### Tenant Switching

Users can switch between tenants they belong to:

```php
// Switch to a different tenant
auth()->user()->switchTenant($tenantId);

// Get current tenant
$currentTenant = auth()->user()->currentTenant();
```

## Data Isolation

### Global Scopes

Models that belong to tenants should use tenant scopes:

- Automatically filter queries to current tenant
- Applied globally unless explicitly removed
- Located in `app/Models/Scopes/`

### Tenant-Specific Models

Models that belong to a tenant should:

1. Have a `tenant_id` foreign key
2. Apply tenant scope in the model
3. Use tenant context in relationships

Example:

```php
use App\Models\Scopes\TenantScope;

class Centre extends Model
{
        protected static function booted(): void
        {
                static::addGlobalScope(new TenantScope());
        }

        public function tenant(): BelongsTo
        {
                return $this->belongsTo(Tenant::class);
        }
}
```

## Media Upload Structure

Tenant-specific media follows this structure:

```
storage/app/private/tenants/{tenant_uuid}/users/{user_uuid}/
```

- Ensures complete data isolation
- Uses UUID to prevent enumeration
- Private storage with signed URL access

## Route Structure

### Tenant-Specific Routes

Routes are organised by tenant context:

- **System Admin**: `console.{domain}/*`
- **Tenant Admin**: `app.{domain}/admin/*`
- **Tenant Staff**: `app.{domain}/staff/*` (permission-based)
- **Parent/User**: `app.{domain}/*`
- **Public Tenant Access**: `app.{domain}/{tenant:slug}/*`

### Route Model Binding

Tenant routes use slug-based binding:

```php
Route::get('/{tenant:slug}/login', function (Tenant $tenant) {
        // $tenant is automatically resolved by slug
});
```

## Filament Integration

### Panel Configuration

Each Filament panel is configured with tenant context:

1. **Admin Panel** - Tenant administration
2. **App Panel** - Parent/user interface
3. **Console Panel** - System administration (no tenant)

### Resource Filtering

Filament resources automatically respect tenant context:

- Table queries scoped to current tenant
- Form submissions include tenant_id
- Relationship queries filtered by tenant

### Multi-Panel Tenant Handling

```php
// In Filament Panel Provider
->tenant(Tenant::class)
->tenantMiddleware([
        EnsureCurrentTenant::class,
])
```

## Tenant User Management

### User-Tenant Relationships

Users relate to tenants through `TenantUser`:

- **Many-to-Many**: Users can belong to multiple tenants
- **Roles**: Each user-tenant relationship has roles
- **Permissions**: Roles determine access within tenant

### Tenant Owner

- Specified by `owner_id` on `tenants` table
- Has full control over tenant
- Cannot leave if they're the last owner
- Can transfer ownership to another user

## Best Practises

### Creating Tenant-Aware Models

1. Add `tenant_id` column to migration
2. Apply `TenantScope` to model
3. Add tenant relationship
4. Include tenant_id in factory

### Querying Without Tenant Scope

When you need to query across tenants:

```php
Model::withoutGlobalScope(TenantScope::class)->get();
```

### Testing Tenant Features

1. Create tenant in test setup
2. Set current tenant context
3. Assert tenant isolation
4. Test tenant switching

### Seeding Tenant Data

1. Create tenant first
2. Set tenant context
3. Create related records
4. Repeat for multiple tenants

## Security Considerations

- Always validate tenant access before operations
- Use signed URLs for tenant media access
- Implement row-level security in queries
- Audit tenant switching actions
- Validate user belongs to tenant on authentication

## API Versioning with Tenants

APIs must respect tenant context:

- Include tenant identification in requests
- Validate tenant access for API calls
- Return tenant-scoped data only
- Use API versioning (v1, v2, etc.)

## Common Pitfalls

1. **Forgetting tenant_id** - Always include in migrations and models
2. **Missing scope** - New models must apply tenant scope
3. **Hardcoded queries** - Use relationships and scopes
4. **Cross-tenant leaks** - Test data isolation thoroughly
5. **Media path errors** - Always use tenant UUID structure
