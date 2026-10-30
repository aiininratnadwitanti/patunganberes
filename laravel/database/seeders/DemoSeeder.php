<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\SplitBillCalculator;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public const EMAIL = 'aiinin@patunganberes.test';

    public const PASSWORD = 'password';

    /**
     * Akun + data contoh untuk mencoba aplikasi tanpa membuat semuanya dari nol.
     * Idempoten: kalau akun demo sudah ada, seeder ini tidak melakukan apa pun.
     */
    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            return;
        }

        $calc = app(SplitBillCalculator::class);

        $user = User::create([
            'name' => 'Aiinin',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'phone' => '081234567890',
            'preferred_currency' => 'IDR',
            'default_tax_rate' => 10,
            'default_service_rate' => 5,
            'default_rounding' => 'nearest_100',
        ]);

        $user->bankAccounts()->create([
            'bank' => 'BCA',
            'account_number' => '8830192841',
            'account_holder' => 'Aiinin',
            'is_default' => true,
        ]);

        // --- Split bill yang sudah dikonfirmasi (ada share + status bayar) ---
        $bill1 = $user->splitBills()->create([
            'title' => 'Makan Malam Tim Produk',
            'merchant_name' => 'Kedai Nusantara',
            'date' => now()->subDay()->toDateString(),
            'time' => '19:30',
            'category' => 'restoran',
            'scanner_source' => 'gemini_ai',
            'subtotal' => 144000,
            'tax_rate_percent' => 10,
            'tax_amount' => 14400,
            'service_rate_percent' => 5,
            'service_charge_amount' => 7200,
            'discount_amount' => 0,
            'other_fees_amount' => 0,
            'grand_total' => 165600,
            'rounding_method' => 'nearest_100',
        ]);

        foreach ([
            ['Nasi Goreng Spesial', 2, 35000],
            ['Es Teh Manis', 3, 8000],
            ['Ayam Bakar Madu', 1, 45000],
            ['Kerupuk Udang', 1, 5000],
        ] as $i => [$name, $quantity, $unitPrice]) {
            $bill1->items()->create([
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
                'sort_order' => $i,
            ]);
        }

        $people = [
            ['Aiinin', true],
            ['Budi Santoso', false],
            ['Citra Dewi', false],
        ];

        $participants = [];

        foreach ($people as $i => [$name, $isOwner]) {
            $participants[] = $bill1->participants()->create([
                'name' => $name,
                'is_current_user' => $isOwner,
                'payment_status' => $isOwner ? 'paid' : 'unpaid',
                'paid_at' => $isOwner ? now()->subHours(3) : null,
                'sort_order' => $i,
            ]);
        }

        $bill1->refresh();
        $items = $bill1->items->keyBy('name');

        $bill1->allocations()->create([
            'receipt_item_id' => $items['Nasi Goreng Spesial']->id,
            'type' => 'by_portion',
            'portion_assignments' => [
                ['participant_id' => $participants[0]->id, 'quantity' => 1],
                ['participant_id' => $participants[1]->id, 'quantity' => 1],
            ],
        ]);
        $bill1->allocations()->create([
            'receipt_item_id' => $items['Es Teh Manis']->id,
            'type' => 'shared_equal',
            'shared_participant_ids' => [$participants[0]->id, $participants[1]->id, $participants[2]->id],
        ]);
        $bill1->allocations()->create([
            'receipt_item_id' => $items['Ayam Bakar Madu']->id,
            'type' => 'single',
            'single_participant_id' => $participants[2]->id,
        ]);
        $bill1->allocations()->create([
            'receipt_item_id' => $items['Kerupuk Udang']->id,
            'type' => 'shared_equal',
            'shared_participant_ids' => [$participants[0]->id, $participants[1]->id, $participants[2]->id],
        ]);

        $calc->calculateForBill($bill1->fresh(), true);
        $bill1->update(['status' => 'pending_payment']);

        $participants[1]->update([
            'payment_status' => 'waiting_verification',
            'payment_method' => 'BCA',
            'verification_notes' => 'Transfer via BCA Mobile',
        ]);

        // --- Split bill yang masih draft (untuk halaman Periksa & Bagi Item) ---
        $bill2 = $user->splitBills()->create([
            'title' => 'Sarapan Bareng Kafe',
            'merchant_name' => 'Kopi Senja',
            'date' => now()->toDateString(),
            'time' => '08:15',
            'category' => 'kafe',
            'scanner_source' => 'gemini_ai',
            'subtotal' => 90000,
            'tax_rate_percent' => 10,
            'tax_amount' => 9000,
            'service_rate_percent' => 0,
            'service_charge_amount' => 0,
            'discount_amount' => 0,
            'other_fees_amount' => 0,
            'grand_total' => 99000,
            'rounding_method' => 'nearest_100',
            'status' => 'draft',
        ]);

        foreach ([['Kopi Susu Gula Aren', 2, 25000], ['Roti Bakar Keju', 2, 20000]] as $i => [$name, $quantity, $unitPrice]) {
            $bill2->items()->create([
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
                'sort_order' => $i,
            ]);
        }

        foreach ([['Aiinin', true], ['Dewi Lestari', false], ['Andi Pratama', false]] as $i => [$name, $isOwner]) {
            $bill2->participants()->create([
                'name' => $name,
                'is_current_user' => $isOwner,
                'payment_status' => 'unpaid',
                'sort_order' => $i,
            ]);
        }

        // --- Iuran bersama ---
        $pool = $user->iuranPools()->create([
            'code' => 'IRN-2026-KLS2',
            'title' => 'Kas Kelas XI IPA 2',
            'description' => 'Iuran bulanan untuk study tour akhir tahun.',
            'target_amount' => 1200000,
            'fee_per_person' => 100000,
            'pricing_type' => 'fixed',
            'deadline' => now()->addDays(14)->toDateString(),
            'category' => 'kas_kelas',
            'bank_name' => 'BCA',
            'account_number' => '8830192841',
            'account_holder' => 'Aiinin',
            'status' => 'active',
        ]);

        foreach ([
            ['Aiinin', 100000, 'paid'],
            ['Budi Santoso', 100000, 'waiting_verification'],
            ['Citra Dewi', 100000, 'waiting_verification'],
            ['Dewi Lestari', 100000, 'paid'],
            ['Eka Saputra', 50000, 'waiting_verification'],
        ] as $i => [$name, $amount, $status]) {
            $pool->participants()->create([
                'name' => $name,
                'color' => participant_color($i),
                'target_fee' => 100000,
                'paid_amount' => $amount,
                'status' => $status,
                'paid_at' => now()->subHours($i + 1),
                'sort_order' => $i,
            ]);
        }

        $pool->refreshCollected();

        // --- Notifikasi ---
        AppNotification::create(['user_id' => $user->id, 'title' => 'Setoran Iuran Baru', 'message' => 'Budi Santoso menyetor Rp100.000 untuk "'.$pool->title.'".', 'type' => 'iuran_reported', 'related_id' => $pool->code, 'related_type' => 'iuran']);
        AppNotification::create(['user_id' => $user->id, 'title' => 'Pembayaran Dilaporkan', 'message' => 'Budi Santoso melaporkan pembayaran untuk "'.$bill1->title.'".', 'type' => 'payment_reported', 'related_id' => $bill1->code, 'related_type' => 'split']);
        AppNotification::create(['user_id' => $user->id, 'title' => 'Split Bill Dibuat', 'message' => 'Tagihan "'.$bill1->title.'" siap dibagikan ke peserta.', 'type' => 'bill_created', 'related_id' => $bill1->code, 'related_type' => 'split']);
        AppNotification::create(['user_id' => $user->id, 'title' => 'Iuran Baru Dibuat', 'message' => 'Iuran "'.$pool->title.'" dengan target Rp1.200.000 sudah dibuat.', 'type' => 'iuran_created', 'related_id' => $pool->code, 'related_type' => 'iuran', 'is_read' => true]);
    }
}
