<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DataAwalSeeder::class);

        if (! app()->environment('production')) {
            $this->call(DataContohSeeder::class);
        }
    }
}
