<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cancellation extends Model
{
    protected $fillable = [
        'email',
        'source_path',
        'stripe_status',
        'stripe_cancelled_count',
        'stripe_details',
    ];

    protected $casts = [
        'stripe_cancelled_count' => 'integer',
        'stripe_details' => 'array',
    ];
}
