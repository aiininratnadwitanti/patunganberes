<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('split_bill_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('initials', 4);
            $table->string('color', 16)->default('#16A34A');
            $table->string('phone_or_wa')->nullable();
            $table->boolean('is_current_user')->default(false);
            $table->string('payment_status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('payment_proof_url')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('public_token', 64)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_participants');
    }
};
