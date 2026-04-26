<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Notification::truncate();

        Notification::insert([
            [
                'title' => 'MIDTERM EXAM SCHEDULE',
                'message' => 'September 25–27, 2025',
                'start_date' => '2025-09-25',
                'end_date' => '2025-09-27',
                'target_role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'SCHEDULE OF PAYMENTS',
                'message' => "September 17–19  First Year\nSeptember 20–22  Second Year\nSeptember 23–24  Third Year",
                'start_date' => '2025-09-17',
                'end_date' => '2025-09-24',
                'target_role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}