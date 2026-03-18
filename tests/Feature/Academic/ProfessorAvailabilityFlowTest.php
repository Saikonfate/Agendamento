<?php

use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Models\BlockedDate;
use App\Models\User;
use Illuminate\Support\Carbon;

function weekdaySettings(array $enabledDays, string $start = '10:00', string $end = '11:00'): array
{
    $settings = [];

    foreach (['mon', 'tue', 'wed', 'thu', 'fri'] as $day) {
        $enabled = in_array($day, $enabledDays, true);

        $settings[$day] = [
            'enabled' => $enabled,
            'start_time' => $start,
            'end_time' => $end,
            'break_start' => '',
            'break_end' => '',
        ];
    }

    return $settings;
}

test('professor can save per-day availability', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create([
        'role' => 'professor',
        'name' => 'Ana Lima',
        'email' => 'ana.lima@unifap.edu.br',
    ]);

    $response = $this->actingAs($professor)->post(route('academic.professor.availability.store'), [
        'slot_duration_minutes' => 30,
        'day_settings' => weekdaySettings(['mon', 'wed']),
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $schedule = AttendantSchedule::query()->where('attendant_name', 'Prof. Ana Lima')->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->working_days)->toBe(['mon', 'wed']);
    expect($schedule->day_settings['mon']['enabled'])->toBeTrue();
    expect($schedule->day_settings['tue']['enabled'])->toBeFalse();
});

test('student scheduling respects professor per-day availability', function () {
    /** @var \Tests\TestCase $this */
    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990001',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday')->format('Y-m-d');

    $invalidResponse = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Orientação TCC',
        'date' => $monday,
        'time' => '08:00',
    ]);

    $invalidResponse->assertSessionHasErrors('time');

    $validResponse = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Orientação TCC',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $validResponse
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('academic.student.mine'));

    $this->assertDatabaseHas('appointments', [
        'student_registration' => '20249990001',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Orientação TCC',
        'status' => 'Pendente',
    ]);
});

test('admin reschedule validates professor per-day rules', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.teste@unifap.edu.br',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Aluno Teste',
        'student_registration' => '20249990002',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Revisão',
        'scheduled_at' => Carbon::now()->next('Monday')->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    $tuesday = Carbon::now()->next('Tuesday')->format('Y-m-d');

    $invalid = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.reschedule', $appointment), [
        'date' => $tuesday,
        'time' => '10:00',
        'reschedule_reason' => 'Ajuste de disponibilidade do professor.',
    ]);

    $invalid->assertStatus(422);

    $monday = Carbon::now()->next('Monday')->format('Y-m-d');

    $valid = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.reschedule', $appointment), [
        'date' => $monday,
        'time' => '10:30',
        'reschedule_reason' => 'Conflito operacional da secretaria.',
    ]);

    $valid
        ->assertOk()
        ->assertJsonPath('data.status', 'Confirmado');
});

test('student cannot schedule in a past time on the same day', function () {
    /** @var \Tests\TestCase $this */
    Carbon::setTestNow(Carbon::parse('2026-03-18 10:00:00'));

    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990009',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['wed'],
        'day_settings' => weekdaySettings(['wed'], '08:00', '12:00'),
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Horário passado',
        'date' => Carbon::today()->toDateString(),
        'time' => '08:00',
    ]);

    $response->assertSessionHasErrors('time');
    $this->assertDatabaseCount('appointments', 0);

    Carbon::setTestNow();
});

test('admin cannot reschedule appointment to a past time on the same day', function () {
    /** @var \Tests\TestCase $this */
    Carbon::setTestNow(Carbon::parse('2026-03-18 10:00:00'));

    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.passado@unifap.edu.br',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['wed'],
        'day_settings' => weekdaySettings(['wed'], '08:00', '12:00'),
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Aluno Teste',
        'student_registration' => '20249990010',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Reagendamento',
        'scheduled_at' => Carbon::today()->setTime(11, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.reschedule', $appointment), [
        'date' => Carbon::today()->toDateString(),
        'time' => '08:30',
        'reschedule_reason' => 'Teste de horário passado.',
    ]);

    $response->assertStatus(422);

    Carbon::setTestNow();
});

