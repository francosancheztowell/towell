@props(['title' => '', 'icon' => null, 'subtitle' => null, 'badge' => null, 'color' => 'blue'])

<div class="flex min-w-0 items-center gap-3 animate-fade-in">
    <div class="flex min-w-0 flex-col">
        <div class="flex min-w-0 items-center gap-2">
            <h1 class="truncate text-lg md:text-xl lg:text-2xl font-bold text-blue-700 leading-tight">
                {{ $title }}
            </h1>
        </div>
    </div>
</div>

