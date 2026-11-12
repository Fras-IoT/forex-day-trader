<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ScanResult extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'scans';

    protected $fillable = [
        'mode',
        'session',
        'pairs_scanned',
        'hits',
        'data_source',
        'ai_summary',
    ];
}

