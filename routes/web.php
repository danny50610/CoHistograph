<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

Route::get('overview', [HomeController::class, 'overview'])->name('overview');

Route::view('faq', 'footer-page.faq')->name('faq');

Route::prefix('graph-schema')->name('graph-schema.')->scopeBindings()->group(function () {
    Route::resource('vertex-type', \App\Http\Controllers\GraphSchema\VertexTypeController::class);
    Route::resource('vertex-type/{vertex_type}/vertex-property', \App\Http\Controllers\GraphSchema\VertexPropertyController::class)
        ->except(['index']);
    Route::resource('edge-type', \App\Http\Controllers\GraphSchema\EdgeTypeController::class);
    Route::resource('edge-type/{edge_type}/edge-property', \App\Http\Controllers\GraphSchema\EdgePropertyController::class)
        ->except(['index']);
});

Route::prefix('graph')->name('graph.')->group(function () {
    Route::resource('vertex', \App\Http\Controllers\Graph\VertexController::class)
        ->only(['index', 'show']);
});

Route::auth();

Route::group(['middleware' => ['auth']], function () {
    Route::resource('user', UserController::class)->except(['create', 'store']);
    Route::resource('role', RoleController::class)->except(['show']);
});
