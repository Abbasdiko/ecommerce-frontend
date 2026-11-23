<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'blog.manage');

        $posts = BlogPost::query()
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%$s%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($posts);
    }

    public function show(Request $request, BlogPost $blog)
    {
        $this->authorizePermission($request, 'blog.manage');

        return $this->ok($blog);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'blog.manage');

        $post = BlogPost::create($this->validated($request) + ['author_id' => $request->user()->id]);
        AuditLogger::record('blog.created', $post);

        return $this->created($post, 'Post created');
    }

    public function update(Request $request, BlogPost $blog)
    {
        $this->authorizePermission($request, 'blog.manage');

        $blog->update($this->validated($request, true));
        AuditLogger::record('blog.updated', $blog);

        return $this->ok($blog->fresh(), 'Post updated');
    }

    public function destroy(Request $request, BlogPost $blog)
    {
        $this->authorizePermission($request, 'blog.manage');
        $blog->delete();
        AuditLogger::record('blog.deleted', $blog);

        return $this->ok(null, 'Post deleted');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$rule, 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('blog_posts', 'slug')->ignore($request->route('blog'))],
            'category' => ['nullable', 'string', 'max:60'],
            'author' => ['nullable', 'string', 'max:120'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => [$rule, 'string'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
