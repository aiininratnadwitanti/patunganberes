@extends('layouts.app')

@section('title', 'Iuran Bersama')

@section('content')
@php
    $selectedPaid = $selected ? $selected->participants->where('status', 'paid')->count() : 0;
    $selectedWaiting = $selected ? $selected->participants->where('status', 'waiting_verification')->count() : 0;
    $selectedProgress = $selected ? $selected->progress : 0;

    $inviteMessage = $selected
        ? "Halo! Ayo ikut iuran *{$selected->title}*.\nTarget: ".format_rupiah($selected->target_amount)."\nSaran per orang: ".format_rupiah($selected->fee_per_person)."\nDeadline: ".$selected->deadline?->translatedFormat('d F Y')."\nTransfer ke {$selected->bank_name} {$selected->account_number} a.n {$selected->account_holder}.\nIsi form setoranmu di sini: {$poolLink}"
        : '';
    $inviteUrl = $selected ? whatsapp_url($inviteMessage) : '#';
@endphp

<div x-data="{ open: false }" class="space-y-4">
    <section class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-[#14532d] via-[#166534] to-[#16a34a] p-5 text-white sm:p-6">
        <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white/85 backdrop-blur">
                    <i data-lucide="piggy-bank" class="h-3 w-3"></i> Iuran & Kas Bersama
                </span>
                <h2 class="mt-3 text-[24px] font-extrabold leading-tight tracking-tight sm:text-[29px]">Patungan terkumpul,<br><span class="text-[#bbf7d0]">tanpa perlu tanya satu-satu.</span></h2>
                <p class="mt-1.5 max-w-lg text-[11.5px] leading-relaxed text-white/75">Pantau siapa yang sudah bayar, kirim pengingat, dan simpan bukti transfer dalam satu tempat.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                <button type="button" class="btn bg-white text-primary-dark justify-center hover:bg-light" @click="open = true">
                    <i data-lucide="plus" class="h-4 w-4"></i> Buat Iuran Baru
                </button>
                @if ($selected)
                    <a href="{{ $inviteUrl }}" target="_blank" rel="noopener noreferrer" class="btn border-white/25 bg-white/10 text-white justify-center hover:bg-white/20 backdrop-blur">
                        <i data-lucide="share-2" class="h-4 w-4"></i> Bagikan Iuran
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat-card">
            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Total Terkumpul</p>
            <p class="mt-1.5 text-[19px] font-extrabold text-ink hash">{{ format_rupiah($totalCollected) }}</p>
            <p class="mt-1 text-[10px] text-muted">dari {{ format_rupiah($totalTarget) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Iuran Aktif</p>
            <p class="mt-1.5 text-[19px] font-extrabold text-ink">{{ $activeCount }}</p>
            <p class="mt-1 text-[10px] text-muted">sedang berjalan</p>
        </div>
        <div class="stat-card">
            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Total Iuran</p>
            <p class="mt-1.5 text-[19px] font-extrabold text-ink">{{ $pools->count() }}</p>
            <p class="mt-1 text-[10px] text-muted">kelompok</p>
        </div>
        <div class="stat-card">
            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Menunggu Verifikasi</p>
            <p class="mt-1.5 text-[19px] font-extrabold text-ink">{{ $selectedWaiting }}</p>
            <p class="mt-1 text-[10px] text-muted">pada iuran terpilih</p>
        </div>
    </section>

    @if ($pools->isEmpty())
        <section class="card px-5 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-light text-primary"><i data-lucide="piggy-bank" class="h-6 w-6"></i></span>
            <h3 class="mt-3 text-[15px] font-extrabold text-ink">Belum ada iuran</h3>
            <p class="mx-auto mt-1 max-w-sm text-[11.5px] leading-relaxed text-muted">Buat iuran pertama untuk kas kelas, hadiah, atau acara bersama. Semua pembayaran tercatat rapi.</p>
            <button type="button" class="btn btn-primary mt-4" @click="open = true">Buat Iuran Baru</button>
        </section>
    @else
        <div class="grid gap-4 lg:grid-cols-[0.8fr_1.6fr]">
            <aside class="space-y-2.5">
                <div class="flex items-center justify-between">
                    <h3 class="text-[13px] font-extrabold tracking-tight text-ink">Daftar Iuran</h3>
                    <span class="text-[10px] font-bold text-muted">{{ $pools->count() }} kelompok</span>
                </div>

                @foreach ($pools as $pool)
                    @php $isSelected = $selected && $selected->id === $pool->id; @endphp
                    <a href="{{ route('iuran.index', ['pool' => $pool->id]) }}"
                       class="block rounded-2xl border p-3 transition {{ $isSelected ? 'border-primary bg-white shadow-sm' : 'border-line bg-white hover:border-primary/30' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-[11.5px] font-extrabold text-ink">{{ $pool->title }}</p>
                                <p class="mt-0.5 text-[9.5px] font-medium text-muted">{{ $pool->code }} · {{ $pool->participants->count() }} peserta</p>
                            </div>
                            <span class="badge {{ $pool->status === 'completed' ? 'badge-success' : 'badge-warning' }}">{{ $pool->status === 'completed' ? 'Tuntas' : 'Aktif' }}</span>
                        </div>

                        <div class="progress-track mt-2 h-1.5"><div class="progress-fill" style="width: {{ $pool->progress }}%"></div></div>
                        <div class="mt-1.5 flex items-center justify-between text-[9.5px]">
                            <span class="font-semibold text-muted">{{ format_rupiah($pool->current_collected) }}</span>
                            <span class="font-bold text-primary-dark">{{ $pool->progress }}%</span>
                        </div>
                    </a>
                @endforeach

                <button type="button" class="flex w-full items-center justify-center gap-1.5 rounded-2xl border border-dashed border-line py-3 text-[10.5px] font-bold text-muted transition hover:border-primary/40 hover:text-primary" @click="open = true">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Iuran baru
                </button>
            </aside>

            @if ($selected)
                <div class="space-y-3">
                    <section class="card overflow-hidden">
                        <div class="border-b border-line bg-gradient-to-br from-light/70 to-white p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $selected->category)) }}</span>
                                    <h3 class="mt-1.5 text-[20px] font-extrabold leading-tight tracking-tight text-ink">{{ $selected->title }}</h3>
                                    <p class="mt-1 max-w-xl text-[11px] leading-relaxed text-muted">{{ $selected->description ?: 'Tanpa deskripsi.' }}</p>
                                    <p class="mt-2 flex items-center gap-1.5 text-[10px] font-semibold text-muted">
                                        <i data-lucide="calendar-clock" class="h-3.5 w-3.5 text-primary"></i>
                                        Deadline {{ $selected->deadline?->translatedFormat('d F Y') }}
                                        <span class="text-line">·</span>
                                        <span>{{ format_rupiah($selected->fee_per_person) }} / orang</span>
                                    </p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <div class="flex items-center justify-between gap-2 sm:justify-end">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Terkumpul</p>
                                        <button type="button" class="icon-btn h-7 w-7 border border-line text-danger" title="Hapus iuran"
                                                data-confirm-delete
                                                data-confirm-title="Hapus iuran?"
                                                data-confirm-message="Iuran &quot;{{ $selected->title }}&quot; akan dihapus permanen beserta peserta dan setoran di dalamnya."
                                                data-confirm-url="{{ route('iuran.destroy', $selected) }}"
                                                data-confirm-method="DELETE"
                                                data-confirm-label="Hapus">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </div>
                                    <p class="text-[24px] font-extrabold leading-none tracking-tight text-primary-dark hash">{{ format_rupiah($selected->current_collected) }}</p>
                                    <p class="mt-1 text-[10px] text-muted">dari target {{ format_rupiah($selected->target_amount) }}</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="progress-track h-2.5"><div class="progress-fill" style="width: {{ $selectedProgress }}%"></div></div>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[10px]">
                                    <span class="font-semibold text-muted">{{ $selectedPaid }} lunas · {{ $selectedWaiting }} menunggu verifikasi</span>
                                    <span class="font-extrabold text-primary-dark">{{ $selectedProgress }}% tercapai</span>
                                </div>
                                @if ($selected->current_collected > $selected->target_amount)
                                    <p class="mt-2 inline-flex items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-[10px] font-bold text-primary-dark">
                                        <i data-lucide="trending-up" class="h-3 w-3"></i> Melebihi target {{ format_rupiah($selected->current_collected - $selected->target_amount) }} — setoran tambahan tetap diterima
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="grid gap-2.5 border-b border-line p-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-[#f8faf9] p-3">
                                <p class="text-[9px] font-bold uppercase tracking-[0.1em] text-muted">Rekening Iuran</p>
                                <p class="mt-1 text-[11.5px] font-extrabold text-ink">{{ $selected->bank_name }}</p>
                                <p class="text-[11px] font-bold text-primary-dark">{{ $selected->account_number }}</p>
                                <p class="text-[9.5px] text-muted">a.n. {{ $selected->account_holder }}</p>
                            </div>
                            <div class="rounded-xl bg-[#f8faf9] p-3">
                                <p class="text-[9px] font-bold uppercase tracking-[0.1em] text-muted">Link Form Peserta</p>
                                <p class="mt-1 truncate text-[10.5px] font-semibold text-ink" title="{{ $poolLink }}">{{ $poolLink }}</p>
                                <a href="{{ $poolLink }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold text-primary hover:underline">
                                    <i data-lucide="external-link" class="h-3 w-3"></i> Buka form peserta (tes)
                                </a>
                            </div>
                            <div class="flex flex-col justify-center gap-1.5 sm:col-span-2 sm:flex-row">
                                <button type="button" class="btn btn-ghost btn-sm flex-1 justify-center" data-copy="{{ $selected->account_number }}" data-copy-message="Nomor rekening iuran berhasil disalin.">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i> Salin Rekening
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm flex-1 justify-center" data-copy="{{ $poolLink }}" data-copy-message="Link form peserta berhasil disalin.">
                                    <i data-lucide="link" class="h-3.5 w-3.5"></i> Salin Link Form
                                </button>
                                <a href="{{ $inviteUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm flex-1 justify-center">
                                    <i data-lucide="message-circle" class="h-3.5 w-3.5"></i> Bagikan via WhatsApp
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center justify-between px-4 py-3">
                            <h4 class="text-[12.5px] font-extrabold text-ink">Setoran Masuk</h4>
                            <span class="text-[10px] font-bold text-muted">{{ $selected->participants->count() }} setoran</span>
                        </div>

                        <div class="divide-y divide-line border-t border-line">
                            @forelse ($selected->participants as $participant)
                                <div class="flex flex-wrap items-center gap-2.5 px-4 py-2.5">
                                    <span class="avatar-initial h-8 w-8 text-[10px]" style="background: {{ $participant->color }}">{{ $participant->initials }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[11px] font-bold text-ink">{{ $participant->name }}</p>
                                        <p class="text-[9.5px] text-muted">
                                            {{ format_rupiah($participant->paid_amount) }}
                                            @if ($participant->paid_at) · {{ $participant->paid_at->translatedFormat('d M H:i') }} @endif
                                            @if ($participant->notes) · {{ $participant->notes }} @endif
                                        </p>
                                    </div>
                                    @include('partials.payment-status', ['status' => $participant->status])

                                    <div class="flex items-center gap-1">
                                        @if ($participant->proof_url)
                                            <a href="{{ str_starts_with($participant->proof_url, 'http') ? $participant->proof_url : asset('storage/'.$participant->proof_url) }}" target="_blank" rel="noopener noreferrer" class="icon-btn h-7 w-7 border border-line" title="Lihat bukti">
                                                <i data-lucide="image" class="h-3.5 w-3.5"></i>
                                            </a>
                                        @endif

                                        @if ($participant->status === 'waiting_verification')
                                            <form method="POST" action="{{ route('iuran.participant.update', $selected) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                                <input type="hidden" name="status" value="paid">
                                                <button type="submit" class="btn btn-primary btn-sm">Verifikasi</button>
                                            </form>
                                            <form method="POST" action="{{ route('iuran.participant.update', $selected) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn btn-ghost btn-sm text-danger">Tolak</button>
                                            </form>
                                        @elseif ($participant->status === 'paid')
                                            <form method="POST" action="{{ route('iuran.participant.update', $selected) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                                <input type="hidden" name="status" value="waiting_verification">
                                                <button type="submit" class="btn btn-ghost btn-sm">Batal Verifikasi</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <p class="text-[11.5px] font-bold text-ink">Belum ada setoran</p>
                                    <p class="mt-0.5 text-[10.5px] text-muted">Bagikan link form ke grup agar peserta bisa menyetor.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <div class="card p-4">
                        <div class="flex gap-2.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="info" class="h-4 w-4"></i></span>
                            <p class="text-[10px] leading-relaxed text-muted">Setoran yang dikirim peserta sudah langsung menambah total terkumpul. Cek buktinya, lalu klik <span class="font-bold text-ink">Verifikasi</span> untuk mengunci, atau <span class="font-bold text-ink">Tolak</span> bila bukti tidak sesuai.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div x-cloak x-show="open" class="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" @click="open = false"></div>

        <div class="relative max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-[26px] border border-line bg-white p-5 shadow-2xl sm:rounded-3xl"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Iuran Baru</p>
                    <h3 class="mt-1 text-[20px] font-extrabold tracking-tight text-ink">Buat iuran bersama</h3>
                    <p class="mt-1 text-[11px] text-muted">Tentukan target dan nominal. Bisa diubah nanti.</p>
                </div>
                <button type="button" class="icon-btn border border-line" @click="open = false" aria-label="Tutup"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>

            @if ($errors->any())
                <div class="mt-3 rounded-xl border border-danger/20 bg-danger/5 px-3 py-2 text-[11px] font-semibold text-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('iuran.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="label" for="title">Nama Iuran</label>
                    <input class="input" type="text" id="title" name="title" value="{{ old('title') }}" placeholder="Contoh: Kas Kelas November" required>
                </div>
                <div>
                    <label class="label" for="description">Deskripsi</label>
                    <textarea class="textarea" id="description" name="description" placeholder="Untuk keperluan apa iuran ini?">{{ old('description') }}</textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="target_amount">Target Dana (Rp)</label>
                        <input class="input" type="number" min="1000" step="1000" id="target_amount" name="target_amount" value="{{ old('target_amount') }}" placeholder="1200000" required>
                    </div>
                    <div>
                        <label class="label" for="fee_per_person">Iuran per Orang (Rp)</label>
                        <input class="input" type="number" min="0" step="1000" id="fee_per_person" name="fee_per_person" value="{{ old('fee_per_person') }}" placeholder="Kosongkan untuk otomatis">
                    </div>
                    <div>
                        <label class="label" for="pricing_type">Jenis Nominal</label>
                        <select class="select" id="pricing_type" name="pricing_type">
                            <option value="fixed" @selected(old('pricing_type') === 'fixed')>Tetap per orang</option>
                            <option value="flexible" @selected(old('pricing_type') === 'flexible')>Bebas / sukarela</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="deadline">Deadline</label>
                        <input class="input" type="date" id="deadline" name="deadline" value="{{ old('deadline', now()->addDays(14)->toDateString()) }}" required>
                    </div>
                    <div>
                        <label class="label" for="category">Kategori</label>
                        <select class="select" id="category" name="category">
                            @foreach (['kas_kelas' => 'Kas Kelas', 'hadiah' => 'Hadiah', 'acara' => 'Acara', 'belanja' => 'Belanja', 'lainnya' => 'Lainnya'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="bank_name">Bank / E-Wallet</label>
                        <input class="input" type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $defaultBank->bank ?? 'BCA') }}" required>
                    </div>
                    <div>
                        <label class="label" for="account_number">Nomor Rekening</label>
                        <input class="input" type="text" id="account_number" name="account_number" value="{{ old('account_number', $defaultBank->account_number ?? '') }}" required>
                    </div>
                    <div>
                        <label class="label" for="account_holder">Atas Nama</label>
                        <input class="input" type="text" id="account_holder" name="account_holder" value="{{ old('account_holder', $defaultBank->account_holder ?? $navUser->name) }}" required>
                    </div>
                </div>

                <p class="rounded-xl bg-[#f8faf9] p-3 text-[10.5px] leading-relaxed text-muted">
                    Iuran baru selalu mulai dari <span class="font-bold text-ink">0%</span>. Setelah dibuat, bagikan <span class="font-bold text-ink">satu link form</span> ke grup — tiap peserta mengisi nama, nominal, dan bukti. Nominal langsung mengurangi sisa target, lalu kamu tinggal verifikasi buktinya.
                </p>

                <div class="flex gap-2 pt-1">
                    <button type="button" class="btn btn-ghost flex-1 justify-center" @click="open = false">Batal</button>
                    <button type="submit" class="btn btn-primary flex-1 justify-center"><i data-lucide="plus" class="h-4 w-4"></i> Buat Iuran</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
