<?php

use App\Http\Controllers\GraphSchema\EdgePropertyController;
use App\Http\Controllers\GraphSchema\EdgeTypeController;
use App\Http\Controllers\GraphSchema\VertexPropertyController;
use App\Http\Controllers\GraphSchema\VertexTypeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

Route::get('overview', [HomeController::class, 'overview'])->name('overview');

Route::view('faq', 'footer-page.faq')->name('faq');

Route::auth();

Route::group(['middleware' => ['auth']], function () {
    Route::resource('user', UserController::class)->except(['create', 'store']);
    Route::resource('role', RoleController::class)->except(['show']);

    Route::prefix('graph-schema')->name('graph-schema.')->group(function () {
        Route::resource('vertex-type', VertexTypeController::class);
        Route::resource('vertex-type/{vertex_type}/vertex-property', VertexPropertyController::class);
        Route::resource('edge-type', EdgeTypeController::class);
        Route::resource('edge-type/{edge_type}/edge-property', EdgePropertyController::class);
    });
});
