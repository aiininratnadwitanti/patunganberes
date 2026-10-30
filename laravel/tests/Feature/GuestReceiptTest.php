<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_guest_receipt_page_renders_for_a_participant(): void
    {
        $user = User::factory()->create();

        $bill = $user->splitBills()->create([
            'title' => 'Makan Bersama',
            'merchant_name' => 'Kedai Nusantara',
            'date' => now()->toDateString(),
        ]);

        $bill->items()->create([
            'name' => 'Nasi Goreng Spesial',
            'quantity' => 1,
            'unit_price' => 25000,
            'total_price' => 25000,
        ]);

        $participant = $bill->participants()->create(['name' => 'Budi Santoso']);

        $this->get(route('guest.receipt', [$bill->code, $participant->public_token]))
            ->assertOk()
            ->assertSee('Struk Split Bill Personal')
            ->assertSee('Budi Santoso');
    }

    public function test_an_unknown_token_returns_not_found(): void
    {
        $user = User::factory()->create();

        $bill = $user->splitBills()->create([
            'title' => 'Makan Bersama',
            'merchant_name' => 'Kedai Nusantara',
            'date' => now()->toDateString(),
        ]);

        $this->get(route('guest.receipt', [$bill->code, 'token-tidak-dikenal']))
            ->assertNotFound();
    }
}
