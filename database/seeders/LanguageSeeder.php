<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('languages')->insert([
            ['name' => 'PHP'],
            ['name' => 'JavaScript'],
            ['name' => 'Python'],
            ['name' => 'Java'],
            ['name' => 'Rust'],
        ]);
    }
}
