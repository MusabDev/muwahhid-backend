<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\DarsSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private Author $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = Author::create(['slug' => 'author', 'name_ar' => 'مؤلف', 'name_bn' => 'লেখক', 'name_translit' => 'Author']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function book(string $slug): Book
    {
        return Book::create(['slug' => $slug, 'author_id' => $this->author->id, 'title_ar' => 'كتاب', 'title_bn' => 'কিতাব', 'title_translit' => $slug]);
    }

    private function series(string $slug): DarsSeries
    {
        return DarsSeries::create(['slug' => $slug, 'author_id' => $this->author->id, 'title_ar' => 'سلسلة', 'title_bn' => 'সিরিজ', 'title_translit' => $slug]);
    }

    public function test_books_can_be_published_recategorized_reassigned_and_deleted_together(): void
    {
        [$a, $b, $c] = [$this->book('a'), $this->book('b'), $this->book('c')];
        $category = Category::create(['slug' => 'fiqh', 'name_bn' => 'ফিকহ']);
        $other = Author::create(['slug' => 'other', 'name_ar' => 'آخر', 'name_bn' => 'অন্য', 'name_translit' => 'Other']);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/books/bulk', ['ids' => [$a->id, $b->id], 'action' => 'publish'])->assertOk()->assertJsonPath('affected', 2);
        $this->assertTrue($a->fresh()->is_published);
        $this->assertFalse($c->fresh()->is_published);

        $this->actingAs($admin)->postJson('/api/admin/books/bulk', ['ids' => [$a->id, $c->id], 'action' => 'category', 'category_id' => $category->id])->assertOk();
        $this->assertSame($category->id, $c->fresh()->category_id);

        $version = $b->fresh()->content_version;
        $this->actingAs($admin)->postJson('/api/admin/books/bulk', ['ids' => [$b->id], 'action' => 'author', 'author_id' => $other->id])->assertOk();
        $this->assertSame($other->id, $b->fresh()->author_id);
        $this->assertGreaterThan($version, $b->fresh()->content_version);

        $this->actingAs($admin)->postJson('/api/admin/books/bulk', ['ids' => [$a->id, $b->id], 'action' => 'delete'])->assertOk();
        $this->assertSame([$c->id], Book::pluck('id')->all());
    }

    public function test_series_can_be_unpublished_together(): void
    {
        $series = [$this->series('a'), $this->series('b')];
        collect($series)->each->update(['is_published' => true]);

        $this->actingAs($this->admin())->postJson('/api/admin/dars-series/bulk', ['ids' => collect($series)->pluck('id'), 'action' => 'unpublish'])->assertOk();

        $this->assertSame(0, DarsSeries::where('is_published', true)->count());
    }

    public function test_bulk_dars_delete_only_touches_that_series(): void
    {
        $first = $this->series('a');
        $second = $this->series('b');
        $keep = $first->durus()->create(['number' => 1, 'title_bn' => '১', 'media_type' => 'video', 'media_url' => 'https://example.com/1.mp4']);
        $drop = $first->durus()->create(['number' => 2, 'title_bn' => '২', 'media_type' => 'video', 'media_url' => 'https://example.com/2.mp4']);
        $elsewhere = $second->durus()->create(['number' => 1, 'title_bn' => '১', 'media_type' => 'video', 'media_url' => 'https://example.com/3.mp4']);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/dars-series/{$first->id}/durus/bulk", ['ids' => [$drop->id, $elsewhere->id], 'action' => 'delete'])
            ->assertOk()
            ->assertJsonPath('affected', 1);

        $this->assertNotNull($keep->fresh());
        $this->assertNull($drop->fresh());
        $this->assertNotNull($elsewhere->fresh());
    }

    public function test_bulk_actions_need_an_admin_and_a_valid_action(): void
    {
        $book = $this->book('a');

        $this->actingAs(User::factory()->create())->postJson('/api/admin/books/bulk', ['ids' => [$book->id], 'action' => 'publish'])->assertForbidden();
        $this->actingAs($this->admin())->postJson('/api/admin/books/bulk', ['ids' => [$book->id], 'action' => 'author'])->assertJsonValidationErrors('author_id');
        $this->actingAs($this->admin())->postJson('/api/admin/books/bulk', ['ids' => [], 'action' => 'shred'])->assertJsonValidationErrors(['ids', 'action']);
    }
}
