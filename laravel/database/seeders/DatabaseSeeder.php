<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data contoh opsional untuk mencoba aplikasi (akun demo + beberapa
     * tagihan & iuran). Jalankan `php artisan db:seed`, atau lewati sama
     * sekali kalau ingin mulai dari aplikasi yang benar-benar kosong.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
