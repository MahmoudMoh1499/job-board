<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class JobAttributeValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $jobs = DB::table('jobs')->pluck('id');
        $attributes = DB::table('attributes')->pluck('id');

        foreach ($jobs as $job) {
            foreach ($attributes as $attribute) {
                DB::table('job_attribute_values')->insert([
                    'job_id' => $job,
                    'attribute_id' => $attribute,
                    'value' => $faker->randomElement(['Junior', 'Mid', 'Senior', 'true', 'false', $faker->date]),
                ]);
            }
        }
    }
}
