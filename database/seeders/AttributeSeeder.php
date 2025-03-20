<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('attributes')->insert([
            ['name' => 'Experience Level', 'type' => 'select', 'options' => json_encode(['Junior', 'Mid', 'Senior'])],
            ['name' => 'Work Permit Required', 'type' => 'boolean', 'options' => null],
            ['name' => 'Expected Start Date', 'type' => 'date', 'options' => null],
        ]);
    }
}
