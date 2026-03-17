<?php

use App\Models\Appointment;
use App\Models\AttendantSchedule;
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

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

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
        'scheduled_at' => Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0),
        'status' => 'Pendente',
    ]);

    $tuesday = Carbon::now()->next(Carbon::TUESDAY)->format('Y-m-d');

    $invalid = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.reschedule', $appointment), [
        'date' => $tuesday,
        'time' => '10:00',
    ]);

    $invalid->assertStatus(422);

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $valid = $this->actingAs($admin)->patchJson(route('academic.admin.appointments.reschedule', $appointment), [
        'date' => $monday,
        'time' => '10:30',
    ]);

    $valid
        ->assertOk()
        ->assertJsonPath('data.status', 'Confirmado');
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

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

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
