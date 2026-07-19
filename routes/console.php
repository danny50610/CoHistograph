<?php

use App\Console\Commands\ApplyRoleAndPermissionCommand;
use Illuminate\Support\Facades\Artisan;

Artisan::command('before-phpunit-setup', function () {
    $this->call('migrate', [
        '--database' => 'pgsql-age',
        '--path' => 'database/migrations-age',
        '--force' => true,
    ]);

    $this->call(ApplyRoleAndPermissionCommand::class);
})->purpose('Prepare AGE graph and roles/permissions before PHPUnit');
