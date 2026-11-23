<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Service;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    use ApiResponse;

    public function blog(Request $request)
    {
        $posts = BlogPost::published()
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->latest('published_at')
            ->paginate((int) min(24, (int) $request->input('per_page', 9)));

        $posts->getCollection()->transform(fn (BlogPost $p) => $this->card($p));

        return $this->ok([
            'posts' => $posts,
            'categories' => BlogPost::published()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function blogPost(string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        return $this->ok([
            'post' => array_merge($this->card($post), ['body' => $post->paragraphs()]),
            'related' => BlogPost::published()->where('id', '!=', $post->id)
                ->where('category', $post->category)->latest('published_at')->take(3)->get()
                ->map(fn ($p) => $this->card($p)),
        ]);
    }

    public function services()
    {
        return $this->ok(
            Service::where('status', 'active')->with('packages:id,service_id,name,price,listing_limit,commission_rate,duration_days')
                ->orderBy('name')->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'tagline' => $s->tagline ?? $s->description,
                    'icon' => $s->icon ?? 'Wrench',
                    'packages' => $s->packages->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => (float) $p->price,
                        'listing_limit' => $p->listing_limit,
                        'commission_rate' => (float) $p->commission_rate,
                        'duration_days' => $p->duration_days,
                    ]),
                ])
        );
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        $sub = NewsletterSubscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            [
                'name' => $data['name'] ?? null,
                'source' => $data['source'] ?? 'storefront',
                'status' => 'subscribed',
                'confirmed_at' => now(),
            ]
        );

        return $this->ok(['id' => $sub->id], 'You are subscribed — watch your inbox for deals.');
    }

    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        ContactMessage::create($data + [
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        return $this->created(null, 'Thanks for reaching out — our team will reply within one business day.');
    }

    private function card(BlogPost $p): array
    {
        return [
            'id' => (string) $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'category' => $p->category,
            'author' => $p->author,
            'excerpt' => $p->excerpt ?? \Illuminate\Support\Str::limit(strip_tags((string) $p->body), 160),
            'image' => $p->cover_image ?: 'https://picsum.photos/seed/blog' . $p->id . '/800/500',
            'published_at' => optional($p->published_at)->toIso8601String(),
            'read_minutes' => $p->read_minutes,
            'readMinutes' => $p->read_minutes,
        ];
    }
}
