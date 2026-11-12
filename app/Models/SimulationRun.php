<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SimulationRun extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'simulations';

    protected $fillable = [
        'trade_plan_id',
        'pair',
        'side',
        'entry_price',
        'stop_loss',
        'take_profit',
        'replays',
        'seed',
        'sessions_used',
        'target_first_pct',
        'stop_first_pct',
        'neither_pct',
        'green_pct',
        'expected_r',
        'breakeven_win_pct',
        'verdict',
        'percentiles',
        'fan_series',
        'sample_paths',
        'histogram',
        'history_stats',
        'ai_verdict',
    ];
}

