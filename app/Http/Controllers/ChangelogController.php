<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\Contracts\View\View;

class ChangelogController extends Controller
{
    public function __construct(private Changelog $changelog) {}

    public function index(): View
    {
        $releases = $this->changelog->releases();

        return view('footer-page.changelog', compact('releases'));
    }
}
