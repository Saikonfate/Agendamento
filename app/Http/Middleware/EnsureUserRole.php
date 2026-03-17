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

        $targetRoute = match ($user->role) {
            'admin' => 'academic.admin.dashboard',
            'professor' => 'academic.professor.dashboard',
            default => 'academic.student.dashboard',
        };

        $targetLabel = match ($user->role) {
            'admin' => 'Ir para painel do admin',
            'professor' => 'Ir para painel do professor',
            default => 'Ir para painel do aluno',
        };

        return response()->view('errors.403', [
            'targetUrl' => route($targetRoute),
            'targetLabel' => $targetLabel,
            'message' => 'Seu perfil não possui permissão para acessar esta área.',
        ], 403);
    }
}
