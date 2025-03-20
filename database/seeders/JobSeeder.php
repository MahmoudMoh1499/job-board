<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class JobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $languages = DB::table('languages')->pluck('id')->toArray();
        $locations = DB::table('locations')->pluck('id')->toArray();
        $categories = DB::table('categories')->pluck('id')->toArray();

        foreach (range(1, 10) as $index) {
            $jobId = DB::table('jobs')->insert([
                'title' => $faker->jobTitle,
                'description' => $faker->paragraph,
                'company_name' => $faker->company,
                'salary_min' => $faker->numberBetween(3000, 7000),
                'salary_max' => $faker->numberBetween(7000, 15000),
                'is_remote' => $faker->boolean,
                'job_type' => $faker->randomElement(['full-time', 'part-time', 'contract', 'freelance']),
                'status' => $faker->randomElement(['draft', 'published', 'archived']),
                'published_at' => now(),
            ]);


            // Assign random languages
            DB::table('job_language')->insert([
                ['job_id' => $jobId, 'language_id' => $faker->randomElement($languages)],
            ]);

            // Assign random locations
            DB::table('job_location')->insert([
                ['job_id' => $jobId, 'location_id' => $faker->randomElement($locations)],
            ]);

            // Assign random categories
            DB::table('job_category')->insert([
                ['job_id' => $jobId, 'category_id' => $faker->randomElement($categories)],
            ]);
        }
    }
}
