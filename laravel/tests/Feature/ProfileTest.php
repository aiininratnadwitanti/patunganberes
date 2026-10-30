<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'phone' => '081200000000',
            'preferred_currency' => 'IDR',
            'default_rounding' => 'nearest_500',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('081200000000', $user->phone);
        $this->assertSame('nearest_500', $user->default_rounding);

        // Pajak & servis default tidak lagi diatur dari halaman profil.
        $this->assertSame(10, $user->default_tax_rate);
        $this->assertSame(5, $user->default_service_rate);
    }

    public function test_bank_accounts_can_be_managed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('profile.banks.store'), [
            'bank' => 'Bank Mandiri',
            'account_number' => '9999',
            'account_holder' => $user->name,
            'is_default' => '1',
        ])->assertRedirect();

        $bank = $user->bankAccounts()->where('bank', 'Bank Mandiri')->firstOrFail();
        $this->assertTrue($bank->is_default);

        $this->put(route('profile.banks.update', $bank), [
            'bank' => 'Bank Mandiri',
            'account_number' => '8888',
            'account_holder' => $user->name,
        ])->assertRedirect();
        $this->assertSame('8888', $bank->fresh()->account_number);

        $this->delete(route('profile.banks.destroy', $bank))->assertRedirect();
        $this->assertDatabaseMissing('bank_accounts', ['id' => $bank->id]);
    }

    public function test_a_user_cannot_modify_another_users_bank_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $bank = $owner->bankAccounts()->create([
            'bank' => 'BCA',
            'account_number' => '1234',
            'account_holder' => $owner->name,
        ]);

        $this->actingAs($other)
            ->delete(route('profile.banks.destroy', $bank))
            ->assertForbidden();
    }
}
