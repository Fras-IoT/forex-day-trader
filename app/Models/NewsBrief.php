<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class NewsBrief extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'news_briefs';

    protected $fillable = [
        'pair',
        'headlines',
        'ai_brief',
        'impact_level',
        'bias',
    ];
}

