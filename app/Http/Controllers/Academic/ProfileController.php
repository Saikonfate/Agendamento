<?php

namespace App\Http\Controllers\Academic;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Update the authenticated user's profile details.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'personal_email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'personal_email')->ignore($user->id),
            ],
        ];

        if ($user->role !== 'student') {
            $rules['email'] = [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user->id),
            ];
        }

        $validated = $request->validateWithBag('profileUpdate', $rules, [
            'name.required' => 'O nome é obrigatório.',
            'name.max'      => 'O nome deve ter no máximo :max caracteres.',
            'email.required' => 'O e-mail institucional é obrigatório.',
            'email.email'   => 'Informe um e-mail válido.',
            'email.unique'  => 'Este e-mail já está em uso.',
            'personal_email.email' => 'Informe um e-mail pessoal válido.',
            'personal_email.unique' => 'Este e-mail pessoal já está em uso.',
        ]);

        // Students cannot change institutional data from profile
        if ($user->role === 'student') {
            unset($validated['matricula']);
            unset($validated['email']);
        }

        $user->fill($validated);
        $user->save();

        return back()->with('profile_status', 'Dados atualizados com sucesso.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('passwordUpdate', [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ], [
            'current_password.required'         => 'A senha atual é obrigatória.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required'                 => 'A nova senha é obrigatória.',
            'password.confirmed'                => 'A confirmação da senha não confere.',
            'password.min'                      => 'A senha deve ter pelo menos :min caracteres.',
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return back()->with('password_status', 'Senha atualizada com sucesso.');
    }

    /**
     * Update the authenticated user's profile photo.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('photoUpdate', [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.required' => 'Selecione uma foto.',
            'photo.image'    => 'O arquivo deve ser uma imagem.',
            'photo.mimes'    => 'A foto deve ser PNG, JPG ou WEBP.',
            'photo.max'      => 'A foto deve ter no máximo 2MB.',
        ]);

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $validated['photo']->store('profile-photos', 'public');

        $user->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        return back()->with('photo_status', 'Foto de perfil atualizada com sucesso.');
    }
}
