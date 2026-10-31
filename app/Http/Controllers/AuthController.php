<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::create($data);

        event(new Registered($user));

        Auth::guard('web')->login($user, remember: $request->boolean('remember', true));
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();

        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Email a reset link. The response is the same whether or not the account exists.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __('passwords.sent')]);
    }

    /**
     * Set a new password from a reset token and sign the account out everywhere.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table(config('session.table'))->where('user_id', $user->id)->delete();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status === Password::INVALID_USER ? Password::INVALID_TOKEN : $status),
            ]);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * Verify an email address from the signed link sent to it.
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email verified.']);
    }

    public function resendVerification(Request $request): Response
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }
}
