<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iuran_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('target_amount')->default(0);
            $table->unsignedBigInteger('current_collected')->default(0);
            $table->unsignedBigInteger('fee_per_person')->default(0);
            $table->string('pricing_type')->default('fixed');
            $table->date('deadline')->nullable();
            $table->string('category')->default('kas_kelas');
            $table->string('bank_name')->default('BCA');
            $table->string('account_number')->default('');
            $table->string('account_holder')->default('');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iuran_pools');
    }
};
