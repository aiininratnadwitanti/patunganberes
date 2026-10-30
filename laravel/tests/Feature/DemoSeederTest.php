<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_a_working_demo_account(): void
    {
        $this->seed(DemoSeeder::class);

        $user = User::where('email', DemoSeeder::EMAIL)->firstOrFail();

        $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $user->password));
        $this->assertSame(2, $user->splitBills()->count());
        $this->assertSame(1, $user->iuranPools()->count());
        $this->assertTrue($user->bankAccounts()->exists());

        $confirmed = $user->splitBills()->where('status', '!=', 'draft')->firstOrFail();
        $this->assertSame(3, $confirmed->shares()->count());
        $this->assertSame($confirmed->grand_total, (int) $confirmed->shares()->sum('final_total'));
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::where('email', DemoSeeder::EMAIL)->count());
    }
}
