<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AiInteraction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_interactions';

    protected $fillable = [
        'interaction_id',
        'previous_interaction_id',
        'pair',
        'role',
        'content',
        'model',
    ];
}

