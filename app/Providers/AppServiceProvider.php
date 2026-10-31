<?php

namespace App\Providers;

use App\Models\BookSentence;
use App\Models\DarsSegment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'book_sentence' => BookSentence::class,
            'dars_segment' => DarsSegment::class,
        ]);

        JsonResource::withoutWrapping();

        $this->configureAuth();
    }

    /**
     * Password rules, and reset / verification links that open the SPA instead of the API.
     */
    private function configureAuth(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->max(255)->letters()->numbers()->uncompromised()
            : Password::min(8)->max(255));

        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('পাসওয়ার্ড রিসেট')
            ->greeting('আস-সালামু আলাইকুম')
            ->line('আপনার অ্যাকাউন্টের পাসওয়ার্ড রিসেট করার অনুরোধ পেয়েছি।')
            ->action('পাসওয়ার্ড রিসেট করুন', config('app.frontend_url').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]))
            ->line('লিংকটি '.config('auth.passwords.users.expire').' মিনিট পর্যন্ত কার্যকর থাকবে।')
            ->line('আপনি অনুরোধ না করে থাকলে এই ইমেইলটি উপেক্ষা করুন।'));

        VerifyEmail::createUrlUsing(function (User $user): string {
            $signed = URL::temporarySignedRoute('verification.verify', Carbon::now()->addMinutes(60), [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ], absolute: false);

            parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

            return config('app.frontend_url').'/verify-email?'.http_build_query([
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                'expires' => $query['expires'],
                'signature' => $query['signature'],
            ]);
        });

        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('ইমেইল যাচাই করুন')
            ->greeting('আস-সালামু আলাইকুম')
            ->line('আপনার ইমেইল ঠিকানা যাচাই করতে নিচের বাটনে ক্লিক করুন।')
            ->action('ইমেইল যাচাই করুন', $url)
            ->line('আপনি অ্যাকাউন্ট না খুলে থাকলে এই ইমেইলটি উপেক্ষা করুন।'));
    }
}
