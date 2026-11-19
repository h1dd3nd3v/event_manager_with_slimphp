<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoyalHonoreesModel extends Model
{
    protected $table = 'royal_honorees';

    protected $fillable = [
        'name',
        'titles',
        'bio',
        'profile_image_url',
        'social_links',
    ];

    protected $casts = [
        'titles' => 'array',
        'social_links' => 'array',
    ];
}
