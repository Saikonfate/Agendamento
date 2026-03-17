<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNoticeController extends Controller
{
    public function index(): JsonResponse
    {
        $notices = Notice::query()
            ->latest()
            ->get(['id', 'message', 'tone']);

        return response()->json([
            'data' => $notices,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'tone' => ['required', 'in:amber,violet'],
        ], [
            'message.required' => 'Informe o texto do aviso.',
            'message.max' => 'O aviso deve ter no máximo 255 caracteres.',
            'tone.required' => 'Informe o tipo do aviso.',
            'tone.in' => 'Tipo de aviso inválido.',
        ]);

        $notice = Notice::query()->create($validated);

        return response()->json([
            'data' => $notice->only(['id', 'message', 'tone']),
            'message' => 'Aviso criado com sucesso.',
        ], 201);
    }

    public function update(Request $request, Notice $notice): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'tone' => ['required', 'in:amber,violet'],
        ], [
            'message.required' => 'Informe o texto do aviso.',
            'message.max' => 'O aviso deve ter no máximo 255 caracteres.',
            'tone.required' => 'Informe o tipo do aviso.',
            'tone.in' => 'Tipo de aviso inválido.',
        ]);

        $notice->update($validated);

        return response()->json([
            'data' => $notice->only(['id', 'message', 'tone']),
            'message' => 'Aviso atualizado com sucesso.',
        ]);
    }

    public function destroy(Notice $notice): JsonResponse
    {
        $notice->delete();

        return response()->json([
            'message' => 'Aviso removido com sucesso.',
        ]);
    }
}
