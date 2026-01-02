# Getting Started with KindyGo Development

Welcome to KindyGo! This guide will help you set up your local development environment and start contributing to the project.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

### Required Software
- **PHP 8.3+** - The project uses PHP 8.3 features
- **Composer** - PHP dependency manager
- **Node.js & npm** - For frontend asset compilation
- **Laravel Herd** - Local development environment (recommended)
  - Herd provides PHP, MySQL/PostgreSQL, and automatic HTTPS setup
  - Download from [herd.laravel.com](https://herd.laravel.com)
- **Git** - Version control

### Optional but Recommended
- **Laravel Pint** - Code formatter (included in project)
- **Pest** - Testing framework (included in project)
- **VS Code** with Laravel extensions

---

## Initial Setup

### 1. Clone the Repository

```bash
git clone https://github.com/mzrglobal/kindygo-v3.git
cd kindygo-v3
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Configure your database credentials in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kindygo
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Run Database Migrations

```bash
php artisan migrate --seed
```

This will create the database schema and seed initial data.

### 6. Access the Application

With Laravel Herd, your application is automatically available at:

```
https://kindygo-app.test
```

If using a different setup, start the development server:

```bash
php artisan serve
```

### 7. Compile Frontend Assets

For development with hot reload:

```bash
npm run dev
```

Or build for production:

```bash
npm run build
```

---

## Project Structure

### Key Directories

```
kindygo-app/
├── app/
│   ├── Filament/          # Filament admin panels and resources
│   ├── Http/              # Controllers and middleware
│   ├── Models/            # Eloquent models
│   ├── Policies/          # Authorization policies
│   └── Services/          # Business logic services
├── database/
│   ├── migrations/        # Database migrations
│   ├── seeders/           # Database seeders
│   └── factories/         # Model factories for testing
├── docs/                  # Documentation (you are here!)
├── resources/
│   ├── views/             # Blade templates
│   ├── js/                # JavaScript files
│   └── css/               # Stylesheets
├── routes/
│   ├── web.php            # Web routes
│   ├── api.php            # API routes
│   └── console.php        # Artisan commands
└── tests/                 # Pest tests
    ├── Feature/           # Feature tests
    └── Unit/              # Unit tests
```

### Important Configuration Files

- `.github/copilot-instructions.md` - AI coding assistant guidelines
- `composer.json` - PHP dependencies and project metadata
- `package.json` - Node.js dependencies
- `phpunit.xml` - Test configuration
- `vite.config.js` - Frontend build configuration

---

## Development Workflow

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes

Follow the coding standards defined in `.github/copilot-instructions.md`:
- Use PHP 8.3 constructor property promotion
- Follow Laravel conventions
- Write tests for new features
- Use Filament for admin interfaces

### 3. Format Your Code

Run Laravel Pint to ensure code formatting:

```bash
vendor/bin/pint
```

### 4. Run Tests

Execute the test suite:

```bash
php artisan test
```

Run specific test files:

```bash
php artisan test tests/Feature/ExampleTest.php
```

Filter by test name:

```bash
php artisan test --filter=testName
```

### 5. Commit and Push

```bash
git add .
git commit -m "feat: your feature description"
git push origin feature/your-feature-name
```

### 6. Create a Pull Request

Open a PR on GitHub targeting the `dev` branch.

---

## Tech Stack Overview

### Backend
- **Laravel 12** - PHP framework
- **Filament 4** - Admin panel framework
- **Livewire 3** - Full-stack framework for dynamic interfaces
- **Laravel Sanctum 4** - API authentication
- **Laravel Horizon 5** - Queue monitoring
- **Laravel Telescope 5** - Debugging assistant

### Frontend
- **Tailwind CSS 4** - Utility-first CSS framework
- **Alpine.js** - Lightweight JavaScript framework (included with Livewire)
- **Vite** - Frontend build tool

### Testing
- **Pest 4** - Testing framework
- **PHPUnit 12** - Underlying test runner

### Code Quality
- **Laravel Pint 1** - Code formatter
- **Rector 2** - PHP automated refactoring tool

---

## Multi-Tenancy Architecture

KindyGo uses a multi-tenant architecture with the following user roles:

### System Owner
- Super admin with system-wide access
- Manages tenants, billing, and system configuration
- Access via: `console.{domain}/*`

### Tenant Owner/Admin
- Owns and manages a childcare centre (tenant)
- Can invite staff and manage settings
- Access via: `app.{domain}/admin/*`

### Tenant Staff
- Works under a tenant with role-based permissions
- Limited access based on assigned roles
- Access via: `app.{domain}/admin/*` (with restricted permissions)

### Parent
- End-user who enrolls children
- Views invoices and makes payments
- Access via: `app.{domain}/*`

For detailed architecture information, see [System Architecture Planning](../planning/system-architecture-planning.md).

---

## Common Development Tasks

### Create a New Model

```bash
php artisan make:model ModelName -mfs
```

Options:
- `-m` - Create migration
- `-f` - Create factory
- `-s` - Create seeder

### Create a Filament Resource

```bash
php artisan make:filament-resource ModelName --generate
```

### Create a Test

```bash
php artisan make:test FeatureTest --pest
php artisan make:test UnitTest --unit --pest
```

### Run Queue Workers

```bash
php artisan queue:work
```

Or use Horizon for monitoring:

```bash
php artisan horizon
```

### Clear Caches

```bash
php artisan optimize:clear
```

---

## Troubleshooting

### Vite Manifest Error

If you see "Unable to locate file in Vite manifest":

```bash
npm run build
```

Or ensure `npm run dev` is running during development.

### Database Connection Issues

Verify your database credentials in `.env` and ensure your database server is running.

### Permission Issues

On Unix-based systems, ensure storage and cache directories are writable:

```bash
chmod -R 775 storage bootstrap/cache
```

---

## Next Steps

Now that your environment is set up:

1. **Explore the codebase** - Familiarize yourself with the project structure
2. **Review existing code** - Check out models, controllers, and Filament resources
3. **Read technical docs** - See [E-Invoice Implementation](01-einvoice-implementation.md) and [V2 to V3 Migration](02-v2-to-v3-migration.md)
4. **Write tests** - Practice test-driven development with Pest
5. **Join the team** - Participate in code reviews and discussions

---

## Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Filament Documentation](https://filamentphp.com/docs/4.x)
- [Livewire Documentation](https://livewire.laravel.com/docs/3.x)
- [Pest Documentation](https://pestphp.com/docs/4.x)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)

---

## Questions?

- Check the [docs/README.md](../README.md) for more documentation
- Ask the team in your communication channels
- Review `.github/copilot-instructions.md` for coding guidelines

---

*Happy coding! 🚀*
