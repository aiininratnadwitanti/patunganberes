<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iuran_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iuran_pool_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('initials', 4);
            $table->string('color', 16)->default('#16A34A');
            $table->unsignedBigInteger('target_fee')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status')->default('unpaid');
            $table->text('proof_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iuran_participants');
    }
};
