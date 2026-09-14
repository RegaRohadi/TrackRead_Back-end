<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Book extends Model
{
    public const STATUS_TO_READ = 'to_read';
    public const STATUS_CURRENTLY_READING = 'currently_reading';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_DROPPED = 'dropped';

    public const STATUSES = [
        self::STATUS_TO_READ,
        self::STATUS_CURRENTLY_READING,
        self::STATUS_FINISHED,
        self::STATUS_DROPPED,
    ];

    public const SOURCE_PLATFORM = 'platform';
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCES = [self::SOURCE_PLATFORM, self::SOURCE_UPLOAD];

    protected $fillable = [
        'user_id',
        'name',
        'author',
        'publisher',
        'release_date',
        'description',
        'genre',
        'pages',
        'cover',
        'pages_read',
        'status',
        'source',
        'pdf_path',
        'pdf_original_name',
        'pdf_mime',
        'pdf_size_bytes',
        'pdf_total_pages',
    ];

    protected $casts = [
        'release_date' => 'date',
        'pages' => 'integer',
        'pages_read' => 'integer',
        'pdf_size_bytes' => 'integer',
        'pdf_total_pages' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readingProgress()
    {
        return $this->hasMany(\App\Models\ReadingProgress::class);
    }
}
