<?php

namespace App\Http\Controllers;

class StorageController extends Controller
{
    public function usuarioFoto(string $filename)
    {
        $path = storage_path('app/public/usuarios/' . $filename);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }
}
