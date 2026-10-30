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
        Schema::table('iuran_participants', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique();
        });

        DB::table('iuran_participants')
            ->whereNull('public_token')
            ->get(['id'])
            ->each(function ($row) {
                DB::table('iuran_participants')
                    ->where('id', $row->id)
                    ->update(['public_token' => (string) Str::ulid()]);
            });
    }

    public function down(): void
    {
        Schema::table('iuran_participants', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
