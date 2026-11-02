<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_allocations', function (Blueprint $table) {
            $table->foreignId('payer_participant_id')->nullable()
                ->constrained('bill_participants')->nullOnDelete();
        });

        Schema::table('participant_shares', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('item_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_participant_id');
        });

        Schema::table('participant_shares', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
