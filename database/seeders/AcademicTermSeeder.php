<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\School;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            AcademicTerm::create([
                'school_id' => $school->id,
                'name' => 'سال تحصیلی جاری',
                'type' => 'school_year',
                'academic_year' => now()->year . '-' . (now()->year + 1),
                'season' => null,
                'period' => 1,
                'starts_at' => now()->startOfYear(),
                'ends_at' => now()->endOfYear(),
                'is_active' => true,
                'parent_id' => null,
            ]);
        }
    }
}
