<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('academic.auth');
        }

        if (empty($roles) || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        $targetRoute = $user->role === 'attendant'
            ? 'academic.attendant.dashboard'
            : 'academic.student.dashboard';

        return response()->view('errors.403', [
            'targetUrl' => route($targetRoute),
            'targetLabel' => $user->role === 'attendant' ? 'Ir para painel do atendente' : 'Ir para painel do aluno',
            'message' => 'Seu perfil não possui permissão para acessar esta área.',
        ], 403);
    }
}
