# KindyGo Documentation

Welcome to the KindyGo documentation. This guide will help you navigate through different aspects of the system based on your role and needs.

## Quick Navigation

- **New Developer?** Start with [Getting Started Guide](developer/00-getting-started.md)
- **System Administrator?** Check [Operations Documentation](#operations)
- **Principal/Admin User?** See [Admin Onboarding Guide](user/admin/00-onboarding-guide.md)
- **Planning & Architecture?** Review [Planning Documents](#planning--architecture)

---

## For Developers

Developer documentation covers technical implementation, architecture, and system design.

### Getting Started
1. [Getting Started Guide](developer/00-getting-started.md) - Set up your local environment and begin development

### Technical Implementation
1. [E-Invoice Implementation](developer/01-einvoice-implementation.md) - Complete technical specification for LHDN-compliant e-Invoice system
2. [V2 to V3 Migration Mapping](developer/02-v2-to-v3-migration.md) - Schema migration reference and data transformation patterns

### Key Topics
- Multi-tenancy architecture (System Owner, Tenant Owner, Tenant Staff, Parent roles)
- Laravel 12 + Filament 4 + Livewire 3 stack
- E-Invoice integration with Malaysian LHDN compliance
- Role-based access control (RBAC)
- Database schema and relationships

---

## For Operations

Operations documentation covers deployment, environment setup, and system administration.

> **Coming Soon**: Deployment guides, environment configuration, monitoring setup

---

## For Users

User guides organized by role to help you use the KindyGo system effectively.

### Admin/Principal Users
1. [Onboarding Guide](user/admin/00-onboarding-guide.md) - Step-by-step guide for principals to onboard, enrol children, and manage invoices

### Parent Users
> **Coming Soon**: Parent dashboard guide, child enrolment process, payment management

### Staff Users
> **Coming Soon**: Staff access guide, daily operations, reporting

---

## Planning & Architecture

Reference documents for system planning, architecture decisions, and requirements analysis.

- [System Architecture Planning](planning/system-architecture-planning.md) - Tenancy structure, gaps analysis, and development roadmap

---

## Contributing to Documentation

When adding new documentation:

1. **Choose the right folder**:
   - `developer/` - Technical documentation for developers
   - `operations/` - Deployment and infrastructure guides
   - `user/{role}/` - End-user guides by role (admin, parent, staff)
   - `planning/` - Planning documents and architecture decisions

2. **Use numeric prefixes for sequential guides**:
   - Use `00-`, `01-`, `02-` for guides that should be read in order
   - Skip prefixes for reference documentation

3. **Update this README**:
   - Add your new document to the appropriate section
   - Include a brief description of what it covers

4. **Follow markdown standards**:
   - Use proper heading hierarchy (start with h1)
   - Include a table of contents for longer documents
   - Use British English spelling (e.g., enrolment, centre)

---

## Project Context

**KindyGo** is a multi-tenant childcare management system built for Malaysian childcare centres.

**Tech Stack:**
- PHP 8.3
- Laravel 12
- Filament 4
- Livewire 3
- Pest 4
- Tailwind CSS 4

**Key Features:**
- Multi-tenancy with role-based access
- Child enrolment management
- Invoice and payment processing
- LHDN-compliant e-Invoice generation
- Parent portal and communication

---

## Need Help?

- **Bug reports**: Create an issue in the repository
- **Feature requests**: Discuss with the team
- **Documentation improvements**: Submit a pull request

---

*Last updated: December 29, 2025*
