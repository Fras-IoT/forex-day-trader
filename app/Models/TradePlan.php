<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TradePlan extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'trade_plans';

    protected $fillable = [
        'pair',
        'yahoo_symbol',
        'side',
        'setup',
        'entry_price',
        'stop_loss',
        'take_profit',
        'stop_rule',
        'target_rule',
        'risk_pips',
        'reward_pips',
        'reward_risk',
        'flat_by',
        'status',
        'ai_analysis',
    ];
}

