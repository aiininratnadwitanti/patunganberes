<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.index', [
            'user' => auth()->user()->load('bankAccounts'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
            'preferred_currency' => ['required', 'string', 'max:10'],
            'default_rounding' => ['required', Rule::in(['nearest_100', 'nearest_500', 'nearest_1000', 'none'])],
        ]);

        unset($data['avatar'], $data['remove_avatar']);

        if ($request->hasFile('avatar')) {
            $data['avatar_url'] = $request->file('avatar')->store('avatars', 'public');
            $this->deleteStoredAvatar($user->avatar_url);
        } elseif ($request->boolean('remove_avatar')) {
            $data['avatar_url'] = null;
            $this->deleteStoredAvatar($user->avatar_url);
        }

        $user->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Profil berhasil diperbarui.']);
    }

    public function storeBank(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'bank' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $account = $user->bankAccounts()->create([
            'bank' => $data['bank'],
            'account_number' => $data['account_number'],
            'account_holder' => $data['account_holder'],
            'is_default' => false,
        ]);

        if ($request->boolean('is_default') || $user->bankAccounts()->count() === 1) {
            $this->makeDefault($user, $account);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Rekening berhasil ditambahkan.']);
    }

    public function updateBank(Request $request, BankAccount $bank): RedirectResponse
    {
        abort_unless($bank->user_id === auth()->id(), 403);

        $data = $request->validate([
            'bank' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
        ]);

        $bank->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Rekening berhasil diperbarui.']);
    }

    public function setDefaultBank(BankAccount $bank): RedirectResponse
    {
        abort_unless($bank->user_id === auth()->id(), 403);

        $this->makeDefault(auth()->user(), $bank);

        return back()->with('toast', ['type' => 'success', 'message' => 'Rekening utama diperbarui.']);
    }

    public function destroyBank(BankAccount $bank): RedirectResponse
    {
        abort_unless($bank->user_id === auth()->id(), 403);

        $wasDefault = $bank->is_default;
        $bank->delete();

        if ($wasDefault) {
            $next = auth()->user()->bankAccounts()->first();

            if ($next) {
                $this->makeDefault(auth()->user(), $next);
            }
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Rekening dihapus.']);
    }

    private function makeDefault($user, BankAccount $account): void
    {
        $user->bankAccounts()->update(['is_default' => false]);
        $account->update(['is_default' => true]);
    }

    private function deleteStoredAvatar(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
