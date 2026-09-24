<?php

use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'index'])->name('home');

Route::any('{fallbackPlaceholder}', fn () => abort(404))->where('fallbackPlaceholder', '.*')->fallback();
