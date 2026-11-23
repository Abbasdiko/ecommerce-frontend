<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'category.view');

        if ($request->boolean('tree')) {
            $roots = Category::whereNull('parent_id')
                ->with('children')
                ->when($request->service_id, fn ($q, $id) => $q->where('service_id', $id))
                ->orderBy('sort_order')->get();

            return $this->ok(CategoryResource::collection($roots));
        }

        $categories = Category::query()
            ->with('service:id,name')
            ->withCount('listings')
            ->when($request->service_id, fn ($q, $id) => $q->where('service_id', $id))
            ->when($request->parent_id, fn ($q, $id) => $q->where('parent_id', $id))
            ->orderBy('sort_order')
            ->paginate($this->perPage($request));

        return CategoryResource::collection($categories);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'category.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = Category::create($data);
        AuditLogger::record('category.created', $category);

        return $this->created(new CategoryResource($category), 'Category created');
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizePermission($request, 'category.update');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:categories,id', Rule::notIn([$category->id])],
            'service_id' => ['nullable', 'exists:services,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $category->update($data);
        AuditLogger::record('category.updated', $category);

        return $this->ok(new CategoryResource($category), 'Category updated');
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizePermission($request, 'category.delete');

        if ($category->children()->exists() || $category->listings()->exists()) {
            return $this->fail('Category has child categories or listings attached.', null, 409);
        }

        $category->delete();
        AuditLogger::record('category.deleted', $category);

        return $this->ok(null, 'Category deleted');
    }
}
