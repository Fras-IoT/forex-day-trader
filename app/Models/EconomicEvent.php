<?php

namespace App\Models;

use App\Services\EconomicCalendarService;
use MongoDB\Laravel\Eloquent\Model;

class EconomicEvent extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'economic_events';

    protected $fillable = [
        'event_key',
        'title',
        'country',
        'currency',
        'impact',
        'event_time',
        'event_time_wib',
        'forecast',
        'previous',
        'actual',
        'source',
    ];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    public function isHighImpact(): bool
    {
        return strcasecmp($this->impact ?? '', 'High') === 0;
    }

    public function isMediumImpact(): bool
    {
        return strcasecmp($this->impact ?? '', 'Medium') === 0;
    }

    public function getAffectedPairsAttribute(): array
    {
        return EconomicCalendarService::getAffectedPairs($this->currency ?? $this->country ?? '');
    }

    public function getDescriptionAttribute(): string
    {
        return EconomicCalendarService::getEventDescription($this->title ?? '', $this->currency ?? $this->country ?? '');
    }
}

