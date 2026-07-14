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

    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
})->purpose('Prepare AGE graph and roles/permissions before PHPUnit');
