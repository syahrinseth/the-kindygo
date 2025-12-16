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
