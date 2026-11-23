<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'author', 'cover_image', 'excerpt', 'body',
        'read_minutes', 'status', 'published_at', 'author_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post) {
            if (! $post->slug) {
                $base = Str::slug($post->title);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $post->slug = $slug;
            }
            $post->read_minutes = max(1, (int) ceil(str_word_count(strip_tags((string) $post->body)) / 200));
            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function paragraphs(): array
    {
        return array_values(array_filter(preg_split('/\n{2,}/', trim((string) $this->body))));
    }
}
