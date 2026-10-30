<?php

namespace App\Http\Controllers\Me;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserDarsResource;
use App\Models\Dars;
use App\Models\DarsSeries;
use App\Models\UserDars;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DurusController extends Controller
{
    /**
     * Share of the dars after which it counts as fully listened.
     */
    public const COMPLETED_AT = 95;

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(BookStatus::class)],
            'series_id' => ['nullable', 'integer'],
        ]);

        $entries = $request->user()->durus()
            ->with(['dars.series.author'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('series_id'), fn ($query) => $query->whereHas('dars', fn ($dars) => $dars->where('series_id', $request->integer('series_id'))))
            ->orderByDesc('last_played_at')
            ->orderByDesc('updated_at')
            ->get();

        return UserDarsResource::collection($entries);
    }

    public function show(Request $request, Dars $dars): UserDarsResource
    {
        return new UserDarsResource($this->entry($request, $dars));
    }

    /**
     * Set the dars's library status (null removes the status but keeps the position).
     */
    public function updateStatus(Request $request, Dars $dars): UserDarsResource
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(BookStatus::class)]]);

        $entry = $this->entry($request, $dars);
        $entry->status = $data['status'] ?? null;
        $entry->save();

        return new UserDarsResource($entry);
    }

    /**
     * Set the status of every dars in a series at once (null takes the whole series
     * off the list but keeps each position).
     */
    public function updateSeriesStatus(Request $request, DarsSeries $darsSeries): AnonymousResourceCollection
    {
        abort_unless($darsSeries->is_published || $request->user()->is_admin, 404);

        $data = $request->validate(['status' => ['nullable', Rule::enum(BookStatus::class)]]);

        $entries = $darsSeries->durus->map(function (Dars $dars) use ($request, $data) {
            $entry = $this->entry($request, $dars);
            $entry->status = $data['status'] ?? null;
            $entry->save();

            return $entry;
        });

        return UserDarsResource::collection($entries);
    }

    /**
     * Save the playback position; called periodically by the player.
     */
    public function updateProgress(Request $request, Dars $dars): UserDarsResource
    {
        $data = $request->validate([
            'position_seconds' => ['required', 'numeric', 'min:0'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entry = $this->entry($request, $dars);
        $entry->fill([
            'position_seconds' => $data['position_seconds'],
            'progress' => self::progressFor($dars, $data['position_seconds'], $data['duration_seconds'] ?? null),
            'last_played_at' => now(),
        ]);

        self::applyAutomaticStatus($entry);
        $entry->save();

        return new UserDarsResource($entry);
    }

    public function destroy(Request $request, Dars $dars): Response
    {
        $request->user()->durus()->where('dars_id', $dars->id)->delete();

        return response()->noContent();
    }

    private function entry(Request $request, Dars $dars): UserDars
    {
        return UserDars::firstOrNew(['user_id' => $request->user()->id, 'dars_id' => $dars->id]);
    }

    /**
     * Listening starts the "reading" status, and reaching the end completes it,
     * unless the user picked another status themselves.
     */
    public static function applyAutomaticStatus(UserDars $entry): void
    {
        if ($entry->progress >= self::COMPLETED_AT && in_array($entry->status, [null, BookStatus::Reading], true)) {
            $entry->status = BookStatus::Completed;
        } elseif ($entry->status === null) {
            $entry->status = BookStatus::Reading;
        }
    }

    /**
     * Percent listened, using the stored length or the one the player reports.
     */
    public static function progressFor(Dars $dars, float $position, ?float $duration = null): float
    {
        $total = $dars->duration_seconds ?: $duration;

        if (! $total) {
            return 0;
        }

        return round(min($position / $total, 1) * 100, 2);
    }
}
