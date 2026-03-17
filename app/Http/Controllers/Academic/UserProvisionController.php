<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserProvisionController extends Controller
{
    public function storeStudent(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('studentCreate', [
            'name' => ['required', 'string', 'max:255'],
            'personal_email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class, 'personal_email')],
        ], [
            'name.required' => 'O nome do aluno é obrigatório.',
            'name.max' => 'O nome do aluno deve ter no máximo :max caracteres.',
            'personal_email.email' => 'Informe um e-mail pessoal válido.',
            'personal_email.unique' => 'Este e-mail pessoal já está em uso.',
        ]);

        $matricula = $this->generateUniqueMatricula();
        $email = 'aluno.'.$matricula.'@unifap.edu.br';

        User::create([
            'name' => $validated['name'],
            'matricula' => $matricula,
            'email' => $email,
            'personal_email' => $validated['personal_email'] ?? null,
            'role' => 'student',
            'email_verified_at' => now(),
            'password' => Hash::make('123456'),
            'must_change_password' => true,
        ]);

        return back()->with('status', "Aluno cadastrado com sucesso. Matrícula: {$matricula} | E-mail: {$email} | Senha padrão: 123456");
    }

    public function storeProfessor(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('professorCreate', [
            'name' => ['required', 'string', 'max:255'],
            'personal_email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class, 'personal_email')],
        ], [
            'name.required' => 'O nome do professor é obrigatório.',
            'name.max' => 'O nome do professor deve ter no máximo :max caracteres.',
            'personal_email.email' => 'Informe um e-mail pessoal válido.',
            'personal_email.unique' => 'Este e-mail pessoal já está em uso.',
        ]);

        $email = $this->generateUniqueProfessorEmail($validated['name']);

        User::create([
            'name' => $validated['name'],
            'email' => $email,
            'personal_email' => $validated['personal_email'] ?? null,
            'role' => 'professor',
            'email_verified_at' => now(),
            'password' => Hash::make('123456'),
            'must_change_password' => false,
        ]);

        return back()->with('status', "Professor cadastrado com sucesso. E-mail: {$email} | Senha padrão: 123456");
    }

    private function generateUniqueMatricula(): string
    {
        $year = now()->format('Y');

        do {
            $matricula = $year.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (User::where('matricula', $matricula)->exists());

        return $matricula;
    }

    private function generateUniqueProfessorEmail(string $name): string
    {
        $base = Str::slug($name, '.');
        $base = $base !== '' ? $base : 'professor';

        do {
            $email = 'professor.'.$base.'.'.random_int(100, 999).'@unifap.edu.br';
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
