<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_customer_id',
        'kbis_request_id',
        'stripe_intent_id',
        'status',
        'amount',
        'currency',
        'holder_name',
        'email',
        'siret_or_siren',
        'profile',
        'company_name',
        'address',
        'first_name',
        'last_name',
        'phone',
        'source_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];
}

