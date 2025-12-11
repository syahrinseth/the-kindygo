# Planning: Tenancy Structure Alignment

Reviewed against .ai/guidelines/00-feature-domain.md on 2025-12-11 to reflect the updated tenancy/feature documentation. This captures current state, gaps, and recommended actions.

## Requirement Snapshot (from updated doc)
- Support distinct roles and surfaces:
  - System Owner via console.{domain} (system dashboard, settings, tenants, users, finance, products, features, logs, notifications, etc.).
  - Tenant Owner/Admin via app.{domain}/admin (tenant dashboard, settings, branches, children, enrolments, invoices, payments, finance, user/product management, invites, multi-tenant switching).
  - Tenant Staff (role/permission-based access to the same tenant surfaces as allowed).
  - Parent-facing app.{domain}/ (parent dashboard, profile, children, invoices, payments, transactions, tenant-specific login/register).
- British English spellings (enrolment, etc.).
- Filament v4 structure with navigation groups and dashboards per role/domain.
- Tests with Pest v4 and feature coverage for new flows.

## Current State (observed)
- Filament panel: single panel configured at path `app` with id `app` in [app/Providers/Filament/AppPanelProvider.php](app/Providers/Filament/AppPanelProvider.php). Uses Filament default `Pages\Dashboard`; `FinanceDashboard` is commented out. Widgets registered: `InvoiceStats`, `InvoiceChart`. Tenant context enabled via `tenant(Tenant::class, slugAttribute: 'slug')` and `UpdateCurrentTenant` middleware.
- Navigation grouping: defined globally in [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) with groups Finance, Campus Management, User Management.
- Routing: [routes/web.php](routes/web.php) includes public login/register, tenant invitation, secure media, CHIP payment callbacks, invoice/payment PDF routes. No console.{domain} subdomain routing and no explicit app.{domain}/admin tenant admin prefix; Filament panel mounted at `/app`.
- Filament pages/resources: FinanceDashboard exists ([app/Filament/Pages/FinanceDashboard.php](app/Filament/Pages/FinanceDashboard.php)) with finance widgets, but not currently registered in the panel. Standard resources exist (Children, Enrolments, Invoices, Payments, Products, Centres, Users, etc.) under `App\Filament\Resources`.
- No separate system-owner console panel or parent-facing panel observed.
- Filament version appears to match current codebase (PanelProvider API), but the requirement now calls for Filament v4; version alignment needs confirmation and/or an upgrade plan.

## Gaps vs Requirements
- Missing dedicated System Owner surface (console.{domain}) with system-level dashboards, settings, tenant/user/payment/product/feature management, logs, notifications.
- Tenant Admin surface currently mounted at `/app` but not clearly separated for admin vs parent; lacks documented admin prefixes (e.g., /app/admin/...).
- Parent-facing surface (app.{domain}/ for parents) not present; current root redirects authenticated users to `/app` (Filament), not a parent UI.
- FinanceDashboard not wired as the main dashboard; panel still uses Filament default dashboard.
- British spelling audit needed (legacy "Enrollment" names may still exist; renames were in progress).
- Tests for the above tenancy flows not present (Pest v4 coverage for system/tenant/parent paths, permissions, dashboards, and routes).
- Filament version mismatch risk: requirement calls for v4; code may still be on an earlier Filament version (verify and plan upgrade).

## Recommended Next Steps (prioritised)
1) Panel topology and domains
   - Add a System panel (console) on console.{domain} with its own navigation and policies; wire dashboards/settings/tenants/users/products/features/logs/notifications.
   - Tenant + Parent surface: remove `/app` prefix; mount main panel at domain root (app.{domain} or console.{domain}) and serve `/dashboard` as the landing page for all authenticated users (System, Tenant, Parent) using a single Filament v4 codebase.
   - Tenant slug login: `app.{domain}/{tenant:slug}/login` sets `current_tenant_id` to that tenant before showing login; root `/login` uses current tenant if present.
   - Define Parent-facing routes at root (parent dashboard/profile/children/invoices/payments/transactions) using the same panel with role/policy gating.

2) Routing and tenancy plumbing
   - Introduce console subdomain routing; ensure tenant slug/domain handling for admin; add parent routes; update middleware stacks per surface.
   - Root behaviour: if authenticated → redirect to `/dashboard`; if guest → redirect to `/login` (setting/using `current_tenant_id` when a tenant slug is provided).

3) Navigation, dashboards, permissions
   - Set navigationGroups per panel; align `navigationGroup`/`navigationSort` on resources/pages; enforce `shouldRegisterNavigation` with role/policy checks.
   - Add a unified `Dashboard` page at `/dashboard` (replace FinanceDashboard as the default entry point) with role-aware widgets/sections for System Owner, Tenant Owner/Staff, and Parent.

4) Version alignment
   - Confirm Filament version; if <v4, create upgrade plan (panel config, view/layout changes, asset pipeline) to meet the doc requirement. Target: single Filament v4 panel powering all roles.

5) Naming and data model hygiene
   - Complete British spelling (Enrolment) across files/migrations/tables; reconcile any remaining "Enrollment" artifacts.
   - Validate media storage paths and signed URL flows per documented multi-tenant structure.

6) Testing (Pest v4)
   - Add feature/browser tests for: console panel auth/navigation; tenant admin auth, dashboard, CRUD; parent flows (dashboard/profile/children/invoices/payments); permissions/role gating; multi-tenant switching.

## Open Questions to Resolve
- Confirm domain strategy (wildcard subdomains for console/app/tenant slugs?).
- Decide whether parent surface is Filament-based or a separate frontend stack.
- Clarify role/permission model for System Owner vs Tenant Owner vs Staff (policies, gates, or Spatie permissions?).
