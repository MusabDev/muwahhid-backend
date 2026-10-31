<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    /**
     * Update the name and email. Changing the email needs the current password and re-verification.
     */
    public function updateProfile(Request $request): UserResource
    {
        $user = $request->user();

        $request->merge(['email' => Str::lower(trim((string) $request->input('email', $user->email)))]);
        $emailChanged = $request->input('email') !== $user->email;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'string', 'current_password:web'],
        ]);

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return new UserResource($user);
    }

    /**
     * Change the password and sign out every other session.
     */
    public function updatePassword(Request $request): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();

        $this->deleteOtherSessions($request);
        $this->refreshCurrentSession($request);

        return response()->noContent();
    }

    /**
     * List the browser sessions signed in to this account.
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->session()->getId();

        $sessions = DB::table(config('session.table'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session) => [
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => hash_equals($currentId, $session->id),
            ]);

        return response()->json(['data' => $sessions]);
    }

    /**
     * Sign out every session except the current one.
     */
    public function destroyOtherSessions(Request $request): Response
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']]);

        Auth::guard('web')->logoutOtherDevices($request->input('password'));
        $request->user()->tokens()->delete();

        $this->deleteOtherSessions($request);
        $this->refreshCurrentSession($request);

        return response()->noContent();
    }

    /**
     * Permanently delete the account and all of its data.
     */
    public function destroy(Request $request): Response
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']]);

        $user = $request->user();

        Auth::guard('web')->logout();
        DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function deleteOtherSessions(Request $request): void
    {
        DB::table(config('session.table'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    /**
     * Re-login after a credential change so the session and remember cookie carry the new hashes.
     */
    private function refreshCurrentSession(Request $request): void
    {
        $guard = Auth::guard('web');
        $remember = $guard->viaRemember() || $request->hasCookie($guard->getRecallerName());

        $guard->login($request->user(), $remember);
        $request->session()->regenerate(destroy: true);
    }
}
