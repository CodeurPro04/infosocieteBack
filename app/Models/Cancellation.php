<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cancellation extends Model
{
    protected $fillable = [
        'email',
        'source_path',
    ];
}

