<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('split_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_item_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('single');
            $table->foreignId('single_participant_id')->nullable()
                ->constrained('bill_participants')->nullOnDelete();
            $table->json('shared_participant_ids')->nullable();
            $table->json('portion_assignments')->nullable();
            $table->timestamps();

            $table->unique('receipt_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_allocations');
    }
};
