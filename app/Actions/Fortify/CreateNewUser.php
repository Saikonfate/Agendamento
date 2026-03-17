<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'matricula' => ['required', 'string', 'max:20', 'unique:users,matricula'],
            'password' => $this->passwordRules(),
        ], [
            'name.required'      => 'O nome é obrigatório.',
            'name.max'           => 'O nome deve ter no máximo :max caracteres.',
            'email.required'     => 'O e-mail é obrigatório.',
            'email.email'        => 'Informe um e-mail válido.',
            'email.unique'       => 'Este e-mail já está em uso.',
            'matricula.required' => 'A matrícula é obrigatória.',
            'matricula.unique'   => 'Esta matrícula já está cadastrada.',
            'password.required'  => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min'       => 'A senha deve ter pelo menos :min caracteres.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'matricula' => $input['matricula'],
            'role' => 'student',
            'email' => $input['email'],
            'email_verified_at' => now(),
            'password' => $input['password'],
        ]);
    }
}
