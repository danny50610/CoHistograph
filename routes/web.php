<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

Route::get('overview', [HomeController::class, 'overview'])->name('overview');

Route::view('faq', 'footer-page.faq')->name('faq');

Route::auth();
