<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Infrastructure\Persistence\Eloquent\Models\Category;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $categories,
        ]);
    }
}
