<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_customers', function (Blueprint $table) {
            $table->id();
            $table->string('profile', 50);
            $table->string('siret_or_siren', 20)->index();
            $table->string('company_name', 180)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_customers');
    }
};

