<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Book extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'author',
        'publisher',
        'release_date',
        'description',
        'isbn',
        'genre',
        'pages',
        'cover',
        'pages_read',
    ];

    protected $casts = [
        'release_date' => 'date',
        'pages' => 'integer',
        'pages_read' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
