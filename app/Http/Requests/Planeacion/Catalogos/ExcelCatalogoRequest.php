<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

/** Archivo de carga masiva de Telares, Eficiencia, Velocidad, Aplicaciones y Calendarios. */
class ExcelCatalogoRequest extends CatalogoRequest
{
    protected int $statusValidacion = 400;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['archivo_excel.*' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx o .xls) de máximo 10MB.'];
    }
}
