@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $pageTitle)

@section('content')
<div class="w-full pt-page">
  <div class="bg-white w-full pt-page-card p-4">

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Orden / Id</label>
        <input type="text" name="orden" value="{{ $filtros['orden'] }}" placeholder="TW-12345 o Id=603"
               class="border border-gray-300 rounded px-2 py-1 text-sm w-48">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Usuario</label>
        <input type="text" name="usuario" value="{{ $filtros['usuario'] }}"
               class="border border-gray-300 rounded px-2 py-1 text-sm w-40">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Acción</label>
        <select name="accion" class="border border-gray-300 rounded px-2 py-1 text-sm">
          <option value="">Todas</option>
          @foreach (['INSERT', 'UPDATE', 'DELETE'] as $accion)
            <option value="{{ $accion }}" @selected($filtros['accion'] === $accion)>{{ $accion }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
        <input type="date" name="desde" value="{{ $filtros['desde'] }}"
               class="border border-gray-300 rounded px-2 py-1 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
        <input type="date" name="hasta" value="{{ $filtros['hasta'] }}"
               class="border border-gray-300 rounded px-2 py-1 text-sm">
      </div>
      <button type="submit"
              class="px-3 py-1.5 rounded bg-blue-500 text-white text-sm hover:bg-blue-600">
        <i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar
      </button>
      <a href="{{ route('programa-tejido.auditoria') }}"
         class="px-3 py-1.5 rounded bg-gray-200 text-gray-700 text-sm hover:bg-gray-300">Limpiar</a>
    </form>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-gray-700">
          <tr>
            <th class="px-3 py-2 text-left font-semibold">Fecha</th>
            <th class="px-3 py-2 text-left font-semibold">Usuario</th>
            <th class="px-3 py-2 text-left font-semibold">Acción</th>
            <th class="px-3 py-2 text-left font-semibold">Contexto</th>
            <th class="px-3 py-2 text-left font-semibold">Orden</th>
            <th class="px-3 py-2 text-left font-semibold">Detalle</th>
            <th class="px-3 py-2 text-left font-semibold">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse ($registros as $r)
            @php
              // Detalle viene como 'CONTEXTO | campo: antes -> despues'
              [$contexto, $detalle] = array_pad(explode(' | ', (string) $r->Detalle, 2), 2, '');
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 whitespace-nowrap">{{ $r->Fecha }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $r->Usuario }}</td>
              <td class="px-3 py-2">
                <span @class([
                  'px-2 py-0.5 rounded text-xs font-medium',
                  'bg-green-100 text-green-800' => $r->Accion === 'INSERT',
                  'bg-amber-100 text-amber-800' => $r->Accion === 'UPDATE',
                  'bg-red-100 text-red-800' => $r->Accion === 'DELETE',
                ])>{{ $r->Accion }}</span>
              </td>
              <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-700">{{ $contexto }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $r->PK }}</td>
              <td class="px-3 py-2">{{ $detalle }}</td>
              <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $r->IP }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-3 py-8 text-center text-gray-500">Sin movimientos con esos filtros.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">{{ $registros->links() }}</div>
  </div>
</div>
@endsection
