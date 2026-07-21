<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RemainingModulesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_remaining_module_pages(): void
    {
        $permissions = [
            'view dashboard',
            'view sale returns',
            'view purchase returns',
            'view stock adjustments',
            'view payments',
            'view reports',
            'view users',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->givePermissionTo($permissions);

        $this->actingAs($user);

        $routes = [
            'dashboard',
            'sale-returns.index',
            'sale-returns.manage',
            'purchase-returns.index',
            'purchase-returns.manage',
            'stock-adjustments.index',
            'stock-adjustments.manage',
            'payments.index',
            'reports.index',
            'users.index',
        ];

        foreach ($routes as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
