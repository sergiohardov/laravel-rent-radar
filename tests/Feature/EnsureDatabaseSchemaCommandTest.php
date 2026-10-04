<?php

use Illuminate\Support\Facades\DB;

afterEach(function () {
    DB::statement('drop schema if exists "rentradar_check"');
});

it('creates the configured schema once', function () {
    config(['database.connections.pgsql.search_path' => 'rentradar_check']);

    $this->artisan('db:ensure-schema')->expectsOutput('Schema rentradar_check is ready.')->assertSuccessful();
    $this->artisan('db:ensure-schema')->assertSuccessful();

    expect(DB::scalar("select count(*) from information_schema.schemata where schema_name = 'rentradar_check'"))->toBe(1);
});

it('leaves public alone', function () {
    config(['database.connections.pgsql.search_path' => 'public']);

    $this->artisan('db:ensure-schema')->expectsOutput('Tables live in public; nothing to create.')->assertSuccessful();
});
