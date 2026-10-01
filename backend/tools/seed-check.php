#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Seeder verification
|--------------------------------------------------------------------------
|
| Runs the seeders against a throwaway database and reports the row counts.
| Useful because `artisan db:seed` requires ext-dom on this machine.
|
| Run: php tools/seed-check.php
|
*/

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dbPath = '/tmp/seed_check.sqlite';

if (file_exists($dbPath)) {
    unlink($dbPath);
}

touch($dbPath);

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $dbPath,
    'database.connections.sqlite.foreign_key_constraints' => true,
]);

// AdminUserSeeder deliberately skips itself when credentials are absent, so
// supply them here to exercise the happy path the way a configured
// environment would.
config([
    'marketplace.admin.name' => 'Administrator',
    'marketplace.admin.email' => env('ADMIN_EMAIL') ?: 'admin@digital-shop.test',
    'marketplace.admin.password' => env('ADMIN_PASSWORD') ?: 'seed-check-password',
]);

$migrator = $app->make('migrator');
$outputProperty = new ReflectionProperty($migrator, 'output');
$outputProperty->setAccessible(true);
$outputProperty->setValue($migrator, null);
$migrator->setConnection('sqlite');

if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}

$migrator->run([$root.'/database/migrations']);

// Seed without going through the console renderer.
foreach ([
    AdminUserSeeder::class,
    CategorySeeder::class,
    DemoDataSeeder::class,
] as $seeder) {
    $app->make($seeder)->run();
}

echo "\nRow counts after seeding\n";
echo str_repeat('-', 40)."\n";

foreach (['users', 'shops', 'categories', 'products', 'product_files'] as $table) {
    printf("  %-16s %d\n", $table, DB::table($table)->count());
}

$admin = DB::table('users')->where('role', 'admin')->first();

echo "\n  admin email      ".(($admin->email ?? null) ?? '(none)')."\n";
echo '  admin role       '.(($admin->role ?? null) ?? '-')."\n";

// The seeder must not create an admin without credentials supplied.
if ($admin === null) {
    fwrite(STDERR, "\nFAIL: no admin was created\n");

    exit(1);
}

echo "\nOK\n\n";
