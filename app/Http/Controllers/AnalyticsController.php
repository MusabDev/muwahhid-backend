<?php

namespace App\Http\Controllers;

use App\Services\Analytics;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives the site's page-view beacon. Bodies arrive as text/plain JSON so the browser
 * can send them with sendBeacon and without a CORS preflight.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly Analytics $analytics) {}

    public function collect(Request $request): Response
    {
        $this->mergeJsonBody($request);

        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'path' => ['required', 'string', 'starts_with:/', 'max:2000'],
            'route' => ['nullable', 'string', 'max:300'],
            'title' => ['nullable', 'string', 'max:1000'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'utm_source' => ['nullable', 'string', 'max:300'],
            'utm_medium' => ['nullable', 'string', 'max:300'],
            'utm_campaign' => ['nullable', 'string', 'max:300'],
            'language' => ['nullable', 'string', 'max:35'],
            'screen_width' => ['nullable', 'integer', 'between:1,10000'],
        ]);

        $this->analytics->record($request, $data);

        return response()->noContent();
    }

    public function engage(Request $request): Response
    {
        $this->mergeJsonBody($request);

        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'seconds' => ['required', 'integer', 'min:0'],
        ]);

        $this->analytics->engage($request, $data['id'], $data['seconds']);

        return response()->noContent();
    }

    private function mergeJsonBody(Request $request): void
    {
        if (! $request->isJson()) {
            $request->merge(json_decode($request->getContent(), true) ?: []);
        }
    }
}
