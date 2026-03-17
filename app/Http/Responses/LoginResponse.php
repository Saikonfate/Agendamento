<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        /** @var Request $request */
        $user = $request->user();

        $target = match ($user?->role) {
            'admin' => route('academic.admin.dashboard'),
            'professor' => route('academic.professor.dashboard'),
            default => route('academic.student.dashboard'),
        };

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $target])
            : redirect()->intended($target);
    }
}