test('student cannot schedule with less than minimum lead time', function () {
    /** @var \Tests\TestCase $this */
    Carbon::setTestNow(Carbon::parse('2026-03-18 10:10:00'));

    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990011',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['wed'],
        'day_settings' => weekdaySettings(['wed'], '10:00', '12:00'),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Antecedência mínima',
        'date' => Carbon::today()->toDateString(),
        'time' => '10:30',
    ]);

    $response->assertSessionHasErrors('time');

    Carbon::setTestNow();
});

test('student cannot schedule beyond maximum booking window', function () {
    /** @var \Tests\TestCase $this */
    Carbon::setTestNow(Carbon::parse('2026-03-18 10:00:00'));

    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990012',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'day_settings' => weekdaySettings(['mon', 'tue', 'wed', 'thu', 'fri'], '10:00', '12:00'),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $futureDate = Carbon::today()->addDays(120)->toDateString();

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Janela máxima',
        'date' => $futureDate,
        'time' => '10:00',
    ]);

    $response->assertSessionHasErrors('time');

    Carbon::setTestNow();
});

test('student cannot keep two active appointments in same datetime with different attendants', function () {
    /** @var \Tests\TestCase $this */
    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990013',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Bruno Costa',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday')->toDateString();

    $first = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Primeiro ativo',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $first
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('academic.student.mine'));

    $second = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Bruno Costa',
        'subject' => 'Conflito aluno',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $second->assertSessionHasErrors('time');
});

test('admin status update blocks invalid transition', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.transicao@unifap.edu.br',
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Aluno Teste',
        'student_registration' => '20249990014',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Transição inválida',
        'scheduled_at' => Carbon::now()->addDay()->setTime(10, 0),
        'status' => 'Cancelado',
    ]);

    $response = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.status', $appointment), [
        'status' => 'Confirmado',
    ]);

    $response->assertStatus(422);

    expect($appointment->fresh()->status)->toBe('Cancelado');
});

test('student cannot exceed maximum active appointments limit', function () {
    /** @var \Tests\TestCase $this */
    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990015',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '12:00'),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday');

    Appointment::query()->create([
        'student_name' => 'Aluno Limite',
        'student_registration' => '20249990015',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Ativo 1',
        'scheduled_at' => $monday->copy()->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    Appointment::query()->create([
        'student_name' => 'Aluno Limite',
        'student_registration' => '20249990015',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Ativo 2',
        'scheduled_at' => $monday->copy()->setTime(10, 30),
        'status' => 'Confirmado',
    ]);

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Ultrapassar limite',
        'date' => $monday->toDateString(),
        'time' => '11:00',
    ]);

    $response->assertSessionHasErrors('time');
});

test('student cancel is blocked inside modification window', function () {
    /** @var \Tests\TestCase $this */
    Carbon::setTestNow(Carbon::parse('2026-03-18 10:00:00'));

    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990016',
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => $student->name,
        'student_registration' => '20249990016',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Cancelamento próximo',
        'scheduled_at' => Carbon::parse('2026-03-18 11:00:00'),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($student)->patch(route('academic.student.cancel', $appointment));

    $response->assertSessionHas('status');
    expect($appointment->fresh()->status)->toBe('Pendente');

    Carbon::setTestNow();
});

test('admin cancel requires cancellation reason', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.reason.cancel@unifap.edu.br',
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Aluno Admin',
        'student_registration' => '20249990017',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Cancelamento sem motivo',
        'scheduled_at' => Carbon::now()->addDays(1)->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.status', $appointment), [
        'status' => 'Cancelado',
    ]);

    $response->assertStatus(422);
});

test('admin reschedule requires reason', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.reason.reschedule@unifap.edu.br',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '12:00'),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Aluno Admin',
        'student_registration' => '20249990018',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Reagendamento sem motivo',
        'scheduled_at' => Carbon::now()->next('Monday')->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.reschedule', $appointment), [
        'date' => Carbon::now()->next('Monday')->toDateString(),
        'time' => '10:30',
    ]);

    $response->assertStatus(422);
});

