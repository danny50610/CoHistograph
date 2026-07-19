<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('before-phpunit-setup', function () {
    $this->call('migrate', [
        '--database' => 'pgsql-age',
        '--path' => 'database/migrations-age',
        '--force' => true,
    ]);

    $this->call('app:apply-role-and-permission-command');
})->purpose('Prepare AGE graph and roles/permissions before PHPUnit');
