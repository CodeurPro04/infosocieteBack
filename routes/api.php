<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

Route::get('/content', [ContentController::class, 'show']);
Route::get('/pages/{slug}', [ContentController::class, 'page']);
Route::post('/search', [ContentController::class, 'search']);

Route::post('/forms/contact', [FormController::class, 'contact']);
Route::post('/forms/cancel', [FormController::class, 'cancel']);
Route::post('/forms/claim', [FormController::class, 'claim']);

Route::post('/admin/login', [AdminController::class, 'login']);
Route::middleware('admin.token')->group(function () {
    Route::put('/admin/content', [AdminController::class, 'updateContent']);
    Route::get('/admin/submissions', [AdminController::class, 'submissions']);
});
