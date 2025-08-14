<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

Route::view('faq', 'footer-page.faq')->name('faq');
