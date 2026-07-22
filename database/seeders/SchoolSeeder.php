<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $schools = [
            [
                'code' => 'SCH-001',
                'name' => 'مدرسه نمونه اول',
                'address' => 'تهران، خیابان انقلاب، پلاک 1',
                'website' => 'https://school1.example.com',
                'logo_url' => '/uploads/schools/school1-logo.png',
                'type' => 'school',
                'account_url' => 'https://school1.example.com/account',
            ],
            [
                'code' => 'SCH-002',
                'name' => 'مدرسه نمونه دوم',
                'address' => 'تهران، خیابان ولیعصر، پلاک 2',
                'website' => 'https://school2.example.com',
                'logo_url' => '/uploads/schools/school2-logo.png',
                'type' => 'school',
                'account_url' => 'https://school2.example.com/account',
            ],
            [
                'code' => 'SCH-003',
                'name' => 'موسسه آموزشی نمونه',
                'address' => 'تهران، خیابان آزادی، پلاک 3',
                'website' => 'https://institute1.example.com',
                'logo_url' => '/uploads/schools/institute1-logo.png',
                'type' => 'institute',
                'account_url' => 'https://institute1.example.com/account',
            ],
        ];

        foreach ($schools as $school) {
            School::firstOrCreate(['code' => $school['code']], $school);
        }
    }
}
