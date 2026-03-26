<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'status',
        'rejection_reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the user who created this event
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this event
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the items requested for this event
     */
    public function eventItems()
    {
        return $this->hasMany(EventItem::class);
    }

    /**
     * Get history records for this event.
     */
    public function histories()
    {
        return $this->hasMany(EventHistory::class);
    }

    /**
     * Get the status color for display
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'approved' => '#a3b18a',
            'pending' => 'orange',
            'rejected' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get the status badge class for display
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'approved' => 'badge-success',
            'pending' => 'badge-warning',
            'rejected' => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Scope for approved events
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for pending events
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for rejected events
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for future events (not past)
     */
    public function scopeFuture($query)
    {
        $today = now()->toDateString();
        $nowTime = now()->format('H:i:s');

        // "Future" means the event is still active/upcoming:
        // end_datetime (event_date + end_time) must be strictly greater than now.
        return $query->where(function ($q) use ($today, $nowTime) {
            $q->where('event_date', '>', $today)
              ->orWhere(function ($q2) use ($today, $nowTime) {
                  $q2->where('event_date', $today)
                     ->where('end_time', '>', $nowTime);
              });
        });
    }

    /**
     * Scope for past events
     */
    public function scopePast($query)
    {
        return $query->where('event_date', '<', now()->toDateString());
    }
}
