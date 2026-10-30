@extends('layouts.app')

@section('title', 'Scan Struk Baru')

@section('content')
<div class="space-y-4">
    <div class="card flex items-center gap-2.5 overflow-x-auto p-3 sm:p-4">
        @foreach ([['1', 'Foto Struk', true], ['2', 'Periksa Detail', false], ['3', 'Bagi Item', false], ['4', 'Bagikan', false]] as [$number, $label, $active])
            <div class="flex items-center gap-2 whitespace-nowrap">
                <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-extrabold {{ $active ? 'bg-primary text-white' : 'bg-[#f1f5f2] text-muted' }}">{{ $number }}</span>
                <span class="text-[11px] font-bold {{ $active ? 'text-ink' : 'text-muted' }}">{{ $label }}</span>
            </div>
            @if (! $loop->last)
                <span class="h-px w-6 bg-line"></span>
            @endif
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="card p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-[16px] font-extrabold tracking-tight text-ink">Upload Foto Struk</h2>
                    <p class="mt-0.5 text-[11.5px] leading-relaxed text-muted">Pastikan seluruh struk terlihat jelas, tidak blur, dan pencahayaan cukup.</p>
                </div>
                <span class="badge badge-success"><i data-lucide="sparkles" class="h-3 w-3"></i> Gemini AI</span>
            </div>

            <form method="POST" action="{{ route('split-bills.store') }}" enctype="multipart/form-data" class="mt-4"
                  x-data="{
                    dragging: false,
                    fileName: '',
                    preview: null,
                    scanning: false,
                    elapsed: 0,
                    timer: null,
                    error: '',
                    handleFile() {
                        const file = this.$refs.receipt.files[0];
                        if (!file) { this.fileName = ''; this.preview = null; return; }
                        this.fileName = file.name;
                        this.preview = URL.createObjectURL(file);
                        this.$nextTick(() => this.submit());
                    },
                    drop(event) {
                        this.dragging = false;
                        const files = event.dataTransfer.files;
                        if (files && files.length) {
                            const transfer = new DataTransfer();
                            transfer.items.add(files[0]);
                            this.$refs.receipt.files = transfer.files;
                            this.handleFile();
                        }
                    },
                    openPicker() {
                        this.$refs.receipt.removeAttribute('capture');
                        this.$refs.receipt.click();
                    },
                    cameraOpen: false,
                    cameraError: '',
                    stream: null,
                    async openCamera() {
                        this.cameraError = '';

                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.cameraError = 'Browser ini tidak mendukung akses kamera. Gunakan &quot;Pilih Foto&quot; saja.';
                            window.toast('warning', this.cameraError);
                            return;
                        }

                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: { ideal: 'environment' } },
                                audio: false,
                            });
                            this.cameraOpen = true;
                            this.$nextTick(() => {
                                const video = this.$refs.cameraVideo;
                                if (video) {
                                    video.srcObject = this.stream;
                                    video.play().catch(() => {});
                                }
                            });
                        } catch (e) {
                            this.cameraError = 'Tidak bisa membuka kamera. Izinkan akses kamera di browser, atau pakai &quot;Pilih Foto&quot;.';
                            window.toast('error', this.cameraError);
                        }
                    },
                    closeCamera() {
                        if (this.stream) {
                            this.stream.getTracks().forEach((track) => track.stop());
                            this.stream = null;
                        }
                        this.cameraOpen = false;
                    },
                    capturePhoto() {
                        const video = this.$refs.cameraVideo;

                        if (!video || !video.videoWidth) {
                            this.cameraError = 'Kamera belum siap. Tunggu sebentar lalu coba lagi.';
                            return;
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                        canvas.toBlob((blob) => {
                            if (!blob) {
                                this.cameraError = 'Gagal mengambil gambar. Coba lagi.';
                                return;
                            }

                            const file = new File([blob], 'kamera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                            const transfer = new DataTransfer();
                            transfer.items.add(file);

                            this.$refs.receipt.files = transfer.files;
                            this.fileName = file.name;
                            this.preview = URL.createObjectURL(file);
                            this.closeCamera();
                            this.$nextTick(() => this.submit());
                        }, 'image/jpeg', 0.9);
                    },
                    async submit() {
                        if (!this.$refs.receipt.files.length) {
                            window.toast('warning', 'Pilih foto struk terlebih dahulu.');
                            return;
                        }

                        this.error = '';
                        this.scanning = true;
                        this.elapsed = 0;
                        this.timer = setInterval(() => { this.elapsed++; }, 1000);

                        const controller = new AbortController();
                        const guard = setTimeout(() => controller.abort(), 60000);
                        const body = new FormData();
                        body.append('receipt', this.$refs.receipt.files[0]);

                        try {
                            const response = await fetch('{{ route('split-bills.scan') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                                body,
                                signal: controller.signal,
                            });

                            const data = await response.json().catch(() => ({}));

                            if (response.ok && data.redirect) {
                                window.location.href = data.redirect;
                                return;
                            }

                            this.error = data.error || 'Gagal memindai struk. Coba lagi atau gunakan input manual.';
                            window.toast('error', this.error);
                        } catch (e) {
                            this.error = 'Pemindaian gagal atau terlalu lama. Coba lagi atau gunakan input manual.';
                            window.toast('error', this.error);
                        } finally {
                            clearTimeout(guard);
                            clearInterval(this.timer);
                            this.timer = null;
                            this.scanning = false;
                        }
                    }
                  }"
                  @submit.prevent="submit()">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="file" name="receipt" accept="image/*" class="hidden" x-ref="receipt" @change="handleFile()">

                <div class="upload-zone" :class="{ 'is-dragging': dragging }"
                     @click="openPicker()"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="drop($event)">
                    <template x-if="!preview">
                        <div class="flex flex-col items-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-primary shadow-sm">
                                <i data-lucide="camera" class="h-6 w-6"></i>
                            </span>
                            <p class="mt-3 text-[13px] font-extrabold text-ink">Klik atau tarik foto struk ke sini</p>
                            <p class="mt-1 text-[10.5px] font-medium text-muted">JPG, PNG, atau WEBP · Maksimal 10MB</p>
                            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                <span class="btn btn-primary btn-sm pointer-events-none">
                                    <i data-lucide="image-plus" class="h-3.5 w-3.5"></i> Pilih Foto
                                </span>
                                <button type="button" class="btn btn-ghost btn-sm" @click.stop="openCamera()">
                                    <i data-lucide="camera" class="h-3.5 w-3.5"></i> Buka Kamera
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="preview">
                        <div class="w-full">
                            <img :src="preview" alt="Pratinjau struk" class="mx-auto max-h-64 rounded-xl object-contain shadow-sm">
                            <p class="mt-2 truncate text-center text-[11px] font-semibold text-ink" x-text="fileName"></p>
                            <p class="mt-0.5 text-center text-[10px] text-muted">Klik area ini untuk mengganti foto</p>
                            <div class="mt-2 flex justify-center">
                                <button type="button" class="btn btn-ghost btn-sm" @click.stop="openCamera()">
                                    <i data-lucide="camera" class="h-3.5 w-3.5"></i> Ambil Foto Lagi
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                @error('receipt')
                    <p class="mt-2 text-[11px] font-semibold text-danger">{{ $message }}</p>
                @enderror

                <template x-if="error">
                    <p class="mt-2 text-[11px] font-semibold text-danger" x-text="error"></p>
                </template>

                <button type="submit" class="btn btn-primary mt-3 w-full justify-center py-3" :disabled="scanning">
                    <template x-if="!scanning">
                        <span class="inline-flex items-center gap-2"><i data-lucide="scan-line" class="h-4 w-4"></i> Pindai dengan AI</span>
                    </template>
                    <template x-if="scanning">
                        <span class="inline-flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span> AI sedang membaca struk… <span class="tabular-nums" x-text="elapsed + 's'"></span></span>
                    </template>
                </button>

                @if (! $scannerConfigured)
                    <p class="mt-2 text-center text-[10px] leading-relaxed text-danger">
                        GEMINI_API_KEY belum diisi di file <span class="font-semibold">.env</span>, jadi scan AI tidak aktif. Pakai input manual di bawah, atau isi key lalu muat ulang halaman ini.
                    </p>
                @else
                    <p class="mt-2 text-center text-[10px] leading-relaxed text-muted">
                        Pilih atau foto struk, itemnya langsung dibaca AI — biasanya hanya butuh beberapa detik.
                    </p>
                @endif

                <template x-if="cameraOpen">
                    <div class="fixed inset-0 z-[85] flex items-center justify-center bg-ink/70 p-4">
                        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white">
                            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                                <p class="text-[13px] font-extrabold text-ink">Arahkan kamera ke struk</p>
                                <button type="button" class="icon-btn" @click="closeCamera()" aria-label="Tutup">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                            </div>
                            <div class="bg-black">
                                <video x-ref="cameraVideo" autoplay playsinline muted class="mx-auto max-h-[60vh] w-full object-contain"></video>
                            </div>
                            <div class="flex items-center gap-2 p-3">
                                <button type="button" class="btn btn-ghost flex-1 justify-center" @click="closeCamera()">Batal</button>
                                <button type="button" class="btn btn-primary flex-1 justify-center" @click="capturePhoto()">
                                    <i data-lucide="camera" class="h-4 w-4"></i> Ambil Foto
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </form>

            <div class="mt-4 grid gap-2 border-t border-line pt-4 sm:grid-cols-2">
                <form method="POST" action="{{ route('split-bills.store') }}">
                    @csrf
                    <input type="hidden" name="mode" value="manual">
                    <button type="submit" class="btn btn-ghost w-full justify-center">
                        <i data-lucide="pen-line" class="h-4 w-4"></i> Input Manual
                    </button>
                </form>
                <a href="{{ route('history.index') }}" class="btn btn-ghost w-full justify-center">
                    <i data-lucide="history" class="h-4 w-4"></i> Lihat Riwayat
                </a>
            </div>
        </section>

        <div class="space-y-4">
            <section class="card p-4 sm:p-5">
                <h2 class="text-[15px] font-extrabold tracking-tight text-ink">Tips Foto Struk</h2>
                <div class="mt-3 space-y-2.5">
                    @foreach ([
                        ['sun', 'Cahaya Cukup', 'Hindari bayangan dan pantulan lampu di kertas struk.'],
                        ['scan', 'Seluruh Struk Terlihat', 'Jangan terpotong di bagian total dan nama merchant.'],
                        ['focus', 'Fokus & Tidak Blur', 'Tahan kamera sebentar sampai teks terlihat tajam.'],
                        ['file-text', 'Gunakan Struk Asli', 'Struk thermal yang pudar lebih sulit dibaca AI.'],
                    ] as [$icon, $title, $desc])
                        <div class="flex gap-2.5 rounded-xl bg-[#f8faf9] p-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-primary-dark shadow-sm">
                                <i data-lucide="{{ $icon }}" class="h-3.5 w-3.5"></i>
                            </span>
                            <div>
                                <p class="text-[11px] font-bold text-ink">{{ $title }}</p>
                                <p class="mt-0.5 text-[10px] leading-relaxed text-muted">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card bg-gradient-to-br from-light/70 to-white p-4 sm:p-5">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-primary shadow-sm"><i data-lucide="shield-check" class="h-4 w-4"></i></span>
                    <div>
                        <p class="text-[12px] font-extrabold text-ink">Datamu aman</p>
                        <p class="text-[10px] text-muted">Foto hanya diproses untuk membaca struk.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
