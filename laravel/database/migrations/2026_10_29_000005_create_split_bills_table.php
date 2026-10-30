<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('split_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('merchant_name')->nullable();
            $table->date('date');
            $table->string('time', 5)->nullable();
            $table->string('category')->default('restoran');
            $table->text('receipt_image')->nullable();
            $table->string('scanner_source')->default('manual');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedTinyInteger('tax_rate_percent')->default(0);
            $table->boolean('is_tax_included')->default(false);
            $table->string('tax_rule')->default('proportional');
            $table->unsignedBigInteger('service_charge_amount')->default(0);
            $table->unsignedTinyInteger('service_rate_percent')->default(0);
            $table->string('service_rule')->default('proportional');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('discount_rule')->default('proportional');
            $table->unsignedBigInteger('other_fees_amount')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->string('rounding_method')->default('nearest_100');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('split_bills');
    }
};
