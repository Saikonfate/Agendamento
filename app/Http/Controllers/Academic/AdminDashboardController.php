<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Notice;
use App\Models\User;
use App\Services\SchedulingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function __construct(
        private SchedulingService $scheduling,
    ) {}

    public function __invoke(): View
    {
        $today = Carbon::today();

        $appointments = Appointment::query()
            ->with('attendantUser:id,name')
            ->whereDate('scheduled_at', '>=', $today)
            ->orderBy('scheduled_at')
            ->get();

        $notices = Notice::query()
            ->latest()
            ->get(['id', 'message', 'tone']);

        $scheduledCount = $appointments->whereIn('status', ['Confirmado', 'Pendente', 'Realizado'])->count();
        $completedCount = $appointments->where('status', 'Realizado')->count();
        $pendingCount = $appointments->where('status', 'Pendente')->count();

        $referenceDate = $today->copy()->addDays(SchedulingService::MIN_DAYS_IN_ADVANCE);

        $vacancyCount = User::query()
            ->where('role', 'professor')
            ->get(['id', 'name'])
            ->sum(function (User $professor) use ($referenceDate): int {
                return collect($this->scheduling->buildSlotsForDate(
                    $referenceDate,
                    $this->scheduling->canonicalProfessorName($professor->name),
                    $professor->id,
                ))->where('available', true)->count();
            });

        $dateLabel = $today->format('d/m/y');

        return view('academic.admin-dashboard', [
            'notices' => $notices,
            'appointments' => $appointments,
            'dateLabel' => $dateLabel,
            'periodStartLabel' => $today->format('d/m/y'),
            'scheduledCount' => $scheduledCount,
            'completedCount' => $completedCount,
            'pendingCount' => $pendingCount,
            'vacancyCount' => $vacancyCount,
        ]);
    }
}
