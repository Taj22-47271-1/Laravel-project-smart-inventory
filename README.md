# Smart Inventory Management System

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?style=for-the-badge&logo=livewire&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)

A modern, secure, and responsive web-based inventory management system built with **Laravel 13**, **Livewire 4**, **Flux UI**, **Tailwind CSS 4**, and **MySQL**.

The application helps businesses manage products, stock, suppliers, customers, purchases, sales, returns, payments, reports, users, roles, and permissions from one centralized dashboard.

---

## Project Overview

Smart Inventory Management System provides a complete solution for daily inventory operations. It automatically updates stock after purchases, sales, returns, and adjustments while keeping a detailed movement history for every product.

The system also includes secure authentication, role-based access control, payment tracking, reporting, responsive layouts, and a professional dashboard.

---

## Main Features

### Dashboard

- Inventory overview
- Total product and stock summary
- Sales and purchase statistics
- Low-stock monitoring
- Recent transaction information
- Business performance indicators

### Product Management

- Create, edit, view, and delete products
- Manage categories, brands, and units
- Product code and pricing management
- Current stock tracking
- Active and inactive product status

### Supplier and Customer Management

- Supplier and customer profile management
- Contact, company, and address information
- Opening balance management
- Transaction and payment history
- Active and inactive status control

### Purchase Management

- Create purchases with multiple items
- Automatically increase product stock
- Manage purchase records
- Track supplier payments and due amounts
- Process purchase returns

### Sales Management

- Create sales invoices with multiple items
- Automatically reduce product stock
- Apply discounts
- Track paid and due amounts
- Manage customer sales history
- Process sale returns

### Inventory Management

- View current inventory
- Track stock-in and stock-out movements
- View product-wise stock history
- Create stock adjustments
- Monitor low-stock products
- Track inventory value

### Return Management

- Sale returns
- Purchase returns
- Return quantity validation
- Automatic stock correction
- Return history and notes

### Payment Management

- Record purchase payments
- Record sale payments
- Track payment methods
- Monitor paid and due balances
- View payment history

### Reports

- Sales reports
- Purchase reports
- Inventory reports
- Payment reports
- Return reports
- Date-range filtering
- Business summaries

### User, Role, and Permission Management

- Create and manage system users
- Admin, Manager, and Staff roles
- Permission-based menu and route access
- Active and inactive account control
- Secure authentication
- Email verification
- Two-factor authentication
- Passkey support

---

## Technology Stack

| Technology | Purpose |
|---|---|
| Laravel 13 | Backend framework |
| PHP 8.2+ | Server-side programming |
| Livewire 4 | Dynamic user interface |
| Flux UI | Interface components |
| Tailwind CSS 4 | Styling and responsive design |
| Alpine.js | Lightweight frontend interactions |
| MySQL | Relational database |
| Spatie Laravel Permission | Roles and permissions |
| Vite | Frontend asset bundling |
| Git and GitHub | Version control |

---

## System Requirements

Install the following software before running the project:

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL
- Git
- Laragon, XAMPP, or another local server environment

---

## Installation Guide

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

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Linux or macOS:

```bash
cp .env.example .env
```

### 5. Generate the application key

```bash
php artisan key:generate
```

### 6. Configure the database

Create a MySQL database:

```text
smart_inventory
```

Update the `.env` file:

```env
APP_NAME="Smart Inventory"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smart_inventory
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Run migrations and seeders

```bash
php artisan migrate --seed
```

To reset the database completely:

```bash
php artisan migrate:fresh --seed
```

> **Warning:** `migrate:fresh` deletes all existing database tables and data.

### 8. Create the storage link

```bash
php artisan storage:link
```

### 9. Clear application caches

```bash
php artisan optimize:clear
```

### 10. Start the Laravel server

```bash
php artisan serve
```

### 11. Start the frontend development server

Open another terminal:

```bash
npm run dev
```

Open the application:

```text
http://127.0.0.1:8000
```

---

## Running with Laragon

1. Open Laragon.
2. Click **Start All**.
3. Open Laragon Terminal.
4. Go to the project folder:

```powershell
F:
cd F:\Laravel-project\smart-inventory
```

5. Start Laravel:

```powershell
php artisan serve
```

6. Open another terminal and run:

```powershell
npm run dev
```

---

## Useful Commands

Clear all Laravel caches:

```bash
php artisan optimize:clear
```

Run automated tests:

```bash
php artisan test
```

Fix code formatting:

```powershell
vendor\bin\pint --parallel
```

Check code formatting:

```bash
composer lint:check
```

Run PHPStan analysis:

```bash
composer types:check
```

Run all CI checks:

```bash
composer ci:check
```

View application routes:

```bash
php artisan route:list
```

Build production assets:

```bash
npm run build
```

---

## User Roles

| Role | Typical Access |
|---|---|
| Admin | Full system access, users, roles, reports, and settings |
| Manager | Purchases, sales, inventory, contacts, payments, and reports |
| Staff | Limited operational access based on assigned permissions |

Actual access depends on the permissions assigned to each role.

---

## Main Routes

| Module | Route |
|---|---|
| Home | `/` |
| Login | `/login` |
| Dashboard | `/dashboard` |
| Products | `/products` |
| Categories | `/categories` |
| Brands | `/brands` |
| Units | `/units` |
| Suppliers | `/suppliers` |
| Customers | `/customers` |
| Create Purchase | `/purchases` |
| Manage Purchases | `/purchases/manage` |
| Create Sale | `/sales` |
| Manage Sales | `/sales/manage` |
| Inventory | `/inventory` |
| Stock Movements | `/inventory/movements` |
| Sale Returns | `/sale-returns` |
| Purchase Returns | `/purchase-returns` |
| Stock Adjustments | `/stock-adjustments` |
| Payments | `/payments` |
| Reports | `/reports` |
| Users and Roles | `/users/manage` |

Protected pages require authentication, email verification, an active account, and the appropriate permission.

---

## Project Structure

```text
app/
├── Models/
├── Services/
└── Actions/

bootstrap/
config/
database/
├── migrations/
└── seeders/

public/
resources/
├── css/
├── js/
└── views/

routes/
storage/
tests/
```

---

## Security Notes

- Never commit the real `.env` file.
- Never upload database passwords, API keys, or application secrets.
- Do not upload SQL backups containing private information.
- Use `APP_DEBUG=false` in production.
- Use HTTPS on the production server.
- Change all seeded or development passwords before deployment.
- Assign only the permissions required for each user role.

---

## Deployment Checklist

Run the following commands on the production server:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Recommended production environment:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

---

## Updating the Repository

After making project changes:

```bash
git add .
git commit -m "Update project features"
git push origin main
```

---

## Screenshots

Add screenshots inside:

```text
public/images/screenshots/
```

Then include them here:

```markdown
![Dashboard](public/images/screenshots/dashboard.png)
![Sales](public/images/screenshots/sales.png)
![Inventory](public/images/screenshots/inventory.png)
```

---

## Repository

[View the GitHub Repository](https://github.com/Taj22-47271-1/Laravel-project-smart-inventory)

---

## Developer

**MD.Mahamodul Hasan Taj**
**mhtaj655@gmail.com**
Developed as a complete inventory management web application using Laravel, Livewire, Tailwind CSS, and MySQL.

---

## License

This project is currently intended for educational, portfolio, and personal use. Add a `LICENSE` file before allowing public reuse, modification, or commercial distribution.