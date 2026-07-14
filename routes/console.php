<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('before-phpunit-setup', function () {
    Artisan::call('migrate', [
        '--database' => 'pgsql-age',
        '--path' => 'database/migrations-age',
        '--force' => true,
    ]);

    Artisan::call('app:apply-role-and-permission-command');

    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
})->purpose('Prepare AGE graph and roles/permissions before PHPUnit');
