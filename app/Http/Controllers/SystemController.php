<?php

namespace App\Http\Controllers;

class SystemController extends Controller
{
    public function test404()
    {
        abort(404);
    }
}
