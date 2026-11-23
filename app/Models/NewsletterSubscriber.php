<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'name', 'source', 'status', 'confirmed_at'];

    protected $casts = ['confirmed_at' => 'datetime'];
}
