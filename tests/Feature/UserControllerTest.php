<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // $permission = Permission::query()->firstOrCreate(
        //     ['name' => 'user.manage'],
        //     [
        //         'display_name' => '管理會員',
        //         'description' => '修改會員資料、調整會員權限、刪除會員等',
        //     ]
        // );

        // $role = Role::query()->firstOrCreate(
        //     ['name' => 'admin'],
        //     [
        //         'display_name' => '管理員',
        //         'description' => '擁有所有權限的管理員角色',
        //     ]
        // );

        // $role->syncPermissions([$permission]);
    }

    public function test_can_update_other_user_name(): void
    {
        $admin = User::factory()->create();
        $admin->addRole('admin');
        $admin->givePermission('user.manage');

        $targetUser = User::factory()->create([
            'name' => 'Old Name',
        ]);

        $this->actingAs($admin)
            ->patch(route('user.update', $targetUser), [
                'name' => 'Updated Name',
            ])
            ->assertRedirect(route('user.show', $targetUser))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_user_name_requires_name(): void
    {
        $admin = User::factory()->create();
        $admin->addRole('admin');
        $admin->givePermission('user.manage');

        $targetUser = User::factory()->create([
            'name' => 'Old Name',
        ]);

        $this->actingAs($admin)
            ->from(route('user.edit', $targetUser))
            ->patch(route('user.update', $targetUser), [
                'name' => '',
            ])
            ->assertRedirect(route('user.edit', $targetUser))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Old Name',
        ]);
    }

    public function test_self_update_with_admin_role_does_not_duplicate_role_pivot(): void
    {
        $admin = User::factory()->create();
        $admin->addRole('admin');
        $admin->givePermission('user.manage');

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('user.update', $admin), [
                'name' => 'Admin Updated',
                'role' => [$adminRole->id],
            ])
            ->assertRedirect(route('user.show', $admin))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $admin->fresh()->roles()->where('name', 'admin')->count());
    }
}
