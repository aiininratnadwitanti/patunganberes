<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\BillParticipant;
use App\Models\SplitBill;
use App\Services\ReceiptScannerService;
use App\Services\SplitBillCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class SplitBillController extends Controller
{
    public function __construct(
        private readonly SplitBillCalculator $calculator,
        private readonly ReceiptScannerService $scanner,
    ) {}

    public function index(Request $request): View
    {
        $bills = auth()->user()->splitBills()->with(['participants', 'shares'])->get();

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        $filtered = $bills->filter(function (SplitBill $bill) use ($search, $status) {
            $matchesSearch = $search === ''
                || Str::contains(Str::lower($bill->title), Str::lower($search))
                || Str::contains(Str::lower($bill->merchant_name ?? ''), Str::lower($search))
                || Str::contains(Str::lower($bill->code), Str::lower($search));

            $matchesStatus = match ($status) {
                'pending' => $bill->isPending(),
                'completed' => $bill->status === 'completed',
                'archived' => $bill->status === 'archived',
                default => true,
            };

            return $matchesSearch && $matchesStatus;
        })->values();

        return view('history.index', [
            'bills' => $filtered,
            'search' => $search,
            'status' => $status,
            'allBills' => $bills,
        ]);
    }

    public function create(): View
    {
        return view('split-bills.create', [
            'scannerConfigured' => $this->scanner->isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'mode' => ['required', Rule::in(['scanner', 'manual'])],
            'receipt' => ['nullable', 'image', 'max:10240'],
        ]);

        $user = auth()->user();

        if ($request->input('mode') === 'manual') {
            $bill = $this->createEmptyBill($user);

            return redirect()->route('split-bills.validate', $bill)
                ->with('toast', ['type' => 'info', 'message' => 'Isi detail struk secara manual.']);
        }

        if (! $request->hasFile('receipt')) {
            return back()->withErrors(['receipt' => 'Pilih foto struk terlebih dahulu.'])->withInput();
        }

        try {
            $bill = $this->scanReceipt($request->file('receipt'), $user);
        } catch (RuntimeException $e) {
            return back()
                ->withErrors(['receipt' => $e->getMessage()])
                ->with('toast', ['type' => 'error', 'message' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('split-bills.validate', $bill)
            ->with('toast', ['type' => 'success', 'message' => 'Struk berhasil dipindai oleh AI!']);
    }

    public function scan(Request $request): JsonResponse
    {
        try {
            $request->validate(['receipt' => ['required', 'image', 'max:10240']]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first('receipt')], 422);
        }

        try {
            $bill = $this->scanReceipt($request->file('receipt'), auth()->user());
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['redirect' => route('split-bills.validate', $bill)]);
    }

    private function scanReceipt(UploadedFile $file, $user): SplitBill
    {
        $path = $file->store('receipts', 'public');

        try {
            $contents = Storage::disk('public')->get($path);
            $mime = $file->getMimeType() ?: 'image/jpeg';

            $scan = $this->scanner->scan($contents, $mime);

            return $this->createFromScan($user, $scan, $path);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }
    }

    public function show(SplitBill $splitBill): View
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['items', 'participants.share', 'shares.participant', 'allocations']);

        if ($splitBill->shares->isEmpty()) {
            $this->calculator->calculateForBill($splitBill, true);
            $splitBill->load('shares.participant', 'participants.share');
        }

        return view('split-bills.show', [
            'bill' => $splitBill,
            'defaultBank' => auth()->user()->defaultBankAccount(),
        ]);
    }

    public function showValidate(SplitBill $splitBill): View
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['items', 'participants']);

        return view('split-bills.validate', [
            'bill' => $splitBill,
        ]);
    }

    public function updateValidate(Request $request, SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $data = $request->validate([
            'merchant_name' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'string', 'max:10'],
            'category' => ['required', 'string', 'max:50'],
            'tax_amount' => ['nullable', 'integer', 'min:0'],
            'service_charge_amount' => ['nullable', 'integer', 'min:0'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'other_fees_amount' => ['nullable', 'integer', 'min:0'],
            'is_tax_included' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $splitBill) {
            $existingIds = $splitBill->items()->pluck('id')->all();
            $submittedIds = [];

            foreach (array_values($data['items']) as $index => $itemData) {
                $itemId = $itemData['id'] ?? null;
                $attributes = [
                    'name' => $itemData['name'],
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => (int) $itemData['unit_price'],
                    'total_price' => (int) $itemData['quantity'] * (int) $itemData['unit_price'],
                    'sort_order' => $index,
                ];

                if ($itemId && in_array((int) $itemId, $existingIds, true)) {
                    $item = $splitBill->items()->whereKey($itemId)->first();
                    $item?->update($attributes);
                    $submittedIds[] = (int) $itemId;
                } else {
                    $item = $splitBill->items()->create($attributes + ['confidence' => 'high']);
                    $submittedIds[] = $item->id;
                }
            }

            $splitBill->items()->whereNotIn('id', $submittedIds)->delete();

            $itemsSubtotal = (int) $splitBill->items()->sum('total_price');
            $tax = (int) ($data['tax_amount'] ?? 0);
            $service = (int) ($data['service_charge_amount'] ?? 0);
            $discount = (int) ($data['discount_amount'] ?? 0);
            $other = (int) ($data['other_fees_amount'] ?? 0);
            $taxIncluded = (bool) ($data['is_tax_included'] ?? false);

            $splitBill->update([
                'merchant_name' => $data['merchant_name'],
                'date' => $data['date'],
                'time' => normalize_time($data['time'] ?? null),
                'category' => $data['category'],
                'subtotal' => $itemsSubtotal,
                'tax_amount' => $tax,
                'tax_rate_percent' => $itemsSubtotal > 0 ? (int) round($tax / $itemsSubtotal * 100) : 0,
                'is_tax_included' => $taxIncluded,
                'service_charge_amount' => $service,
                'service_rate_percent' => $itemsSubtotal > 0 ? (int) round($service / $itemsSubtotal * 100) : 0,
                'discount_amount' => $discount,
                'other_fees_amount' => $other,
                'grand_total' => max(0, $itemsSubtotal + ($taxIncluded ? 0 : $tax) + $service + $other - $discount),
            ]);
        });

        if ($request->input('save_mode') === 'draft') {
            return redirect()->route('history.index')
                ->with('toast', ['type' => 'success', 'message' => 'Detail struk disimpan sebagai draft.']);
        }

        return redirect()->route('split-bills.participants', $splitBill)
            ->with('toast', ['type' => 'success', 'message' => 'Detail struk berhasil disimpan.']);
    }

    public function showParticipants(SplitBill $splitBill): View
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['participants', 'items']);

        return view('split-bills.participants', [
            'bill' => $splitBill,
        ]);
    }

    public function updateParticipants(Request $request, SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.id' => ['nullable'],
            'participants.*.name' => ['required', 'string', 'max:100'],
            'participants.*.is_current_user' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $splitBill) {
            $existing = $splitBill->participants()->get()->keyBy('id');
            $keptIds = [];
            $currentUserAssigned = false;

            foreach (array_values($data['participants']) as $index => $participantData) {
                $id = $participantData['id'] ?? null;
                $isCurrent = (bool) ($participantData['is_current_user'] ?? false) && ! $currentUserAssigned;
                $currentUserAssigned = $currentUserAssigned || $isCurrent;

                $attributes = [
                    'name' => trim($participantData['name']),
                    'initials' => participant_initials($participantData['name']),
                    'color' => participant_color($index),
                    'is_current_user' => $isCurrent,
                    'sort_order' => $index,
                ];

                if ($id && $existing->has((int) $id)) {
                    $participant = $existing->get((int) $id);
                    $participant->update($attributes);
                } else {
                    $participant = $splitBill->participants()->create($attributes + [
                        'payment_status' => $isCurrent ? 'paid' : 'unpaid',
                        'paid_at' => $isCurrent ? now() : null,
                    ]);
                }

                $keptIds[] = $participant->id;
            }

            $splitBill->participants()->whereNotIn('id', $keptIds)->delete();

            $splitBill->update([
                'title' => $data['title'],
                'date' => $data['date'],
            ]);
        });

        $splitBill->refresh();
        $this->calculator->calculateForBill($splitBill, true);

        return redirect()->route('split-bills.allocation', $splitBill)
            ->with('toast', ['type' => 'success', 'message' => 'Peserta berhasil disimpan.']);
    }

    public function showAllocation(SplitBill $splitBill): View
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['items', 'participants', 'allocations']);

        $allocations = $splitBill->allocations->mapWithKeys(function ($allocation) {
            return [$allocation->receipt_item_id => [
                'type' => $allocation->type,
                'single_participant_id' => $allocation->single_participant_id,
                'shared_participant_ids' => $allocation->shared_participant_ids ?? [],
                'portion_assignments' => $allocation->portion_assignments ?? [],
            ]];
        });

        return view('split-bills.allocation', [
            'bill' => $splitBill,
            'initialAllocations' => $allocations,
        ]);
    }

    public function updateAllocation(Request $request, SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $request->validate([
            'allocations' => ['required', 'string'],
        ]);

        $decoded = json_decode((string) $request->input('allocations'), true);

        if (! is_array($decoded)) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Data pembagian tidak valid.']);
        }

        $splitBill->load(['items', 'participants']);

        $participantIds = $splitBill->participants->pluck('id')->all();
        $itemIds = $splitBill->items->pluck('id')->all();

        DB::transaction(function () use ($decoded, $splitBill, $participantIds, $itemIds) {
            $splitBill->allocations()->delete();

            foreach ($decoded as $itemId => $allocation) {
                if (! in_array((int) $itemId, $itemIds, true) || ! is_array($allocation)) {
                    continue;
                }

                $type = $allocation['type'] ?? null;

                if (! in_array($type, ['single', 'shared_equal', 'by_portion'], true)) {
                    continue;
                }

                $singleId = isset($allocation['single_participant_id']) && in_array((int) $allocation['single_participant_id'], $participantIds, true)
                    ? (int) $allocation['single_participant_id']
                    : null;

                $sharedIds = collect($allocation['shared_participant_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => in_array($id, $participantIds, true))
                    ->values()
                    ->all();

                $portions = collect($allocation['portion_assignments'] ?? [])
                    ->map(fn ($portion) => [
                        'participant_id' => isset($portion['participant_id']) ? (int) $portion['participant_id'] : null,
                        'quantity' => isset($portion['quantity']) ? (int) $portion['quantity'] : 0,
                    ])
                    ->filter(fn ($portion) => in_array($portion['participant_id'], $participantIds, true) && $portion['quantity'] > 0)
                    ->values()
                    ->all();

                if ($type === 'single' && ! $singleId) {
                    continue;
                }

                if ($type === 'shared_equal' && count($sharedIds) === 0) {
                    continue;
                }

                if ($type === 'by_portion' && count($portions) === 0) {
                    continue;
                }

                $splitBill->allocations()->create([
                    'receipt_item_id' => (int) $itemId,
                    'type' => $type,
                    'single_participant_id' => $type === 'single' ? $singleId : null,
                    'shared_participant_ids' => $type === 'shared_equal' ? $sharedIds : [],
                    'portion_assignments' => $type === 'by_portion' ? $portions : [],
                ]);
            }
        });

        $splitBill->refresh();
        $this->calculator->calculateForBill($splitBill, true);

        return redirect()->route('split-bills.summary', $splitBill)
            ->with('toast', ['type' => 'success', 'message' => 'Pembagian item berhasil disimpan.']);
    }

    public function showSummary(SplitBill $splitBill): View
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['items', 'participants', 'allocations', 'shares.participant']);

        $result = $this->calculator->calculateForBill($splitBill);

        $shares = collect($result['shares'])->keyBy('participant_id');

        return view('split-bills.summary', [
            'bill' => $splitBill,
            'result' => $result,
            'shares' => $shares,
            'defaultBank' => auth()->user()->defaultBankAccount(),
        ]);
    }

    public function updateRules(Request $request, SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $data = $request->validate([
            'tax_rule' => ['required', Rule::in(['proportional', 'equal', 'none'])],
            'service_rule' => ['required', Rule::in(['proportional', 'equal', 'none'])],
            'discount_rule' => ['required', Rule::in(['proportional', 'equal', 'none'])],
            'rounding_method' => ['required', Rule::in(['nearest_100', 'nearest_500', 'nearest_1000', 'none'])],
        ]);

        $splitBill->update($data);
        $splitBill->refresh();
        $this->calculator->calculateForBill($splitBill, true);

        return redirect()->route('split-bills.summary', $splitBill)
            ->with('toast', ['type' => 'success', 'message' => 'Aturan pembagian berhasil diperbarui.']);
    }

    public function confirm(SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $result = $this->calculator->calculateForBill($splitBill, true);

        if (count($result['unallocated_item_ids']) > 0) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Masih ada item yang belum dibagi ke peserta.',
            ]);
        }

        $splitBill->update([
            'status' => 'pending_payment',
            'is_locked' => true,
        ]);

        AppNotification::create([
            'user_id' => $splitBill->user_id,
            'title' => 'Split Bill Berhasil Dibuat',
            'message' => 'Struk "'.$splitBill->title.'" siap dibagikan ke '.$splitBill->participants()->count().' peserta.',
            'type' => 'receipt_ready',
            'related_id' => $splitBill->code,
            'related_type' => 'split',
        ]);

        return redirect()->route('split-bills.receipts', $splitBill)
            ->with('toast', ['type' => 'success', 'message' => 'Split bill berhasil dibuat!']);
    }

    public function showReceipts(SplitBill $splitBill): View
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['items', 'participants.share', 'shares.participant', 'allocations']);

        if ($splitBill->shares->isEmpty()) {
            $this->calculator->calculateForBill($splitBill, true);
            $splitBill->load('shares.participant', 'participants.share');
        }

        return view('split-bills.receipts', [
            'bill' => $splitBill,
            'defaultBank' => auth()->user()->defaultBankAccount(),
        ]);
    }

    public function updatePaymentStatus(Request $request, SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
            'payment_status' => ['required', Rule::in(BillParticipant::PAYMENT_STATUSES)],
            'verification_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $participant = $splitBill->participants()->whereKey($data['participant_id'])->firstOrFail();

        $participant->update([
            'payment_status' => $data['payment_status'],
            'verification_notes' => $data['verification_notes'] ?? null,
            'paid_at' => $data['payment_status'] === 'paid' ? ($participant->paid_at ?? now()) : null,
        ]);

        $splitBill->load('participants');
        $splitBill->refreshPaymentStatus();

        $labels = [
            'paid' => 'Pembayaran '.$participant->name.' berhasil diverifikasi.',
            'needs_fix' => 'Pembayaran '.$participant->name.' ditandai perlu perbaikan.',
            'waiting_verification' => 'Status '.$participant->name.' diubah ke menunggu verifikasi.',
            'unpaid' => 'Status '.$participant->name.' diubah ke belum bayar.',
        ];

        AppNotification::create([
            'user_id' => $splitBill->user_id,
            'title' => $data['payment_status'] === 'paid' ? 'Pembayaran Diverifikasi' : 'Status Pembayaran Diperbarui',
            'message' => $labels[$data['payment_status']].' ('.$splitBill->title.')',
            'type' => $data['payment_status'] === 'paid' ? 'payment_verified' : 'payment_updated',
            'related_id' => $splitBill->code,
            'related_type' => 'split',
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => $labels[$data['payment_status']]]);
    }

    public function duplicate(SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $splitBill->load(['items', 'participants', 'allocations']);

        $copy = $splitBill->duplicateForUser(auth()->user());
        $this->calculator->calculateForBill($copy, true);

        return redirect()->route('split-bills.show', $copy)
            ->with('toast', ['type' => 'success', 'message' => 'Split bill berhasil diduplikasi.']);
    }

    public function archive(SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $splitBill->update(['status' => 'archived']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Split bill dipindahkan ke arsip.']);
    }

    public function destroy(SplitBill $splitBill): RedirectResponse
    {
        $this->authorizeOwner($splitBill);

        $splitBill->delete();

        return redirect()->route('history.index')
            ->with('toast', ['type' => 'success', 'message' => 'Split bill berhasil dihapus.']);
    }

    private function createEmptyBill($user): SplitBill
    {
        $bill = $user->splitBills()->create([
            'title' => 'Split Bill Baru',
            'merchant_name' => null,
            'date' => now()->toDateString(),
            'time' => now()->format('H:i'),
            'category' => 'makan_bersama',
            'scanner_source' => 'manual',
            'subtotal' => 0,
            'tax_amount' => 0,
            'tax_rate_percent' => (int) ($user->default_tax_rate ?? 10),
            'is_tax_included' => false,
            'tax_rule' => 'proportional',
            'service_charge_amount' => 0,
            'service_rate_percent' => (int) ($user->default_service_rate ?? 5),
            'service_rule' => 'proportional',
            'discount_amount' => 0,
            'discount_rule' => 'proportional',
            'other_fees_amount' => 0,
            'grand_total' => 0,
            'rounding_method' => $user->default_rounding ?? 'nearest_100',
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $bill->items()->create([
            'name' => 'Item baru',
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
            'confidence' => 'high',
            'sort_order' => 0,
        ]);

        $bill->participants()->create([
            'name' => $user->name,
            'initials' => participant_initials($user->name),
            'color' => '#16A34A',
            'is_current_user' => true,
            'payment_status' => 'paid',
            'paid_at' => now(),
            'phone_or_wa' => $user->phone,
            'sort_order' => 0,
        ]);

        foreach ([['Budi Santoso', '081298765432'], ['Dinda Kirana', '081345678901']] as $index => [$name, $phone]) {
            $bill->participants()->create([
                'name' => $name,
                'initials' => participant_initials($name),
                'color' => participant_color($index + 1),
                'is_current_user' => false,
                'payment_status' => 'unpaid',
                'phone_or_wa' => $phone,
                'sort_order' => $index + 1,
            ]);
        }

        return $bill;
    }

    private function createFromScan($user, array $scan, ?string $receiptPath = null): SplitBill
    {
        $items = $scan['items'] ?? [];
        $subtotal = (int) ($scan['subtotal'] ?? collect($items)->sum('total_price'));

        $bill = $user->splitBills()->create([
            'title' => 'Makan di '.($scan['merchant_name'] ?? 'Restoran'),
            'merchant_name' => $scan['merchant_name'] ?? null,
            'date' => normalize_date($scan['date'] ?? null) ?? now()->toDateString(),
            'time' => normalize_time($scan['time'] ?? null) ?? now()->format('H:i'),
            'category' => $scan['category'] ?? 'makan_bersama',
            'receipt_image' => $receiptPath,
            'scanner_source' => 'gemini_ai',
            'subtotal' => $subtotal,
            'tax_amount' => (int) ($scan['tax_amount'] ?? 0),
            'tax_rate_percent' => (int) ($scan['tax_rate_percent'] ?? 0),
            'is_tax_included' => (bool) ($scan['is_tax_included'] ?? false),
            'tax_rule' => (int) ($scan['tax_amount'] ?? 0) > 0 ? 'proportional' : 'none',
            'service_charge_amount' => (int) ($scan['service_charge_amount'] ?? 0),
            'service_rate_percent' => (int) ($scan['service_rate_percent'] ?? 0),
            'service_rule' => (int) ($scan['service_charge_amount'] ?? 0) > 0 ? 'proportional' : 'none',
            'discount_amount' => (int) ($scan['discount_amount'] ?? 0),
            'discount_rule' => (int) ($scan['discount_amount'] ?? 0) > 0 ? 'proportional' : 'none',
            'other_fees_amount' => 0,
            'grand_total' => (int) ($scan['grand_total'] ?? $subtotal),
            'rounding_method' => $user->default_rounding ?? 'nearest_100',
            'status' => 'draft',
            'notes' => $scan['notes'] ?? null,
            'is_locked' => false,
        ]);

        foreach (array_values($items) as $index => $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (int) ($item['unit_price'] ?? 0);

            $bill->items()->create([
                'name' => $item['name'] ?? 'Item '.($index + 1),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => (int) ($item['total_price'] ?? $quantity * $unitPrice),
                'confidence' => $item['confidence'] ?? 'high',
                'sort_order' => $index,
            ]);
        }

        if ($bill->items()->count() === 0) {
            $bill->items()->create([
                'name' => 'Item baru',
                'quantity' => 1,
                'unit_price' => 0,
                'total_price' => 0,
                'confidence' => 'high',
                'sort_order' => 0,
            ]);
        }

        $bill->participants()->create([
            'name' => $user->name,
            'initials' => participant_initials($user->name),
            'color' => '#16A34A',
            'is_current_user' => true,
            'payment_status' => 'paid',
            'paid_at' => now(),
            'phone_or_wa' => $user->phone,
            'sort_order' => 0,
        ]);

        foreach ([['Budi Santoso', '081298765432'], ['Dinda Kirana', '081345678901']] as $index => [$name, $phone]) {
            $bill->participants()->create([
                'name' => $name,
                'initials' => participant_initials($name),
                'color' => participant_color($index + 1),
                'is_current_user' => false,
                'payment_status' => 'unpaid',
                'phone_or_wa' => $phone,
                'sort_order' => $index + 1,
            ]);
        }

        return $bill;
    }

    private function authorizeOwner(SplitBill $bill): void
    {
        abort_unless($bill->user_id === auth()->id(), 403);
    }
}
