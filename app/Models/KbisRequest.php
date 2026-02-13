<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbisRequest extends Model
{
    protected $fillable = [
        'user_customer_id',
        'siret_or_siren',
        'profile',
        'company_name',
        'address',
        'first_name',
        'last_name',
        'email',
        'phone',
        'consent',
        'source_path',
    ];

    protected $casts = [
        'consent' => 'boolean',
    ];
}

