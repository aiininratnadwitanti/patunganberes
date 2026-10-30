<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iuran_pools', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique();
        });

        DB::table('iuran_pools')
            ->whereNull('public_token')
            ->get(['id'])
            ->each(function ($row) {
                DB::table('iuran_pools')
                    ->where('id', $row->id)
                    ->update(['public_token' => (string) Str::ulid()]);
            });

        Schema::table('iuran_participants', function (Blueprint $table) {
            $table->dropUnique('iuran_participants_public_token_unique');
            $table->dropColumn('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('iuran_participants', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique();
        });

        Schema::table('iuran_pools', function (Blueprint $table) {
            $table->dropUnique('iuran_pools_public_token_unique');
            $table->dropColumn('public_token');
        });
    }
};
