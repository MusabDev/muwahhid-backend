<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the author / category / book references used in import files.
 *
 * A reference is either a slug string (must already exist) or an object with the
 * entity's fields (found by slug, created when missing). In dry-run mode nothing is
 * written; the models that would be created are listed in $created instead.
 */
class ReferenceResolver
{
    /** @var array{authors: list<string>, categories: list<string>} */
    public array $created = ['authors' => [], 'categories' => []];

    public function __construct(public bool $dryRun = false) {}

    /**
     * Validation rules for an author reference at the given key.
     *
     * @return array<string, mixed>
     */
    public static function authorRules(string $key, bool $required = true): array
    {
        return [
            $key => [$required ? 'required' : 'nullable', self::slugOrObject()],
            "{$key}.name_ar" => ['string', 'max:255'],
            "{$key}.name_bn" => ['string', 'max:255'],
            "{$key}.name_translit" => ['string', 'max:255'],
            "{$key}.slug" => ['nullable', 'string', 'max:255', 'alpha_dash'],
            "{$key}.death_year_ah" => ['nullable', 'integer', 'min:1', 'max:1500'],
            "{$key}.about_md" => ['nullable', 'string'],
        ];
    }

    /**
     * Validation rules for a category reference at the given key.
     *
     * @return array<string, mixed>
     */
    public static function categoryRules(string $key): array
    {
        return [
            $key => ['nullable', self::slugOrObject()],
            "{$key}.name_bn" => ['string', 'max:255'],
            "{$key}.name_ar" => ['nullable', 'string', 'max:255'],
            "{$key}.slug" => ['nullable', 'string', 'max:255', 'alpha_dash'],
        ];
    }

    private static function slugOrObject(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) && ! is_array($value)) {
                $fail("{$attribute} must be a slug or an object.");
            }
        };
    }

    public function author(string|array|null $ref, string $key): ?Author
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        if (is_string($ref)) {
            return Author::where('slug', $ref)->first()
                ?? throw ValidationException::withMessages([$key => "No author with slug \"{$ref}\". Use an object with name_ar, name_bn and name_translit to create one."]);
        }

        foreach (['name_ar', 'name_bn', 'name_translit'] as $field) {
            if (blank($ref[$field] ?? null)) {
                throw ValidationException::withMessages(["{$key}.{$field}" => "A new author needs {$field}."]);
            }
        }

        $slug = $ref['slug'] ?? Str::slug($ref['name_translit']);

        if ($author = Author::where('slug', $slug)->first()) {
            return $author;
        }

        $this->created['authors'][] = $ref['name_bn'];

        if ($this->dryRun) {
            return new Author(['slug' => $slug, ...$ref]);
        }

        return Author::create([
            'slug' => $slug,
            'name_ar' => $ref['name_ar'],
            'name_bn' => $ref['name_bn'],
            'name_translit' => $ref['name_translit'],
            'death_year_ah' => $ref['death_year_ah'] ?? null,
            'about_md' => $ref['about_md'] ?? null,
        ]);
    }

    public function category(string|array|null $ref, string $key): ?Category
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        if (is_string($ref)) {
            return Category::where('slug', $ref)->first()
                ?? throw ValidationException::withMessages([$key => "No category with slug \"{$ref}\". Use an object with name_bn to create one."]);
        }

        if (blank($ref['name_bn'] ?? null)) {
            throw ValidationException::withMessages(["{$key}.name_bn" => 'A new category needs name_bn.']);
        }

        $slug = $ref['slug'] ?? (Str::slug($ref['name_bn']) ?: 'category-'.Str::lower(Str::random(6)));

        if ($category = Category::where('slug', $slug)->orWhere('name_bn', $ref['name_bn'])->first()) {
            return $category;
        }

        $this->created['categories'][] = $ref['name_bn'];

        if ($this->dryRun) {
            return new Category(['slug' => $slug, 'name_bn' => $ref['name_bn'], 'name_ar' => $ref['name_ar'] ?? null]);
        }

        return Category::create([
            'slug' => $slug,
            'name_bn' => $ref['name_bn'],
            'name_ar' => $ref['name_ar'] ?? null,
            'position' => (int) Category::max('position') + 1,
        ]);
    }

    public function book(?string $slug, string $key): ?Book
    {
        if (blank($slug)) {
            return null;
        }

        return Book::where('slug', $slug)->first()
            ?? throw ValidationException::withMessages([$key => "No book with slug \"{$slug}\"."]);
    }
}
