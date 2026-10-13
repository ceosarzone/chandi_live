<?php

namespace App\Models;

class BlogPost extends Record
{
    protected $table = 'blog_posts';

    protected $casts = ['published_at' => 'datetime'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
