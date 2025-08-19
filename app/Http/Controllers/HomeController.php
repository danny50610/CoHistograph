<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public const AUTHENTICATED_REDIRECT = '/overview';

    public function index()
    {
        return view('index');
    }

    public function overview()
    {
        return view('overview');
    }
}
