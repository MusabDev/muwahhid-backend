<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly Analytics $analytics) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'range' => ['nullable', 'in:today,yesterday,7d,30d,90d,12m,custom'],
            'from' => ['required_if:range,custom', 'nullable', 'date'],
            'to' => ['required_if:range,custom', 'nullable', 'date', 'after_or_equal:from'],
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        $today = CarbonImmutable::now(Analytics::TIMEZONE)->startOfDay();

        [$from, $to] = match ($data['range'] ?? '7d') {
            'today' => [$today, $today->addDay()],
            'yesterday' => [$today->subDay(), $today],
            '30d' => [$today->subDays(29), $today->addDay()],
            '90d' => [$today->subDays(89), $today->addDay()],
            '12m' => [$today->subMonthsNoOverflow(11)->startOfMonth(), $today->addDay()],
            'custom' => [
                CarbonImmutable::parse($data['from'], Analytics::TIMEZONE)->startOfDay(),
                CarbonImmutable::parse($data['to'], Analytics::TIMEZONE)->startOfDay()->addDay(),
            ],
            default => [$today->subDays(6), $today->addDay()],
        };

        return response()->json($this->analytics->report($from, $to, $data['path'] ?? null));
    }

    public function realtime(): JsonResponse
    {
        return response()->json($this->analytics->realtime());
    }
}
