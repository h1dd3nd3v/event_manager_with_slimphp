<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventsModel extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'event_name',
        'start_date',
        'end_date',
        'location',
        'event_registration_link',
        'event_created_by',
        'event_updated_by',
        'title_image_link'
    ];
}