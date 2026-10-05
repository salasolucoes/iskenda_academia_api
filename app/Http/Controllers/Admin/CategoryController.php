<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Infrastructure\Persistence\Eloquent\Models\Category;
use Infrastructure\Persistence\Eloquent\Models\Course;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::tags(['categories'])->remember('categories.all', 300, function () {
            return CategoryResource::collection(Category::orderBy('name')->get())->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        Cache::tags(['categories'])->flush();

        return response()->json([
            'data' => new CategoryResource($category),
            'message' => 'Categoria criada com sucesso.',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? $category->description,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        Cache::tags(['categories'])->flush();

        return response()->json([
            'data' => new CategoryResource($category),
            'message' => 'Categoria actualizada com sucesso.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if (Course::where('category_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Não é possível eliminar uma categoria com cursos associados.'],
            ]);
        }

        $category->delete();

        Cache::tags(['categories'])->flush();

        return response()->json(['message' => 'Categoria removida com sucesso.']);
    }
}
