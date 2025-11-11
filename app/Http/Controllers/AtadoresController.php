<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AtadoresController extends Controller
{
    //
    public function index(){
        return view("atadores.index");
    }
}
