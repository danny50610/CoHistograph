<?php

use App\Http\Controllers\Admin\RevisionReviewController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RevisionController;
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

    Route::get('visualization', [\App\Http\Controllers\GraphSchema\VisualizationController::class, 'index'])->name('visualization');
});

Route::prefix('graph')->name('graph.')->group(function () {
    Route::resource('vertex', \App\Http\Controllers\Graph\VertexController::class)
        ->only(['index', 'show']);
});

Route::auth();

Route::group(['middleware' => ['auth']], function () {
    Route::get('revisions', [RevisionController::class, 'index'])->name('revisions.index');
    Route::get('revisions/create', [RevisionController::class, 'create'])->name('revisions.create');
    Route::post('revisions', [RevisionController::class, 'store'])->name('revisions.store');
    Route::get('revisions/{revision}', [RevisionController::class, 'show'])->name('revisions.show');
    Route::get('revisions/{revision}/edit', [RevisionController::class, 'edit'])->name('revisions.edit');
    Route::post('revisions/{revision}/validate', [RevisionController::class, 'validateDraft'])->name('revisions.validate');
    Route::put('revisions/{revision}', [RevisionController::class, 'update'])->name('revisions.update');
    Route::post('revisions/{revision}/submit', [RevisionController::class, 'submit'])->name('revisions.submit');
    Route::post('revisions/{revision}/reopen', [RevisionController::class, 'reopen'])->name('revisions.reopen');
    Route::delete('revisions/{revision}', [RevisionController::class, 'destroy'])->name('revisions.destroy');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('revisions', [RevisionReviewController::class, 'index'])->name('revisions.index');
        Route::get('revisions/{revision}', [RevisionReviewController::class, 'show'])->name('revisions.show');
        Route::post('revisions/{revision}/approve', [RevisionReviewController::class, 'approve'])->name('revisions.approve');
        Route::post('revisions/{revision}/reject', [RevisionReviewController::class, 'reject'])->name('revisions.reject');
    });

    Route::resource('user', UserController::class)->except(['create', 'store']);
    Route::resource('role', RoleController::class)->except(['show']);
});
