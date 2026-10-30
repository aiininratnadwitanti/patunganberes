<div x-data x-cloak x-show="$store.dialog.open" class="fixed inset-0 z-[80] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" @click="$store.dialog.close()"></div>

    <div class="relative w-full max-w-md rounded-3xl border border-line bg-white p-5 shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0">
        <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger">
                <i data-lucide="alert-triangle" class="h-5 w-5"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="text-[16px] font-extrabold text-ink" x-text="$store.dialog.title"></h3>
                <p class="mt-1 text-[12px] leading-relaxed text-muted" x-text="$store.dialog.message"></p>
            </div>
        </div>

        <form method="POST" :action="$store.dialog.url" class="mt-5 flex gap-2" @submit="$store.dialog.open = false">
            @csrf
            <input type="hidden" name="_method" :value="$store.dialog.method">
            <button type="button" class="btn btn-ghost flex-1 justify-center" @click="$store.dialog.close()">Batal</button>
            <button type="submit" class="btn btn-danger flex-1 justify-center" x-text="$store.dialog.confirmLabel"></button>
        </form>
    </div>
</div>
