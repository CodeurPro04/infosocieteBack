<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'submission_id',
        'type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

