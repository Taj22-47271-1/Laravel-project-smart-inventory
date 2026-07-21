# Smart Inventory — Remaining Implementation Pack

This pack contains the remaining functional modules for the Laravel 13 + Livewire 4 Smart Inventory project built in the preceding steps.

## Included

- Sale return workflow: complete/cancel service, create page and management page
- Purchase return workflow: migrations, models, service, create page and management page
- Stock adjustment workflow: migrations, models, service, create page and management page
- Customer receipt and supplier payment workflow
- Polymorphic payment history
- Dashboard and report pages
- User/role/status management page
- Inactive-user middleware
- Remaining permission seeder
- Sidebar, route, model and bootstrap patch snippets
- Smoke and payment service tests

## Important assumption

The project already contains the files completed in earlier steps:

- `InventoryService`
- `Purchase`, `PurchaseItem`, `Sale`, `SaleItem`, `SaleReturn`, `SaleReturnItem`
- `Inventory`, `StockMovement`, `Product`, `Customer`, `Supplier`, `User`
- Spatie Laravel Permission installation and existing role/permission middleware aliases

Do not blindly overwrite your existing models. Apply `patches/model-relationships.md` to them.

## Installation on Windows

1. Back up the project or commit it to Git.
2. Extract this pack.
3. Open PowerShell inside the extracted pack.
4. Run:

```powershell
.\INSTALL.ps1 -ProjectPath "F:\Laravel-project\smart-inventory"
```

5. Apply these manual patches:

- `patches/model-relationships.md`
- `patches/routes-web.md`
- `patches/bootstrap-app-middleware.md`
- `patches/sidebar-links.blade.php`

6. In the project terminal run:

```powershell
composer dump-autoload
php artisan migrate
php artisan db:seed --class=RemainingPermissionSeeder
php artisan optimize:clear
npm run build
php artisan test
```

7. Start the project:

```powershell
php artisan serve
```

## Pages

- `/inventory-dashboard`
- `/sale-returns`
- `/sale-returns/manage`
- `/purchase-returns`
- `/purchase-returns/manage`
- `/stock-adjustments`
- `/stock-adjustments/manage`
- `/payments`
- `/reports`
- `/users/manage`

## Verification order

1. Complete an existing sale return and verify stock increases.
2. Complete a purchase return and verify stock decreases.
3. Complete stock-in and stock-out adjustments.
4. Record a partial sale receipt and check paid/due values.
5. Record a supplier payment and check purchase paid/due values.
6. Open reports and verify the selected date range.
7. Create a manager/staff user and verify role-based menus.
8. Disable a test user and verify the account is logged out when an `active` protected route is opened.

## Financial note

The report's “Margin Estimate” is `net sales - net purchases`; it is not a true accounting gross-profit calculation. A precise gross-profit report requires cost-of-goods-sold values captured per completed sale item.

## Production checklist

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
php artisan test
```

Set production values in `.env`, including `APP_ENV=production`, `APP_DEBUG=false`, the production database, mail settings and a valid `APP_URL`. Back up the database before migrations.
