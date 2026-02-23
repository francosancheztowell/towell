@extends('layouts.app')

@section('page-title', 'Obtener Chat ID de Telegram')

@section('content')
<div class="w-full px-4 py-6 max-w-4xl mx-auto">
    <x-layout.page-header
        title="Obtener Chat ID de Telegram"
        subtitle="Configura TELEGRAM_CHAT_ID en tu .env para recibir notificaciones"
        gradient="blue"
    />

    <div class="mt-6 space-y-6">
        {{-- Cómo buscar y escribir al bot en Telegram --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 text-white px-4 py-3 flex items-center gap-2">
                <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.53 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                <h2 class="text-lg font-semibold">Cómo enviar un mensaje a tu bot en Telegram</h2>
            </div>
            <div class="p-4 space-y-4">
                <ol class="list-decimal list-inside space-y-3 text-gray-700">
                    <li>
                    </li>
                    <li>
                        <strong>Busca tu bot</strong> por su nombre de usuario. En la barra de búsqueda escribe <code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">@NombreDeTuBot</code> (el mismo que te dio @BotFather al crear el bot). Por ejemplo: <code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">@MiBotDeNotificaciones_bot</code>.
                    </li>
                    <li>
                        <strong>Abre el chat</strong> del bot y pulsa <strong>Iniciar</strong> (o <em>Start</em>). Si ya lo iniciaste antes, solo abre la conversación.
                    </li>
                    <li>
                        <strong>Envía cualquier mensaje</strong> al bot: por ejemplo escribe <em>Hola</em> o <em>Hola bot</em> y envía. Con eso el bot ya “ve” tu chat y aquí podremos obtener tu Chat ID.
                    </li>
                </ol>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                    <p class="font-medium mb-1">¿No recuerdas el nombre de tu bot?</p>
                </div>
            </div>
        </div>

        {{-- Pasos --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-slate-800 text-white px-4 py-3">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-sm">1</span>
                    Pasos para obtener tu Chat ID
                </h2>
            </div>
            <ul class="divide-y divide-gray-100 p-4 space-y-0">
                @foreach($instructions ?? [] as $index => $step)
                    <li class="flex items-start gap-3 py-3 first:pt-0">
                        <span class="flex-shrink-0 flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-600 text-sm font-medium">{{ $index + 1 }}</span>
                        <span class="text-gray-700">{{ $step }}</span>
                    </li>
                @endforeach
                @if(empty($instructions))
                    <li class="flex items-start gap-3 py-3">
                        <span class="text-gray-500">Configura primero TELEGRAM_BOT_TOKEN en .env y envía un mensaje a tu bot.</span>
                    </li>
                @endif
            </ul>
        </div>

        {{-- Recargar --}}
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('telegram.get-chat-id') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Recargar Chat IDs
            </a>
            <a href="{{ route('telegram.bot-info') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                Ver información del bot
            </a>
        </div>

        {{-- Mensaje de estado --}}
        @if(!empty($message))
            <div class="rounded-xl border px-4 py-3 {{ $success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
                <p class="font-medium">{{ $message }}</p>
            </div>
        @endif

        {{-- Tabla de Chat IDs --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-slate-800 text-white px-4 py-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Chat IDs encontrados</h2>
                @if(!empty($chat_ids) && count($chat_ids) > 0)
                    <span class="text-sm text-slate-300">{{ count($chat_ids) }} chat(s)</span>
                @endif
            </div>
            <div class="overflow-x-auto">
                @if(!empty($chat_ids) && count($chat_ids) > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Chat ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($chat_ids as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-sm text-gray-900">{{ $item['chat_id'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $item['first_name'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ isset($item['username']) && $item['username'] ? '@' . $item['username'] : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @php $type = $item['type'] ?? 'private'; @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $type === 'private' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $type === 'private' ? 'Privado' : ucfirst($type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button"
                                            class="copy-chat-id inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            data-chat-id="{{ $item['chat_id'] }}"
                                            title="Copiar Chat ID">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            <span class="copy-label">Copiar</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="font-medium">Aún no hay chats</p>
                        <p class="text-sm mt-1">Envía un mensaje a tu bot en Telegram y luego pulsa «Recargar Chat IDs».</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Cómo usar en .env --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-slate-700 text-white px-4 py-3">
                <h2 class="text-lg font-semibold">Uso en .env</h2>
            </div>
            <div class="p-4 bg-slate-50 font-mono text-sm text-slate-800 rounded-b-xl">
                <p class="text-slate-600 mb-1">Añade o edita en tu archivo <code class="bg-white px-1 rounded">.env</code>:</p>
                <pre class="bg-slate-800 text-emerald-300 p-4 rounded-lg overflow-x-auto">TELEGRAM_CHAT_ID=tu_chat_id_aqui</pre>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.copy-chat-id').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var chatId = this.getAttribute('data-chat-id');
        if (!chatId) return;
        var label = this.querySelector('.copy-label');
        var originalText = label ? label.textContent : 'Copiar';
        function showCopied() {
            if (label) { label.textContent = '¡Copiado!'; }
            setTimeout(function() { if (label) { label.textContent = originalText; } }, 1500);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(chatId).then(showCopied);
        } else {
            var ta = document.createElement('textarea');
            ta.value = chatId;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showCopied();
        }
    });
});
</script>
@endpush
@endsection
