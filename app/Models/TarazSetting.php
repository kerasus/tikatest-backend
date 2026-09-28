<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property numeric $zaribe_z
 * @property numeric $sabet_eafzoodani
 * @property bool $selected_model
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting whereSabetEafzoodani($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting whereSelectedModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TarazSetting whereZaribeZ($value)
 * @mixin \Eloquent
 */
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
