<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dev Admin
    |--------------------------------------------------------------------------
    |
    | Credentials of the Filament admin created by AdminUserSeeder in dev.
    | In production the admin is created with `php artisan make:filament-user`.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
