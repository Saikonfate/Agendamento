<?php

use App\Http\Controllers\Academic\ProfileController;
use App\Http\Controllers\Academic\AdminNoticeController;
use App\Http\Controllers\Academic\AdminDashboardController;
use App\Http\Controllers\Academic\AdminAppointmentController;
use App\Http\Controllers\Academic\AdminScheduleController;
use App\Http\Controllers\Academic\StudentAppointmentController;
use App\Http\Controllers\Academic\ProfilePageController;
use App\Http\Controllers\Academic\ProfessorAppointmentController;
use App\Http\Controllers\Academic\ProfessorDashboardController;
use App\Http\Controllers\Academic\UserProvisionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::redirect('/', '/acesso');

Route::get('/home', function () {
    if (Auth::check()) {
        return redirect()->route(
            match (Auth::user()?->role) {
                'admin' => 'academic.admin.dashboard',
                'professor' => 'academic.professor.dashboard',
                default => 'academic.student.dashboard',
            },
        );
    }

    return view('academic.auth');
})->name('home');

Route::get('/acesso', function () {
    if (Auth::check()) {
        return redirect()->route(
            match (Auth::user()?->role) {
                'admin' => 'academic.admin.dashboard',
                'professor' => 'academic.professor.dashboard',
                default => 'academic.student.dashboard',
            },
        );
    }

    return view('academic.auth');
})->middleware('guest')->name('academic.auth');

Route::prefix('aluno')->name('academic.student.')->middleware(['auth', 'role:student'])->group(function () {
    Route::get('/inicio', [StudentAppointmentController::class, 'dashboard'])->name('dashboard');
    Route::get('/agendar', [StudentAppointmentController::class, 'create'])->name('new');
    Route::post('/agendar', [StudentAppointmentController::class, 'store'])->name('store');
    Route::get('/agendamentos', [StudentAppointmentController::class, 'index'])->name('mine');
    Route::patch('/agendamentos/{appointment}/cancelar', [StudentAppointmentController::class, 'cancel'])->name('cancel');
    Route::get('/perfil', [ProfilePageController::class, 'student'])->name('profile');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
});

Route::get('/admin/agenda', AdminDashboardController::class)
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.dashboard');

Route::get('/admin/horarios', [AdminScheduleController::class, 'index'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.schedule');

Route::post('/admin/horarios/bloqueios', [AdminScheduleController::class, 'storeBlockedDate'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.schedule.blocked-dates.store');

Route::post('/admin/horarios/configuracoes', [AdminScheduleController::class, 'storeAttendantSchedule'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.schedule.settings.store');

Route::delete('/admin/horarios/bloqueios/{blockedDate}', [AdminScheduleController::class, 'destroyBlockedDate'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.schedule.blocked-dates.destroy');

Route::view('/admin/cadastros', 'academic.admin-users')
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.users');

Route::post('/admin/alunos', [UserProvisionController::class, 'storeStudent'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.students.store');

Route::post('/admin/professores', [UserProvisionController::class, 'storeProfessor'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.professors.store');

Route::get('/admin/avisos', [AdminNoticeController::class, 'index'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.notices.index');

Route::post('/admin/avisos', [AdminNoticeController::class, 'store'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.notices.store');

Route::patch('/admin/avisos/{notice}', [AdminNoticeController::class, 'update'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.notices.update');

Route::delete('/admin/avisos/{notice}', [AdminNoticeController::class, 'destroy'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.notices.destroy');

Route::patch('/admin/agendamentos/{appointment}/status', [AdminAppointmentController::class, 'updateStatus'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.appointments.status');

Route::patch('/admin/agendamentos/{appointment}/reagendar', [AdminAppointmentController::class, 'reschedule'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.appointments.reschedule');

Route::get('/admin/perfil', [ProfilePageController::class, 'admin'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.profile');

Route::patch('/admin/perfil', [ProfileController::class, 'update'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.profile.update');

Route::patch('/admin/perfil/senha', [ProfileController::class, 'updatePassword'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.profile.password');

Route::post('/admin/perfil/foto', [ProfileController::class, 'updatePhoto'])
    ->middleware(['auth', 'role:admin'])
    ->name('academic.admin.profile.photo');

Route::prefix('professor')->name('academic.professor.')->middleware(['auth', 'role:professor'])->group(function () {
    Route::get('/inicio', ProfessorDashboardController::class)->name('dashboard');
    Route::post('/disponibilidade', [ProfessorDashboardController::class, 'storeAvailability'])->name('availability.store');
    Route::patch('/agendamentos/{appointment}/status', [ProfessorAppointmentController::class, 'updateStatus'])->name('appointments.status');
    Route::get('/perfil', [ProfilePageController::class, 'professor'])->name('profile');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return redirect()->route(
            match (Auth::user()?->role) {
                'admin' => 'academic.admin.dashboard',
                'professor' => 'academic.professor.dashboard',
                default => 'academic.student.dashboard',
            },
        );
    })->name('dashboard');
});

require __DIR__.'/settings.php';
