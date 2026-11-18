<?php

namespace App\Http\Controllers\Yayasan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilController extends Controller
{
    public function index(Request $request): View
    {
        return view('yayasan.profil', [
            'pengguna' => $request->user(),
        ]);
    }
}
