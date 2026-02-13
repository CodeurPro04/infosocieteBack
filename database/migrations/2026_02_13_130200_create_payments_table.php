<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_customer_id')->nullable()->constrained('users_customers')->nullOnDelete();
            $table->foreignId('kbis_request_id')->nullable()->constrained('kbis_requests')->nullOnDelete();
            $table->string('stripe_intent_id', 100)->nullable()->unique();
            $table->string('status', 40)->default('pending');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('eur');
            $table->string('holder_name', 160)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('siret_or_siren', 20)->nullable()->index();
            $table->string('profile', 50)->nullable();
            $table->string('company_name', 180)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('source_path', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

