# KindyGo - Childcare Management System

A comprehensive childcare and kindergarten management system built with Laravel, Filament, and modern web technologies.

![Laravel](https://img.shields.io/badge/Laravel-v12.0-red.svg)
![PHP](https://img.shields.io/badge/PHP-^8.2-blue.svg)
![Filament](https://img.shields.io/badge/Filament-v3.3-orange.svg)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v4.0-cyan.svg)

## About KindyGo

KindyGo is a modern childcare management system designed to streamline operations for kindergartens, childcare centers, and educational institutions. The system provides comprehensive tools for managing children, enrolments, billing, payments, and administrative tasks through an intuitive web interface.

### Key Features

- **👶 Child Management**: Complete child profiles with personal information, medical records, and documentation
- **🏫 Multi-Centre Support**: Manage multiple centres and campuses from a single platform
- **📝 Enrolment Management**: Handle child enrolments with flexible billing cycles and status tracking
- **💰 Billing & Invoicing**: Automated invoice generation with Malaysian e-invoice compliance
- **💳 Payment Processing**: Integrated payment gateway (CHIP) with multiple payment methods
- **👨‍👩‍👧‍👦 Parent Portal**: Dedicated interface for parents to manage their children's information
- **📊 Admin Dashboard**: Comprehensive reporting and analytics through Filament admin panel
- **🔐 Role-Based Access**: Multi-tenant architecture with role-based permissions
- **📱 Responsive Design**: Mobile-friendly interface built with TailwindCSS

## Technology Stack

### Backend

- **Laravel 12.0** - PHP framework
- **PHP 8.2+** - Programming language
- **MySQL/PostgreSQL** - Database
- **Filament 3.3** - Admin panel framework
- **Spatie Laravel Permission** - Role and permission management
- **Laravel Sanctum** - API authentication

### Frontend

- **TailwindCSS 4.0** - CSS framework
- **Vite 6.2** - Build tool
- **Livewire** - Full-stack framework (via Filament)

### Payment & Integrations

- **CHIP Payment Gateway** - Payment processing
- **MyInvois PHP SDK** - Malaysian e-invoice integration
- **Spatie Media Library** - File and media management

### Development & Testing

- **Pest PHP** - Testing framework
- **Laravel Pint** - Code formatting
- **Faker** - Test data generation

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 16+ and npm/yarn
- MySQL 8.0+ or PostgreSQL 13+
- Web server (Apache/Nginx)

### Setup Instructions

1. **Clone the repository**

   ```bash
   git clone <repository-url> kindygo-app
   cd kindygo-app
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install Node.js dependencies**

   ```bash
   npm install
   ```

4. **Environment configuration**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your `.env` file**

   ```env
   APP_NAME="KindyGo"
   APP_URL=http://localhost
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=kindygo
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # CHIP Payment Gateway
   CHIP_BRAND_ID=your_brand_id
   CHIP_SECRET_KEY=your_secret_key
   CHIP_PUBLIC_KEY=your_public_key
   CHIP_ENVIRONMENT=sandbox # or production
   
   # MyInvois (Malaysian e-invoice)
   MYINVOIS_CLIENT_ID=your_client_id
   MYINVOIS_CLIENT_SECRET=your_client_secret
   MYINVOIS_ENVIRONMENT=sandbox # or production
   ```

6. **Database setup**

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Storage linking**

   ```bash
   php artisan storage:link
   ```

8. **Build assets**

   ```bash
   npm run build
   # or for development
   npm run dev
   ```

9. **Create admin user**

   ```bash
   php artisan make:filament-user
   ```

## Development

### Running the Application

```bash
# Start Laravel development server
php artisan serve

# Start Vite development server (in another terminal)
npm run dev
```

Access the application at `http://localhost:8000`

- Admin Panel: `/admin`
- Parent Portal: `/parent`

### Code Style & Testing

```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Run tests with Pest
php artisan test
# or
./vendor/bin/pest
```

### Database Management

```bash
# Create new migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Seed database
php artisan db:seed
```

## Project Structure

```bash
├── app/
│   ├── Enums/              # Application enumerations
│   ├── Filament/           # Filament admin panel resources
│   ├── Http/Controllers/   # HTTP controllers
│   ├── Models/            # Eloquent models
│   ├── Policies/          # Authorization policies
│   ├── Services/          # Business logic services
│   └── Support/           # Helper classes
├── database/
│   ├── migrations/        # Database migrations
│   ├── seeders/          # Database seeders
│   └── factories/        # Model factories
├── resources/
│   ├── views/            # Blade templates
│   ├── js/              # JavaScript assets
│   └── css/             # CSS assets
└── routes/
    ├── web.php           # Web routes
    ├── api.php           # API routes
    └── console.php       # Console commands
```

### Key Models

- **Child**: Represents a child enrolled in the system
- **Centre**: Childcare centers/campuses
- **ChildEnrolment**: Child enrolment records with billing information
- **Invoice**: Billing invoices for services
- **Payment**: Payment records and transactions
- **User**: System users (parents, staff, administrators)

## Payment Integration

The system integrates with CHIP Payment Gateway for processing payments. See [CHIP_INTEGRATION.md](CHIP_INTEGRATION.md) for detailed implementation notes.

### Payment Flow

1. Invoice generation from enrolments
2. Payment initiation through CHIP gateway
3. Redirect to secure payment page
4. Callback handling for payment status
5. Invoice status updates

## API Documentation

The system provides RESTful APIs for integration with external systems. API endpoints are secured with Laravel Sanctum.

### Authentication

```bash
# Get API token
POST /api/login
```

### Available Endpoints

- `/api/children` - Child management
- `/api/enrolments` - Enrolment management
- `/api/invoices` - Invoice management
- `/api/payments` - Payment processing

## Deployment

### Production Environment

1. **Server Requirements**
   - PHP 8.2+ with required extensions
   - MySQL 8.0+ or PostgreSQL 13+
   - Nginx or Apache web server
   - Redis (recommended for caching and sessions)

2. **Deployment Steps**

   ```bash
   # Optimize for production
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   
   # Run migrations
   php artisan migrate --force
   
   # Build assets
   npm run build
   ```

3. **Environment Configuration**
   - Set `APP_ENV=production`
   - Configure proper database credentials
   - Set up SSL certificates
   - Configure queue workers for background jobs

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`php artisan test`)
5. Format code (`./vendor/bin/pint`)
6. Commit changes (`git commit -m 'Add amazing feature'`)
7. Push to branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## Security

If you discover any security vulnerabilities, please send an email to the development team. All security vulnerabilities will be promptly addressed.

### Security Features

- Multi-tenant data isolation
- Role-based access control
- CSRF protection
- SQL injection prevention
- XSS protection
- Secure file uploads

## License

This project is proprietary software. All rights reserved.

## Support

For technical support and questions:

- Create an issue in the repository
- Contact the development team
- Check the documentation in the `/docs` directory

---

Built with ❤️ using Laravel and Filament
