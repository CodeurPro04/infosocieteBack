<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kbis_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_customer_id')->nullable()->constrained('users_customers')->nullOnDelete();
            $table->string('siret_or_siren', 20)->index();
            $table->string('profile', 50);
            $table->string('company_name', 180)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->boolean('consent')->default(false);
            $table->string('source_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kbis_requests');
    }
};

