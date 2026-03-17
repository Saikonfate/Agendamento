<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'aluno@unifap.edu.br'],
            [
                'name' => 'Gabriel Silva',
                'matricula' => '20241180203',
                'role' => 'student',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'atendente@unifap.edu.br'],
            [
                'name' => 'Atendente Secretaria',
                'role' => 'attendant',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
