<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Root redirect to Admin Dashboard
Route::get('/', function () {
    return redirect('/admin');
});

// Fallback login route for web requests - redirects to Filament Admin login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');
