# DatabaseSeeder patch

Add the new seeder to `database/seeders/DatabaseSeeder.php` after your existing permission seeder:

```php
$this->call([
    RoleSeeder::class,
    PermissionSeeder::class,
    RemainingPermissionSeeder::class,
]);
```

Or run it directly once:

```powershell
php artisan db:seed --class=RemainingPermissionSeeder
```
