<?php

use App\Http\Controllers\Academic\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::redirect('/', '/acesso');

Route::get('/acesso', function () {
    if (Auth::check()) {
        return redirect()->route(
            Auth::user()?->role === 'attendant'
                ? 'academic.attendant.dashboard'
                : 'academic.student.dashboard',
        );
    }

    return view('academic.auth');
})->middleware('guest')->name('academic.auth');

Route::prefix('aluno')->name('academic.student.')->middleware(['auth', 'role:student'])->group(function () {
    Route::view('/inicio', 'academic.student-dashboard')->name('dashboard');
    Route::view('/agendar', 'academic.student-new')->name('new');
    Route::view('/agendamentos', 'academic.student-appointments')->name('mine');
    Route::view('/perfil', 'academic.student-profile')->name('profile');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
});

Route::view('/atendente/agenda', 'academic.attendant-dashboard')
    ->middleware(['auth', 'role:attendant'])
    ->name('academic.attendant.dashboard');

Route::view('/atendente/perfil', 'academic.attendant-profile')
    ->middleware(['auth', 'role:attendant'])
    ->name('academic.attendant.profile');

Route::patch('/atendente/perfil', [ProfileController::class, 'update'])
    ->middleware(['auth', 'role:attendant'])
    ->name('academic.attendant.profile.update');

Route::patch('/atendente/perfil/senha', [ProfileController::class, 'updatePassword'])
    ->middleware(['auth', 'role:attendant'])
    ->name('academic.attendant.profile.password');

Route::post('/atendente/perfil/foto', [ProfileController::class, 'updatePhoto'])
    ->middleware(['auth', 'role:attendant'])
    ->name('academic.attendant.profile.photo');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return redirect()->route(
            Auth::user()?->role === 'attendant'
                ? 'academic.attendant.dashboard'
                : 'academic.student.dashboard',
        );
    })->name('dashboard');
});

require __DIR__.'/settings.php';
