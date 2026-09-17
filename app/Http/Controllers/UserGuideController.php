<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class UserGuideController extends Controller
{
    public function show(): View
    {
        return view('guide');
    }
}
