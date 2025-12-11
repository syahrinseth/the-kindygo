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
      - console.{domain}/ for system admin dashboard
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
      - app.{domain}/admin/ route for tenant admin dashboard
      - app.{domain}/admin/settings for tenant settings
      - app.{domain}/admin/branches for centre management
      - app.{domain}/admin/children for child management
    - is_tenant_owner flag on tenant_users table
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
      - app.{domain}/ route for parent dashboard
      - app.{domain}/profile for profile management
      - app.{domain}/children/{child_id} for child details
      - app.{domain}/invoices for viewing invoices
      - app.{domain}/payments for making payments (support multiple invoices at once)
      - app.{domain}/transactions for payments history
    - all users are parents by default
    - can have multiple children enrolled in multiple centres under the tenant
    - can belong to multiple tenants (e.g., siblings in different companies)
    - can switch between tenants they belong to
    - can access
      - Parent Dashboard in Filament
      - Edit Profile Page in Filament
      - View Child Details in Filament
      - View Invoices in Filament
      - Make Payments in Filament


## Project inventory (generated)

Below are the current models and Filament pages found in the codebase. This is intended as a living list — add or edit entries as features are added.

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
