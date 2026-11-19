<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishingModel extends Model
{
    protected $table = 'publishing_entries';

    protected $fillable = [
        'name',
        'filename',
        'url',
        'public_id',
        'resource_type',
        'format',
        'bytes',
        'display_image',
        'display_image_public_id',
    ];

    protected $casts = [
        'bytes' => 'integer',
    ];
}
