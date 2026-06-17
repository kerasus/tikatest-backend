<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreRegistration extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'parent_username',
        'username',
        'password',
        'sms_id',
    ];
}
