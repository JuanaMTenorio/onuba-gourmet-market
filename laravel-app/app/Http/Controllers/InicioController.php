<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InicioController extends Controller
{
    public function index()
    {
        $categorias = DB::table('categorias')->get();

        return view('inicio', compact('categorias'));
    }
}
