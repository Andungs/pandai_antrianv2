<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Queue extends Model
{
    protected $fillable = [
        'service_id',
        'counter_id',
        'date',
        'queue_number',
        'status',
        'visitor_photo_path',
        'called_at',
        'served_at',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'called_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    /* ── Relationships ─────────────────────────── */

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    /* ── Scopes ─────────────────────────────────── */

    /**
     * Filter hanya antrean hari ini (logika reset harian).
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->where('date', now()->toDateString());
    }

    /**
     * Filter berdasarkan status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Antrean yang menunggu hari ini untuk service tertentu (atau beberapa service).
     */
    public function scopeWaitingFor(Builder $query, int|array $serviceIds): Builder
    {
        $serviceIds = (array) $serviceIds;

        return $query->today()
            ->whereIn('service_id', $serviceIds)
            ->where('status', 'menunggu')
            ->orderBy('id');
    }
}
