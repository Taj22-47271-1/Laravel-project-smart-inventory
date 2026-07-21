# `bootstrap/app.php` middleware patch

Add the import near the top:

```php
use App\Http\Middleware\EnsureUserIsActive;
```

Inside your existing `->withMiddleware(function (Middleware $middleware): void { ... })` block, add the `active` alias without removing the existing Spatie aliases:

```php
$middleware->alias([
    'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    'active' => EnsureUserIsActive::class,
]);
```

Apply `active` to the existing authenticated route groups too, not only to the new routes, so a disabled account cannot continue using older pages.
