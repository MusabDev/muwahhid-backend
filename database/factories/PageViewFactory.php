<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->slug(2);

        return [
            'uuid' => (string) Str::uuid(),
            'visitor_hash' => hash('sha256', fake()->uuid()),
            'session_key' => (string) Str::uuid(),
            'path' => "/books/{$slug}",
            'route' => '/books/$slug',
            'title' => fake()->sentence(3),
            'referrer_host' => fake()->optional()->randomElement(['www.google.com', 'www.facebook.com', 't.me']),
            'country' => fake()->randomElement(['BD', 'SA', 'GB', 'US']),
            'device' => fake()->randomElement(['desktop', 'smartphone', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari']),
            'os' => fake()->randomElement(['Android', 'Windows', 'iOS']),
            'language' => fake()->randomElement(['bn', 'en', 'ar']),
            'screen_width' => fake()->randomElement([390, 1440, 1920]),
            'is_authenticated' => fake()->boolean(20),
            'duration_seconds' => fake()->optional()->numberBetween(1, 600),
            'created_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
