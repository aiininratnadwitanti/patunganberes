@if (session('toast'))
    <div x-data x-init="$store.toasts.push(@js(session('toast.type')), @js(session('toast.message')))"></div>
@endif

<div x-data class="pointer-events-none fixed inset-x-0 top-4 z-[90] flex flex-col items-center gap-2 px-4 sm:left-auto sm:right-6 sm:items-end">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white px-4 py-3 shadow-lg"
             :class="{
                'border-primary/30': toast.type === 'success',
                'border-warning/40': toast.type === 'warning',
                'border-danger/30': toast.type === 'error',
                'border-line': toast.type === 'info'
             }"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-[-8px]"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl"
                  :class="{
                    'bg-light text-primary': toast.type === 'success',
                    'bg-warning/10 text-warning': toast.type === 'warning',
                    'bg-danger/10 text-danger': toast.type === 'error',
                    'bg-slate-100 text-slate-600': toast.type === 'info'
                  }">
                <i data-lucide="check-circle-2" class="h-4 w-4" x-show="toast.type === 'success'"></i>
                <i data-lucide="alert-triangle" class="h-4 w-4" x-show="toast.type === 'warning'"></i>
                <i data-lucide="x-circle" class="h-4 w-4" x-show="toast.type === 'error'"></i>
                <i data-lucide="info" class="h-4 w-4" x-show="toast.type === 'info'"></i>
            </span>
            <p class="flex-1 text-[12px] font-semibold leading-relaxed text-ink" x-text="toast.message"></p>
            <button type="button" class="text-muted hover:text-ink" @click="$store.toasts.remove(toast.id)" aria-label="Tutup notifikasi">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
    </template>
</div>
