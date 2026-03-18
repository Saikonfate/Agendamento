<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Criar usuários reais
        $student = User::updateOrCreate(
            ['email' => 'aluno@unifap.edu.br'],
            [
                'name' => 'Gabriel Silva',
                'matricula' => '20241180203',
                'role' => 'student',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@unifap.edu.br'],
            [
                'name' => 'Admin Secretaria',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $professor1 = User::updateOrCreate(
            ['email' => 'professor@unifap.edu.br'],
            [
                'name' => 'Professor Exemplo',
                'role' => 'professor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $professor2 = User::updateOrCreate(
            ['email' => 'professor.teste@unifap.edu.br'],
            [
                'name' => 'Professor Teste',
                'role' => 'professor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Criar mais dois professores usados nos agendamentos
        $profRamon = User::updateOrCreate(
            ['email' => 'ramon@unifap.edu.br'],
            [
                'name' => 'Prof. Ramon',
                'role' => 'professor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $profAnaLima = User::updateOrCreate(
            ['email' => 'ana.lima@unifap.edu.br'],
            [
                'name' => 'Prof. Ana Lima',
                'role' => 'professor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if (Schema::hasTable('appointments')) {
            $today = Carbon::today();

            foreach ([
                ['09:00', 'Gabriel Silva', '20241180203', $profRamon->id, 'Prof. Ramon', 'Orientação TCC', 'Confirmado'],
                ['10:00', 'Luiz Gabriel', '20232130014', $professor1->id, 'Professor Exemplo', 'Histórico escolar', 'Pendente'],
                ['11:00', 'Jose Ueider', '200610066', $professor2->id, 'Professor Teste', 'Requerimento geral', 'Confirmado'],
                ['13:30', 'Ana Souza', '20231140021', $profAnaLima->id, 'Prof. Ana Lima', 'Revisão de prova', 'Confirmado'],
                ['14:00', 'Carlos Melo', '20222110088', $professor1->id, 'Professor Exemplo', 'Declaração de vínculo', 'Realizado'],
                ['15:00', 'Fernanda Costa', '20241190044', $profRamon->id, 'Prof. Ramon', 'Orientação TCC', 'Realizado'],
            ] as [$time, $studentName, $registration, $attendantUserId, $attendantName, $subject, $status]) {
                $scheduledAt = $today->copy()->setTimeFromTimeString($time);

                Appointment::updateOrCreate(
                    [
                        'student_registration' => $registration,
                        'scheduled_at' => $scheduledAt,
                    ],
                    [
                        'student_name' => $studentName,
                        'attendant_user_id' => $attendantUserId,
                        'attendant_name' => $attendantName,
                        'subject' => $subject,
                        'status' => $status,
                    ],
                );
            }
        }

        if (Schema::hasTable('blocked_dates')) {
            $saoJoseDate = Carbon::createFromDate(Carbon::today()->year, 3, 19);
            if ($saoJoseDate->isPast()) {
                $saoJoseDate->addYear();
            }

            BlockedDate::query()->updateOrCreate(
                [
                    'blocked_date' => $saoJoseDate->toDateString(),
                    'attendant_user_id' => null,
                    'attendant_name' => null,
                ],
                [
                    'reason' => 'Feriado — Dia de São José',
                ],
            );

            BlockedDate::query()->updateOrCreate(
                [
                    'blocked_date' => Carbon::today()->addDays(8)->toDateString(),
                    'attendant_user_id' => $profRamon->id,
                    'attendant_name' => 'Prof. Ramon',
                ],
                [
                    'reason' => 'Congresso acadêmico',
                ],
            );
        }
    }
}
