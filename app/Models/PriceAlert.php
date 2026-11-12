<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PriceAlert extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'alerts';

    protected $fillable = [
        'pair',
        'condition',
        'target_price',
        'label',
        'trade_plan_id',
        'status',
        'triggered_at',
        'triggered_price',
    ];
}

