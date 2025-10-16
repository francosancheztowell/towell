<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>PAPELETAS VIAJERAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>

<body class="bg-white impresion-UE">
    @for ($i = 0; $i < $orden->no_telas; $i++)
        @php
            $item = $ordEngomado[$i];
            $ord = $orden;
        @endphp
        <div class="border border-black p-5">
            <div class="flex justify-between items-center mb-1">
                <div>
                    <img src="{{ asset('images/fondosTowell/logo_towell2.png') }}" alt="Logo Towell" style="width: 2cm;">
                </div>
                <p class="font-bold text-lg text-sm">PAPELETA VIAJERA DE TELA ENGOMADA</p>
                <div class="text-right mr-2">
                    <p class="text-sm">No. FOLIO: <span class="font-bold text-red-600">{{ $folio }}</span>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-5 mb-2 text-left">
                <div>
                    <p class="mt-2"><strong>ENGOMADO:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $orden->maquinaEngomado ?? '' }}
                        </span>
                    </p>
                    <p class="mt-2"><strong>URDIDO:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $orden->urdido ?? '' }}
                        </span>
                    </p>
                    <p class="mt-2"><strong>ANCHO BALONAS:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $orden->balonas ?? '' }}
                        </span>

                </div>
                <div>
                    <p class="mt-2"><strong>FECHA:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ \Carbon\Carbon::parse($item->fecha)->format('d-m-y') }}
                        </span>
                    </p>
                    <p class="mt-2"><strong>URDIDOR:</strong>
                        <span
                            class="inline-block text-xs border-b-2 border-black print:border-black whitespace-nowrap overflow-hidden text-ellipsis max-w-[110px] align-middle">
                            {{ Auth::user()->nombre ?? '' }}
                        </span>

                    </p>
                    <p class="mt-2"><strong>CAL.:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                        </span>
                </div>
                <div class="ml-auto">
                    <p class="mt-2"><strong>TURNO:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $item->turno ?? '' }}
                        </span>
                    </p>
                    <p class="mt-2"><strong>CUENTA:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $orden->cuenta ?? '' }}
                        </span>
                    </p>
                    <p class="mt-2"><strong>PROVEEDOR:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $orden->proveedor ?? '' }}
                        </span>
                    </p>
                </div>
                <div class="ml-auto">
                    <p class="mt-2"><strong>ORDEN:</strong>
                        <span
                            class="inline-block text-xs border-b-2 border-black px-2 print:border-black">{{ $folio }}
                        </span>
                    </p>

                    <p class="mt-2"><strong>SÓLIDOS:</strong><span class="text-xs">
                            <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                                {{ $orden->solidos ?? '' }}
                            </span>
                    </p>

                </div>
                <div class="ml-auto">
                    <p class="mt-2"><strong>PAREJA:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                        </span>
                    </p>
                    <p class="mt-2"><strong>TIPO:</strong><span class="text-xs">
                            <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                                {{ $orden->tipo ?? '' }}
                            </span>
                    </p> </span>
                    <p class="mt-2"><strong>COLOR:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ $orden->color ?? '' }}
                        </span>
                </div>
            </div>
            <!-- Tabla principal -->
            <div class="w-full overflow-x-auto">
                <table border="1"
                    style="border-collapse: collapse; margin-bottom: 0.5rem; text-align: center; width: 100%;">
                    <thead style="line-height: 1;">
                        <tr>
                            <th colspan="6"></th>
                        </tr>
                        <tr style="background-color: #e5e7eb;">
                            <th style="padding: 1px; width: 4cm; border: 1px solid black; border: 1px solid black;">
                                FECHA</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">H. INIC.</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">H. FINAL</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">METROS</th>
                            <th style="padding: 1px; width: 1.5cm; border: 1px solid black;">ROTURAS</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">ENGOMADOR</th>
                            <th style="padding: 1px; width: 5cm;   border: 1px solid black;">OBSERVACIONES</th>
                        </tr>
                    </thead>
                    <tbody style="line-height: 1;" class="text-xs">
                        <tr>
                            <td style="padding: 3px; border: 1px solid black;">
                                {{ \Carbon\Carbon::parse($item->fecha)->format('d-m-y') }}</td>
                            <td style="padding: 3px; border: 1px solid black;">
                                {{ \Carbon\Carbon::parse($item->hora_inicio)->format('h:i A') }}</td>
                            <td style="padding: 3px; border: 1px solid black;">
                                {{ \Carbon\Carbon::parse($item->hora_fin)->format('h:i A') }}</td>
                            <td style="padding: 3px; width: 3.5cm; border: 1px solid black;">{{ $item->metros }}</td>
                            <td style="border: 1px solid black; width: 3.5cm;">
                                <div style="display: flex; width: 100%; height: 100%;">
                                    <div style="width: 100%; padding: 4px; text-align: center;">{{ $item->roturas }}
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1px; width: 2.5cm; border: 1px solid black;" class="text-xss">
                                {{ $item->oficial }}</td>
                            <td style="padding: 3px; width: 2.5cm; border: 1px solid black;">
                                {{ $ord->observaciones ?? '' }}
                            </td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <!-- Tabla secundaria -->
            <div class="w-full overflow-x-auto mb-2">
                <table border="1"
                    style="border-collapse: collapse; margin-bottom: 0.25rem; text-align: center; width: 100%;">
                    <thead style="line-height: 1;">
                        <tr>
                            <th colspan="9"></th>
                        </tr>
                        <tr style="background-color: #e5e7eb;">
                            <th style="padding: 1px; width: 3cm;  border: 1px solid black;">N° JULIO</th>
                            <th style="padding: 1px; width: 2cm; border: 1px solid black;">KG. BRUTO</th>
                            <th style="padding: 1px; width: 2cm; border: 1px solid black;">TARA</th>
                            <th style="padding: 1px; width: 2cm; border: 1px solid black;">KG. NETO</th>
                            <th style="padding: 1px; width: 3cm; border: 1px solid black;">SOL. CAN.</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">TEMP. CANOA 1</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">TEMP. CANOA 2</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">TEMP. TAMB.</th>
                            <th style="padding: 1px; width: 2.5cm; border: 1px solid black;">HUMEDAD</th>
                        </tr>
                    </thead>
                    <tbody style="line-height: 1;" class="text-xs">
                        <tr>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->no_julio }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->peso_bruto }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->tara }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->peso_neto }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->turno }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->temp_canoa_1 }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->temp_canoa_2 }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->temp_canoa_3 }}</td>
                            <td style="padding: 3px; border: 1px solid black;">{{ $item->humedad }}</td>
                        </tr>
                    </tbody>

                </table>
            </div>
            <div class="grid grid-cols-4 gap-4 mb-0.5 text-left">
                <div>
                    <p><strong>FECHA DE ATADO:</strong> _____________________________</p>
                </div>
                <div>
                    @php
                        $items = $telares; // true para array asociativo
                    @endphp
                    <p class="text-xs"><strong>TELAR:</strong>
                        <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                            {{ implode(', ', $telares->toArray()) }}
                        </span>
                    </p>
                </div>
                <div>
                    <p><strong>TURNO:</strong> _____________________________</p>
                </div>
                <div>
                    <table class="table-fixed border border-black text-xss ml-20">
                        <tbody>
                            <tr>
                                <td class="border border-black text-center px-4"><strong>CLAVE ATADOR</strong></td>
                            </tr>
                            <tr>
                                <td class="border border-black py-4 text-center text-sm">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4 -mt-6 ">
                <div class="mt-5">
                    <p><strong>DESTINO:<span class="text-xs">
                                <span class="inline-block text-xs border-b-2 border-black px-2 print:border-black">
                                    {{ $orden->destino ?? '' }}
                                </span>
                        </strong></p>
                </div>
                <div>
                    <table class="ml-auto border border-black text-xss text-right ">
                        <tbody>
                            <tr>
                                <td class="border border-black px-7 text-center"><strong>H. PARO</strong></td>
                                <td class="border border-black px-7 text-center"><strong>H. INICIO</strong></td>
                                <td class="border border-black px-7 text-center"><strong>H. FINAL</strong></td>
                            </tr>
                            <tr>
                                <td class="border border-black p-4 text-center"></td>
                                <td class="border border-black p-4 text-center"></td>
                                <td class="border border-black p-4 text-center"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <p class="mt-5 text-right"><strong>MERMA:</strong> _____________________________</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mt-4 text-left">
                <div>
                    <p><strong>FIRMA DEL SUPERVISOR:</strong> _____________________________</p>
                </div>
                <div>
                    <p><strong>OBSERVACIONES:</strong> _____________________________</p>
                </div>
                <div>
                    <p><strong>BAJADO POR:</strong> _____________________________</p>
                </div>
            </div>
        </div>
    @endfor
    <!--SECCIÓN DE SCRIPS JS-->

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const folio = @json($folio);
            JsBarcode("#barcode", folio, {
                format: "CODE128",
                lineColor: "black",
                width: 1,
                height: 30,
                displayValue: false
            });
        });
    </script>
</body>

</html>
