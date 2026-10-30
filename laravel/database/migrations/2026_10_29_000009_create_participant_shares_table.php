<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('split_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_participant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('items_subtotal')->default(0);
            $table->unsignedBigInteger('discount_share')->default(0);
            $table->unsignedBigInteger('tax_share')->default(0);
            $table->unsignedBigInteger('service_share')->default(0);
            $table->unsignedBigInteger('other_fees_share')->default(0);
            $table->bigInteger('rounding_adjustment')->default(0);
            $table->unsignedBigInteger('final_total')->default(0);
            $table->json('items_list')->nullable();
            $table->timestamps();

            $table->unique(['split_bill_id', 'bill_participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_shares');
    }
};
