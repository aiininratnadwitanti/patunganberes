@php
    $steps = [
        1 => ['label' => 'Detail Struk', 'route' => 'split-bills.validate'],
        2 => ['label' => 'Peserta', 'route' => 'split-bills.participants'],
        3 => ['label' => 'Pembagian', 'route' => 'split-bills.allocation'],
        4 => ['label' => 'Ringkasan', 'route' => 'split-bills.summary'],
    ];
    $current = $current ?? 1;
@endphp

<div class="card mb-4 overflow-hidden p-3 sm:p-4">
    <div class="flex items-center gap-1.5 sm:gap-3">
        @foreach ($steps as $number => $step)
            @php
                $isDone = $number < $current;
                $isCurrent = $number === $current;
                $isClickable = $isDone && in_array($step['route'], ['split-bills.validate', 'split-bills.participants']) && ($bill->status ?? 'draft') === 'draft';
            @endphp

            @if ($isClickable)
                <a href="{{ route($step['route'], $bill) }}" class="step-pill {{ $isDone ? 'is-done' : '' }} {{ $isCurrent ? 'is-current' : '' }} group">
            @else
                <div class="step-pill {{ $isDone ? 'is-done' : '' }} {{ $isCurrent ? 'is-current' : '' }}">
            @endif
                <span class="step-dot">
                    @if ($isDone)
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="hidden sm:block">{{ $step['label'] }}</span>
            @if ($isClickable)
                </a>
            @else
                </div>
            @endif

            @if (! $loop->last)
                <span class="h-px flex-1 bg-line {{ $number < $current ? 'bg-primary/40' : '' }}"></span>
            @endif
        @endforeach
    </div>
</div>
