<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Notice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Carbon::today();

        $appointments = Appointment::query()
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $notices = Notice::query()
            ->latest()
            ->get(['id', 'message', 'tone']);

        $scheduledCount = $appointments->whereIn('status', ['Confirmado', 'Pendente', 'Realizado'])->count();
        $completedCount = $appointments->where('status', 'Realizado')->count();
        $pendingCount = $appointments->where('status', 'Pendente')->count();

        $dateLabel = mb_convert_case(
            $today->locale('pt_BR')->translatedFormat('l, d \\d\\e F \\d\\e Y'),
            MB_CASE_TITLE,
            'UTF-8',
        );

        return view('academic.admin-dashboard', [
            'notices' => $notices,
            'appointments' => $appointments,
            'dateLabel' => $dateLabel,
            'scheduledCount' => $scheduledCount,
            'completedCount' => $completedCount,
            'pendingCount' => $pendingCount,
            'vacancyCount' => 4,
        ]);
    }
}
