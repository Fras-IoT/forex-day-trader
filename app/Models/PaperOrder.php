<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PaperOrder extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'orders';

    protected $fillable = [
        'trade_plan_id',
        'pair',
        'side',
        'order_type',
        'lots',
        'units',
        'pip_value_usd',
        'entry_price',
        'stop_loss',
        'take_profit',
        'max_loss_usd',
        'reward_risk',
        'risk_status',
        'risk_checks',
        'fingerprint',
        'paper',
        'status',
        'close_price',
        'pnl_usd',
        'pnl_pips',
        'executed_at',
        'closed_at',
    ];
}

