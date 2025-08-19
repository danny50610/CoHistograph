<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class ApplyRoleAndPermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apply-role-and-permission-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply roles and permissions settings from configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $permissionIdMap = [];

        $permissions = config('cohistograph.roles-and-permissions.permissions');
        foreach ($permissions as $permissionName => $permissionData) {
            $permission = Permission::updateOrCreate([
                'name' => $permissionName,
            ], [
                'display_name' => $permissionData['display_name'],
                'description' => $permissionData['description'],
            ]);
            $permissionIdMap[$permissionName] = $permission->id;
        }

        $roles = config('cohistograph.roles-and-permissions.roles');
        foreach ($roles as $roleName => $roleData) {
            $role = \Laratrust\Models\Role::updateOrCreate([
                'name' => $roleName,
            ], [
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
            ]);

            $permissionId = collect($roleData['permissions'])->map(function ($permissionName) use ($permissionIdMap) {
                return $permissionIdMap[$permissionName];
            })->toArray();

            $role->permissions()->sync($permissionId);
        }
    }
}
