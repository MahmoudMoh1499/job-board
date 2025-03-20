<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('locations')->insert([
            ['city' => 'Dubai', 'state' => '', 'country' => 'UAE'],
            ['city' => 'New York', 'state' => 'NY', 'country' => 'USA'],
        ]);
    }
}
