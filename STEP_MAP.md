# Step 132–200 map

## 132–139: Sale returns

- 132–133: `app/Services/SaleReturnService.php`
- 134–136: `resources/views/pages/sale-returns/⚡index.blade.php`
- 137–139: `resources/views/pages/sale-returns/⚡manage.blade.php`
- Routes: `patches/routes-web.md`

## 140–151: Purchase returns

- Migrations: `database/migrations/2026_07_20_900001_*` and `900002_*`
- Models: `app/Models/PurchaseReturn.php`, `PurchaseReturnItem.php`
- Service: `app/Services/PurchaseReturnService.php`
- Pages: `resources/views/pages/purchase-returns/`
- Existing-model changes: `patches/model-relationships.md`

## 152–158: Stock adjustments

- Migrations: `900003_*`, `900004_*`
- Models: `StockAdjustment.php`, `StockAdjustmentItem.php`
- Service: `StockAdjustmentService.php`
- Pages: `resources/views/pages/stock-adjustments/`

## 159–166: Payments and due collection

- Migration: `900005_create_payments_table.php`
- Model: `app/Models/Payment.php`
- Service: `app/Services/PaymentService.php`
- Page: `resources/views/pages/payments/⚡index.blade.php`
- Sale/Purchase payment relationships: `patches/model-relationships.md`

## 167–176: Dashboard and reports

- `app/Services/ReportService.php`
- `resources/views/pages/dashboard/⚡inventory.blade.php`
- `resources/views/pages/reports/⚡index.blade.php`

## 177–182: Users and permissions

- Migration: `900006_add_is_active_to_users_table.php`
- Page: `resources/views/pages/users/⚡index.blade.php`
- Middleware: `app/Http/Middleware/EnsureUserIsActive.php`
- Seeder: `database/seeders/RemainingPermissionSeeder.php`
- User/bootstrap patches in `patches/`

## 183–188: Navigation and UI

- `patches/sidebar-links.blade.php`
- All included pages contain responsive tables, empty states, filters and Livewire loading-compatible actions.

## 189–200: Testing and production

- `tests/Feature/RemainingModulesSmokeTest.php`
- `tests/Feature/PaymentServiceTest.php`
- Commands and deployment checklist: `README.md`
