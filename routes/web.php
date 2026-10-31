<?php

use App\Http\Controllers\OgImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/og/{type}/{key}.jpg', OgImageController::class)
    ->where(['type' => 'book|author|series|dars|site', 'key' => '[A-Za-z0-9_-]+'])
    ->middleware('throttle:120,1')
    ->name('og-image');
