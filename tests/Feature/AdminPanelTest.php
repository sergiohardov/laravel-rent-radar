<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

it('redirects guests to the panel login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows the panel to a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk();
});

it('seeds the admin from config', function () {
    config(['admin.email' => 'admin@example.com', 'admin.password' => 'dev-password']);

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->sole();
    expect(Hash::check('dev-password', $admin->password))->toBeTrue();
});

it('skips the admin when credentials are empty', function () {
    config(['admin.email' => null, 'admin.password' => null]);

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->count())->toBe(0);
});
