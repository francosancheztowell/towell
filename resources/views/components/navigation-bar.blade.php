@props([
    'showBack' => true,
    'showForward' => true,
    'backLabel' => 'Atrás',
    'forwardLabel' => 'Adelante',
    'backAction' => 'history.back()',
    'forwardAction' => 'history.forward()',
    'customActions' => null,
    'variant' => 'default' // 'default', 'compact', 'full'
])

@if ($showBack || $showForward || $customActions)
    <div class="flex items-center gap-2">
        @if ($showBack)
            <button onclick="{{ $backAction }}"
                class="{{ $variant === 'compact' ? 'p-2' : 'flex items-center gap-2 px-3 py-2' }} text-gray-600 hover:text-blue-600 hover:bg-gray-100 rounded-lg transition-colors text-sm font-medium"
                title="{{ $backLabel }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                @if ($variant !== 'compact')
                    <span class="hidden sm:inline">{{ $backLabel }}</span>
                @endif
            </button>
        @endif

        @if ($showForward)
            <button onclick="{{ $forwardAction }}"
                class="{{ $variant === 'compact' ? 'p-2' : 'flex items-center gap-2 px-3 py-2' }} text-gray-600 hover:text-blue-600 hover:bg-gray-100 rounded-lg transition-colors text-sm font-medium"
                title="{{ $forwardLabel }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                @if ($variant !== 'compact')
                    <span class="hidden sm:inline">{{ $forwardLabel }}</span>
                @endif
            </button>
        @endif

        @if ($customActions)
            {{ $customActions }}
        @endif
    </div>
@endif











