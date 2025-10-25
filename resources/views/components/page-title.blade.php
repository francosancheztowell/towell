@props(['title' => '', 'icon' => null, 'subtitle' => null, 'badge' => null, 'color' => 'blue'])

<div class="flex items-center gap-3 animate-fade-in">
    <div class="flex flex-col">
        <div class="flex items-center gap-2">
            <h1 class="text-lg md:text-xl lg:text-2xl font-bold text-blue-700 leading-tight">
                {{ $title }}
            </h1>
        </div>
    </div>
</div>

