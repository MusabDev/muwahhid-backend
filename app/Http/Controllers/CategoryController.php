<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * All categories in display order, with counts of published books and dars series.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount([
                'books' => fn ($query) => $query->published(),
                'darsSeries' => fn ($query) => $query->published(),
            ])
            ->orderBy('position')
            ->orderBy('name_bn')
            ->get();

        return CategoryResource::collection($categories);
    }
}
