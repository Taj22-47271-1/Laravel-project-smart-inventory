# Laravel-project-smart-inventory
# Smart Inventory Management System

A modern, secure, and responsive inventory management system built with Laravel, Livewire, Flux UI, Tailwind CSS, and MySQL.

The application helps businesses manage products, stock, purchases, sales, suppliers, customers, payments, returns, adjustments, reports, users, roles, and permissions from one centralized dashboard.

## Features

### Dashboard
- Inventory overview
- Sales and purchase summaries
- Low-stock monitoring
- Recent transaction information
- Business performance indicators

### Product Management
- Products
- Categories
- Brands
- Units
- Product pricing
- Stock quantity tracking
- Active and inactive status management

### Supplier and Customer Management
- Supplier profiles
- Customer profiles
- Contact and address information
- Opening balances
- Transaction history

### Purchase Management
- Create purchases
- Manage purchase records
- Purchase items
- Supplier balances
- Purchase returns
- Purchase payments

### Sales Management
- Create sales
- Manage sales records
- Customer balances
- Sale returns
- Sale payments
- Automatic stock updates

### Inventory Management
- Current inventory
- Stock movements
- Stock-in and stock-out history
- Stock adjustments
- Low-stock identification

### Reports
- Sales reports
- Purchase reports
- Inventory reports
- Payment reports
- Return reports
- Date-based filtering

### User and Access Management
- User management
- Admin, manager, and staff roles
- Role-based permissions
- Active and inactive account control
- Email verification
- Two-factor authentication
- Passkey support

## Technology Stack

- PHP
- Laravel 13
- Livewire 4
- Flux UI
- Tailwind CSS 4
- Alpine.js
- MySQL
- Spatie Laravel Permission
- Vite

## System Requirements

Make sure the following software is installed:

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL
- Git

For local development on Windows, Laragon or XAMPP may also be used.

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/Taj22-47271-1/Laravel-project-smart-inventory.git
cd Laravel-project-smart-inventory
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Create the environment file

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

On Linux or macOS:

```bash
cp .env.example .env
```

### 5. Generate the application key

```bash
php artisan key:generate
```

### 6. Configure the database

Create a MySQL database, for example:

```text
smart_inventory
```

Update the following values in the `.env` file:

```env
APP_NAME="Smart Inventory"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smart_inventory
DB_USERNAME=root
DB_PASSWORD=
```

Never upload the real `.env` file to GitHub.

### 7. Run database migrations

```bash
php artisan migrate
```

When the project contains the required seeders, run:

```bash
php artisan db:seed
```

Alternatively:

```bash
php artisan migrate --seed
```

### 8. Create the storage link

```bash
php artisan storage:link
```

### 9. Build frontend assets

For development:

```bash
npm run dev
```

For production:

```bash
npm run build
```

### 10. Start the Laravel server

```bash
php artisan serve
```

Open the application:

```text
http://127.0.0.1:8000
```

## Useful Development Commands

Clear Laravel caches:

```bash
php artisan optimize:clear
```

Run automated tests:

```bash
php artisan test
```

Check application routes:

```bash
php artisan route:list
```

Run the code formatter:

```bash
./vendor/bin/pint
```

On Windows PowerShell:

```powershell
vendor\bin\pint
```

## User Roles

The system supports role-based access control.

| Role | Typical Access |
|---|---|
| Admin | Full system access, users, roles, reports, and settings |
| Manager | Operational management, purchases, sales, inventory, and reports |
| Staff | Limited daily transaction and inventory access |

Actual access depends on the permissions assigned to each role.

## Main Application Routes

| Module | Route |
|---|---|
| Dashboard | `/dashboard` |
| Products | `/products` |
| Categories | `/categories` |
| Brands | `/brands` |
| Units | `/units` |
| Suppliers | `/suppliers` |
| Customers | `/customers` |
| Purchases | `/purchases` |
| Sales | `/sales` |
| Inventory | `/inventory` |
| Stock Movements | `/inventory/movements` |
| Sale Returns | `/sale-returns` |
| Purchase Returns | `/purchase-returns` |
| Stock Adjustments | `/stock-adjustments` |
| Payments | `/payments` |
| Reports | `/reports` |
| Users and Roles | `/users/manage` |

Protected routes require authentication, email verification, an active account, and the necessary permission.

## Security Notes

- Do not commit the `.env` file.
- Do not upload database backups containing private information.
- Do not expose `APP_KEY`, database credentials, mail passwords, or API keys.
- Use `APP_DEBUG=false` in production.
- Use HTTPS in production.
- Assign only the permissions required for each role.
- Change all development or seeded passwords before deployment.

## Project Structure

```text
app/                 Application models, actions, and business logic
bootstrap/           Application bootstrap configuration
config/              Laravel configuration files
database/            Migrations, factories, and seeders
public/              Public assets and entry point
resources/           Blade views, CSS, and JavaScript
routes/              Web and settings routes
storage/             Logs, cache, and generated files
tests/               Automated tests
```

## Deployment Checklist

Before production deployment:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Also configure:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

## Updating the Project

After making changes:

```bash
git add .
git commit -m "Describe your changes"
git push
```

## Author

**Taj22-47271-1**

GitHub repository:  
`https://github.com/Taj22-47271-1/Laravel-project-smart-inventory`

## License

No open-source license has been selected yet. Add a `LICENSE` file before allowing public reuse, modification, or distribution.
##
admin@gmai.com
pass:Admin@12345