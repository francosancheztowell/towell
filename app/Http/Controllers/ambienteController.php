<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ambienteController extends Controller
{
    //
    public function index(){
        return view('ambiente');
    }

    // Todo: autenticar al usuario, en el area de sistemas
    
}
