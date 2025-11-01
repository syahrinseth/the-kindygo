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
- Filament v3 required files and structures

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
