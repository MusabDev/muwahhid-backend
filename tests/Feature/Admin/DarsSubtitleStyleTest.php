<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Dars;
use App\Models\DarsSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarsSubtitleStyleTest extends TestCase
{
    use RefreshDatabase;

    private function makeDars(): Dars
    {
        $author = Author::create(['slug' => 'author', 'name_ar' => 'مؤلف', 'name_bn' => 'লেখক', 'name_translit' => 'Author']);
        $series = DarsSeries::create(['slug' => 'series', 'author_id' => $author->id, 'title_ar' => 'سلسلة', 'title_bn' => 'সিরিজ', 'title_translit' => 'Series', 'is_published' => true]);

        return $series->durus()->create(['number' => 1, 'title_bn' => 'দারস', 'media_type' => 'video', 'media_url' => 'https://example.com/a.mp4']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_admin_can_save_a_subtitle_style_that_the_public_page_returns(): void
    {
        $dars = $this->makeDars();
        $style = ['valign' => 'top', 'position' => 8, 'offsetX' => -20, 'fontBn' => 'kalpurush', 'sizeBn' => 150, 'align' => 'left', 'width' => 60, 'colorBn' => '#ffe14d', 'bold' => false];

        $this->actingAs($this->admin())
            ->putJson("/api/admin/durus/{$dars->id}", ['subtitle_style' => $style])
            ->assertOk()
            ->assertJsonPath('subtitle_style.valign', 'top');

        $this->getJson('/api/dars-series/series/durus/1')
            ->assertOk()
            ->assertJsonPath('subtitle_style', $style);
    }

    public function test_admin_can_set_the_default_subtitle_languages(): void
    {
        $dars = $this->makeDars();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/admin/durus/{$dars->id}", ['subtitle_mode' => 'both'])->assertOk();
        $this->getJson('/api/dars-series/series/durus/1')->assertJsonPath('subtitle_mode', 'both');

        $this->actingAs($admin)->putJson("/api/admin/durus/{$dars->id}", ['subtitle_mode' => 'off'])->assertJsonValidationErrors('subtitle_mode');
        $this->actingAs($admin)->putJson("/api/admin/durus/{$dars->id}", ['subtitle_mode' => null])->assertOk();
        $this->assertNull($dars->fresh()->subtitle_mode);
    }

    public function test_each_language_can_have_its_own_cue(): void
    {
        $dars = $this->makeDars();
        $style = ['separate' => true, 'ar' => ['valign' => 'top', 'position' => 6, 'shape' => 'box'], 'bn' => ['position' => 4, 'stroke' => false, 'shadowColor' => '#1e3a8a', 'shadowBlur' => 16, 'shadowY' => -3, 'transition' => 'words', 'transitionMs' => 500]];

        $this->actingAs($this->admin())
            ->putJson("/api/admin/durus/{$dars->id}", ['subtitle_style' => $style])
            ->assertOk()
            ->assertJsonPath('subtitle_style', $style);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/durus/{$dars->id}", ['subtitle_style' => ['ar' => ['fontAr' => 'amiri', 'valign' => 'left']]])
            ->assertJsonValidationErrors(['subtitle_style.ar', 'subtitle_style.ar.valign']);
    }

    public function test_invalid_subtitle_style_is_rejected(): void
    {
        $dars = $this->makeDars();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/durus/{$dars->id}", ['subtitle_style' => ['valign' => 'sideways', 'colorAr' => 'red', 'sizeAr' => 999, 'transition' => 'spin', 'transitionMs' => 5000, 'unknown' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subtitle_style', 'subtitle_style.valign', 'subtitle_style.colorAr', 'subtitle_style.sizeAr', 'subtitle_style.transition', 'subtitle_style.transitionMs']);
    }

    public function test_empty_or_null_style_clears_it(): void
    {
        $dars = $this->makeDars();
        $dars->update(['subtitle_style' => ['valign' => 'top']]);
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/admin/durus/{$dars->id}", ['subtitle_style' => []])->assertOk();
        $this->assertNull($dars->fresh()->subtitle_style);

        $dars->update(['subtitle_style' => ['valign' => 'top']]);
        $this->actingAs($admin)->putJson("/api/admin/durus/{$dars->id}", ['subtitle_style' => null])->assertOk();
        $this->assertNull($dars->fresh()->subtitle_style);
    }
}
