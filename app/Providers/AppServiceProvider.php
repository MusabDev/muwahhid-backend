<?php

namespace App\Providers;

use App\Models\BookSentence;
use App\Models\DarsSegment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

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
    }
}
