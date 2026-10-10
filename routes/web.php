<?php

use App\Http\Controllers\DownloadLaporanController;
use Illuminate\Support\Facades\Route;

Route::get('/app/{usaha:slug}/laporan/download', DownloadLaporanController::class)
    ->middleware('auth')
    ->name('laporan.download');

Route::get('/', function () {
    return view('welcome');
});
