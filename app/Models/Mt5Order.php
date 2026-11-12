<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Mt5Order extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'mt5_orders';

    protected $fillable = [
        'pair',
        'mt5_symbol',
        'side',
        'lots',
        'entry_price',
        'stop_loss',
        'take_profit',
        'setup',
        'score',
        'expected_r',
        'target_first_pct',
        'status',
        'telegram_message_id',
        'mt5_ticket',
        'executed_price',
        'error_message',
        'expires_at',
        'approved_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}

