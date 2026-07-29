<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use Illuminate\Contracts\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $faqItems = FaqItem::query()->ordered()->get();

        return view('footer-page.faq', compact('faqItems'));
    }
}
