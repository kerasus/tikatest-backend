<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarazSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'zaribe_z',
        'sabet_eafzoodani',
        'selected_model',
    ];

    protected $casts = [
        'zaribe_z' => 'decimal:2',
        'sabet_eafzoodani' => 'decimal:2',
        'selected_model' => 'boolean',
    ];
}