test('attendant shift capacity blocks additional booking when limit is reached', function () {
    /** @var \Tests\TestCase $this */
    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990019',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '08:00', '12:00'),
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday');
    $times = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30'];

    foreach ($times as $index => $time) {
        Appointment::query()->create([
            'student_name' => 'Aluno '.$index,
            'student_registration' => '20249991'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'attendant_name' => 'Prof. Ana Lima',
            'subject' => 'Capacidade '.$index,
            'scheduled_at' => $monday->copy()->setTimeFromTimeString($time),
            'status' => 'Confirmado',
        ]);
    }

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Exceder turno',
        'date' => $monday->toDateString(),
        'time' => '11:00',
    ]);

    $response->assertSessionHasErrors('time');
});

test('student is blocked when no-show recurrence threshold is reached', function () {
    /** @var \Tests\TestCase $this */
    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990020',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    foreach ([5, 10, 20] as $daysAgo) {
        Appointment::query()->create([
            'student_name' => $student->name,
            'student_registration' => '20249990020',
            'attendant_name' => 'Prof. Ana Lima',
            'subject' => 'No-show '.$daysAgo,
            'scheduled_at' => Carbon::now()->subDays($daysAgo),
            'status' => 'Cancelado',
            'cancellation_reason' => 'No-show automático (tolerância de 15 min excedida).',
        ]);
    }

    $monday = Carbon::now()->next('Monday')->toDateString();

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Bloqueado por no-show',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $response->assertSessionHasErrors('time');
});

test('professor dashboard shows own appointments', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create([
        'role' => 'professor',
        'name' => 'Ana Lima',
        'email' => 'prof.ana.dashboard@unifap.edu.br',
    ]);

    Appointment::query()->create([
        'student_name' => 'Gabriel Silva',
        'student_registration' => '20249990003',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Orientação de TCC',
        'scheduled_at' => Carbon::today()->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($professor)->get(route('academic.professor.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Gabriel Silva')
        ->assertSee('Orientação de TCC');
});

test('professor dashboard also shows upcoming appointments', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create([
        'role' => 'professor',
        'name' => 'Professor Exemplo',
        'email' => 'prof.exemplo.upcoming@unifap.edu.br',
    ]);

    Appointment::query()->create([
        'student_name' => 'Aluna Futuro',
        'student_registration' => '20249990111',
        'attendant_name' => 'Prof. Exemplo',
        'subject' => 'Atendimento futuro',
        'scheduled_at' => Carbon::now()->addDays(2)->setTime(14, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($professor)->get(route('academic.professor.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Aluna Futuro')
        ->assertSee('Atendimento futuro');
});

test('admin per-day availability configuration affects student slot validation', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.availability@unifap.edu.br',
    ]);

    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990004',
    ]);

    $this->actingAs($admin)->post(route('academic.admin.schedule.settings.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'slot_duration_minutes' => 30,
        'day_settings' => weekdaySettings(['mon'], '14:00', '15:00'),
    ])->assertSessionHasNoErrors();

    $monday = Carbon::now()->next('Monday')->format('Y-m-d');

    $invalid = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Validação de horário',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $invalid->assertSessionHasErrors('time');

    $valid = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Validação de horário',
        'date' => $monday,
        'time' => '14:00',
    ]);

    $valid
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('academic.student.mine'));
});

test('global blocked date prevents booking for any attendant', function () {
    /** @var \Tests\TestCase $this */
    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990005',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday')->toDateString();

    BlockedDate::query()->create([
        'blocked_date' => $monday,
        'reason' => 'Feriado institucional',
        'attendant_name' => null,
    ]);

    $response = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Atendimento em feriado',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $response->assertSessionHasErrors('time');
});

test('attendant specific blocked date does not block other attendants', function () {
    /** @var \Tests\TestCase $this */
    $professorA = User::factory()->create([
        'role' => 'professor',
        'name' => 'Ana Lima',
        'email' => 'prof.ana.bloqueio@unifap.edu.br',
    ]);

    $professorB = User::factory()->create([
        'role' => 'professor',
        'name' => 'Bruno Costa',
        'email' => 'prof.bruno.bloqueio@unifap.edu.br',
    ]);

    $student = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990006',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'attendant_user_id' => $professorA->id,
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Bruno Costa',
        'attendant_user_id' => $professorB->id,
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday')->toDateString();

    BlockedDate::query()->create([
        'blocked_date' => $monday,
        'reason' => 'Reunião departamental',
        'attendant_name' => 'Prof. Ana Lima',
        'attendant_user_id' => $professorA->id,
    ]);

    $blockedAttempt = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Tentativa bloqueada',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $blockedAttempt->assertSessionHasErrors('time');

    $allowedAttempt = $this->actingAs($student)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Bruno Costa',
        'subject' => 'Tentativa permitida',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $allowedAttempt
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('academic.student.mine'));
});

