#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Schema validation harness
|--------------------------------------------------------------------------
|
| Runs every migration against a throwaway SQLite database and prints the
| resulting tables, columns and foreign keys.
|
| This exists because the console renderer (termwind) requires ext-dom, which
| is not installed on every machine, and because being able to execute the
| migrations without a MySQL server makes schema regressions cheap to catch.
|
| It validates structure only. MySQL-specific features (full-text indexes,
| column collations) are guarded in the migrations and are not exercised here.
|
| Usage:  php tools/schema-check.php [path/to/database.sqlite]
|
*/

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dbPath = $argv[1] ?? '/tmp/schema_check.sqlite';

if (file_exists($dbPath)) {
    unlink($dbPath);
}

touch($dbPath);

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $dbPath,
    'database.connections.sqlite.foreign_key_constraints' => true,
]);

/** @var Migrator $migrator */
$migrator = $app->make('migrator');
$migrator->setConnection('sqlite');

// Migrator::setOutput() is typed non-nullable, and any OutputInterface makes
// it render through termwind (which needs ext-dom). Nulling the property
// directly takes its documented "no output" branch instead.
$outputProperty = new ReflectionProperty($migrator, 'output');
$outputProperty->setAccessible(true);
$outputProperty->setValue($migrator, null);

if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}

$startedAt = microtime(true);

try {
    $migrator->run([$root.'/database/migrations']);
} catch (Throwable $e) {
    fwrite(STDERR, "\nMIGRATION FAILED\n");
    fwrite(STDERR, sprintf("%s\n%s\n", $e->getMessage(), $e->getTraceAsString()));

    exit(1);
}

$elapsed = round((microtime(true) - $startedAt) * 1000);

$connection = $app->make('db')->connection('sqlite');

$tables = array_map(
    static fn (object $row): string => $row->name,
    $connection->select(
        "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
    )
);

$totalColumns = 0;
$totalIndexes = 0;

foreach ($tables as $table) {
    $columns = $connection->select(sprintf('PRAGMA table_info("%s")', $table));
    $indexes = $connection->select(sprintf('PRAGMA index_list("%s")', $table));
    $foreignKeys = $connection->select(sprintf('PRAGMA foreign_key_list("%s")', $table));

    $totalColumns += count($columns);
    $totalIndexes += count($indexes);

    printf(
        "  %-26s %2d cols  %2d idx  %d fk\n",
        $table,
        count($columns),
        count($indexes),
        count($foreignKeys),
    );
}

printf(
    "\nOK  %d tables, %d columns, %d indexes in %dms\n",
    count($tables),
    $totalColumns,
    $totalIndexes,
    $elapsed,
);
