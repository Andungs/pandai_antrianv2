<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'prefix_code',
        'digit_length',
    ];

    /* ── Relationships ─────────────────────────── */

    public function counters(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Counter::class);
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    /* ── Helpers ────────────────────────────────── */

    /**
     * Generate nomor antrean berikutnya untuk hari ini.
     * Contoh: prefix_code = "A", digit_length = 3 → "A001", "A002", ...
     */
    public function generateNextQueueNumber(): string
    {
        $lastQueue = $this->queues()
            ->where('date', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($lastQueue) {
            // Extract angka dari queue_number, misal "A003" → 3
            $lastNumber = (int) substr($lastQueue->queue_number, strlen($this->prefix_code));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $this->prefix_code . str_pad($nextNumber, $this->digit_length, '0', STR_PAD_LEFT);
    }
}
