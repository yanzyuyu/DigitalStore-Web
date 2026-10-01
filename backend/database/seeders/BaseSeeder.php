<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder with console output that is safe to call outside artisan.
 *
 * `$this->command` is only populated when a seeder is invoked through the
 * console; tests and tooling call seeders directly, where it is null.
 */
abstract class BaseSeeder extends Seeder
{
    protected function info(string $message): void
    {
        if (isset($this->command)) {
            $this->command->info($message);
        }
    }

    protected function warn(string $message): void
    {
        if (isset($this->command)) {
            $this->command->warn($message);
        }
    }
}