test('slot competition keeps only first active booking', function () {
    /** @var \Tests\TestCase $this */
    $studentA = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990007',
    ]);

    $studentB = User::factory()->create([
        'role' => 'student',
        'matricula' => '20249990008',
    ]);

    AttendantSchedule::query()->create([
        'attendant_name' => 'Prof. Ana Lima',
        'working_days' => ['mon'],
        'day_settings' => weekdaySettings(['mon'], '10:00', '11:00'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'break_start' => null,
        'break_end' => null,
        'slot_duration_minutes' => 30,
    ]);

    $monday = Carbon::now()->next('Monday')->toDateString();

    $first = $this->actingAs($studentA)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Primeiro agendamento',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $first
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('academic.student.mine'));

    $second = $this->actingAs($studentB)->post(route('academic.student.store'), [
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Segundo agendamento',
        'date' => $monday,
        'time' => '10:00',
    ]);

    $second->assertSessionHasErrors('time');

    $this->assertDatabaseCount('appointments', 1);
});

test('professor can reject appointment with reason', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create([
        'role' => 'professor',
        'name' => 'Ana Lima',
        'email' => 'prof.ana.rejeicao@unifap.edu.br',
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Marcos Silva',
        'student_registration' => '20249990121',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Dúvida de matrícula',
        'scheduled_at' => Carbon::now()->addDay()->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->actingAs($professor)->patch(route('academic.professor.appointments.status', $appointment), [
        'status' => 'Cancelado',
        'cancellation_reason' => 'Reunião institucional no mesmo horário.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'Cancelado',
        'cancellation_reason' => 'Reunião institucional no mesmo horário.',
    ]);
});

test('admin blocked date upsert does not create duplicates for same date and scope', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin.bloqueio.upsert@unifap.edu.br',
    ]);

    $blockedDate = Carbon::now()->next('Friday')->toDateString();

    $first = $this->actingAs($admin)->post(route('academic.admin.schedule.blocked-dates.store'), [
        'blocked_date' => $blockedDate,
        'reason' => 'Feriado local',
        'attendant_key' => 'all',
    ]);

    $first->assertSessionHasNoErrors()->assertRedirect();

    $second = $this->actingAs($admin)->post(route('academic.admin.schedule.blocked-dates.store'), [
        'blocked_date' => $blockedDate,
        'reason' => 'Feriado municipal atualizado',
        'attendant_key' => 'all',
    ]);

    $second->assertSessionHasNoErrors()->assertRedirect();

    $records = BlockedDate::query()
        ->whereDate('blocked_date', $blockedDate)
        ->whereNull('attendant_name')
        ->whereNull('attendant_user_id')
        ->get();

    expect($records)->toHaveCount(1);
    expect((string) $records->first()->reason)->toBe('Feriado municipal atualizado');
});

test('professor rejection requires reason', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create([
        'role' => 'professor',
        'name' => 'Ana Lima',
        'email' => 'prof.ana.sem.motivo@unifap.edu.br',
    ]);

    $appointment = Appointment::query()->create([
        'student_name' => 'Maria Souza',
        'student_registration' => '20249990122',
        'attendant_name' => 'Prof. Ana Lima',
        'subject' => 'Atendimento',
        'scheduled_at' => Carbon::now()->addDay()->setTime(11, 0),
        'status' => 'Pendente',
    ]);

    $response = $this->from(route('academic.professor.dashboard'))->actingAs($professor)->patch(route('academic.professor.appointments.status', $appointment), [
        'status' => 'Cancelado',
        'cancellation_reason' => '',
    ]);

    $response
        ->assertRedirect(route('academic.professor.dashboard'))
        ->assertSessionHasErrors('cancellation_reason');
});
