<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
