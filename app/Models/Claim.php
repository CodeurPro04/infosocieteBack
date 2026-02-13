<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $fillable = [
        'name',
        'email',
        'order_ref',
        'subject',
        'message',
        'source_path',
    ];
}

