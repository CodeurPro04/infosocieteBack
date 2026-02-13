<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCustomer extends Model
{
    protected $table = 'users_customers';

    protected $fillable = [
        'profile',
        'siret_or_siren',
        'company_name',
        'address',
        'first_name',
        'last_name',
        'email',
        'phone',
    ];
}
