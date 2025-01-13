<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/docs/exceptions', 'exceptions')->name('rabbit.docs.exceptions');
Route::get('/docs/exceptions/{code}', static fn($code) => view("exceptions.code", compact('code')))->name('rabbit.docs.exceptions.code');
