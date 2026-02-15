<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellations', function (Blueprint $table) {
            $table->string('stripe_status', 80)->nullable()->after('source_path');
            $table->unsignedInteger('stripe_cancelled_count')->default(0)->after('stripe_status');
            $table->json('stripe_details')->nullable()->after('stripe_cancelled_count');
        });
    }

    public function down(): void
    {
        Schema::table('cancellations', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_status',
                'stripe_cancelled_count',
                'stripe_details',
            ]);
        });
    }
};

