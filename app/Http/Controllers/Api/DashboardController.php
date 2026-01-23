<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Services\ReportingMetricsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReportingRequest;
use App\Models\Appointment;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportingMetricsService $reportingService,
    ) {}

    public function index(): JsonResponse
    {
        $today = Carbon::today();

        $todayAppointments = Appointment::with(['client', 'service', 'staff'])
            ->forDate($today)
            ->orderBy('starts_at')
            ->get();

        $upcomingAppointments = Appointment::with(['client', 'service'])
            ->upcoming()
            ->limit(5)
            ->get();

        return response()->json([
            'today' => [
                'date' => $today->toDateString(),
                'appointments' => $todayAppointments,
                'total_count' => $todayAppointments->count(),
                'completed_count' => $todayAppointments->where('status', 'completed')->count(),
            ],
            'upcoming' => $upcomingAppointments,
        ]);
    }

    public function stats(): JsonResponse
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $weeklyAppointments = Appointment::forDateRange($startOfWeek, $endOfWeek)->get();
        $monthlyAppointments = Appointment::forDateRange($startOfMonth, $endOfMonth)->get();

        $weeklyRevenue = $weeklyAppointments
            ->where('status', 'completed')
            ->sum(fn ($apt) => $apt->service->price ?? 0);

        $monthlyRevenue = $monthlyAppointments
            ->where('status', 'completed')
            ->sum(fn ($apt) => $apt->service->price ?? 0);

        $newClientsThisWeek = Client::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
        $newClientsThisMonth = Client::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        return response()->json([
            'weekly' => [
                'appointments' => $weeklyAppointments->count(),
                'completed' => $weeklyAppointments->where('status', 'completed')->count(),
                'cancelled' => $weeklyAppointments->where('status', 'cancelled')->count(),
                'revenue' => $weeklyRevenue,
                'new_clients' => $newClientsThisWeek,
            ],
            'monthly' => [
                'appointments' => $monthlyAppointments->count(),
                'completed' => $monthlyAppointments->where('status', 'completed')->count(),
                'cancelled' => $monthlyAppointments->where('status', 'cancelled')->count(),
                'revenue' => $monthlyRevenue,
                'new_clients' => $newClientsThisMonth,
            ],
        ]);
    }

    public function report(ReportingRequest $request): JsonResponse
    {
        $dateRange = $request->toDateRangeDTO();

        $report = $request->shouldIncludeComparison()
            ? $this->reportingService->generateFullReport($dateRange)
            : $this->reportingService->generateFullReportWithoutComparison($dateRange);

        return response()->json(['data' => $report->toArray()]);
    }
}
