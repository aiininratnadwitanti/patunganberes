<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_the_profile_avatar_can_be_uploaded_from_a_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'preferred_currency' => 'IDR',
            'default_rounding' => 'nearest_100',
            'avatar' => UploadedFile::fake()->image('foto.jpg', 240, 240),
        ])->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->avatar_url);
        $this->assertStringStartsWith('avatars/', $user->avatar_url);
        Storage::disk('public')->assertExists($user->avatar_url);
        $this->assertStringContainsString('/storage/'.$user->avatar_url, $user->avatar);
    }

    public function test_the_stored_avatar_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => 'avatars/lama.jpg']);
        Storage::disk('public')->put('avatars/lama.jpg', 'x');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'preferred_currency' => 'IDR',
            'default_rounding' => 'nearest_100',
            'remove_avatar' => '1',
        ])->assertRedirect();

        $user->refresh();

        $this->assertNull($user->avatar_url);
        Storage::disk('public')->assertMissing('avatars/lama.jpg');
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
