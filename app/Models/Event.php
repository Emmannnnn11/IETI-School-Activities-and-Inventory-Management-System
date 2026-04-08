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
        'start_date',
        'end_date',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'department',
        'status',
        'rejection_reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
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
        // end_datetime (effective_end_date + end_time) must be strictly greater than now.
        // For legacy records without start/end_date, fall back to event_date.
        return $query->where(function ($outer) use ($today, $nowTime) {
            // New multi-day events (end_date not null)
            $outer->where(function ($q) use ($today, $nowTime) {
                $q->whereNotNull('end_date')
                  ->where(function ($q2) use ($today, $nowTime) {
                      $q2->where('end_date', '>', $today)
                         ->orWhere(function ($q3) use ($today, $nowTime) {
                             $q3->where('end_date', $today)
                                ->where('end_time', '>', $nowTime);
                         });
                  });
            })
            // Legacy single-day events (end_date null)
            ->orWhere(function ($q) use ($today, $nowTime) {
                $q->whereNull('end_date')
                  ->where(function ($q2) use ($today, $nowTime) {
                      $q2->where('event_date', '>', $today)
                         ->orWhere(function ($q3) use ($today, $nowTime) {
                             $q3->where('event_date', $today)
                                ->where('end_time', '>', $nowTime);
                         });
                  });
            });
        });
    }

    /**
     * Scope for past events
     */
    public function scopePast($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($outer) use ($today) {
            $outer->where(function ($q) use ($today) {
                $q->whereNotNull('end_date')
                  ->where('end_date', '<', $today);
            })->orWhere(function ($q) use ($today) {
                $q->whereNull('end_date')
                  ->where('event_date', '<', $today);
            });
        });
    }

    /**
     * Accessor for effective start date (falls back to event_date for legacy records).
     */
    public function getEffectiveStartDateAttribute()
    {
        return $this->start_date ?? $this->event_date;
    }

    /**
     * Accessor for effective end date (falls back to event_date for legacy records).
     */
    public function getEffectiveEndDateAttribute()
    {
        return $this->end_date ?? $this->event_date;
    }

    /**
     * Human-friendly date range label (e.g., "Mar 1–3, 2026" or single date).
     */
    public function getDateRangeLabelAttribute(): string
    {
        $start = $this->effective_start_date;
        $end = $this->effective_end_date;

        if (!$start || !$end) {
            return '';
        }

        if ($start->equalTo($end)) {
            return $start->format('M d, Y');
        }

        if ($start->format('Y') === $end->format('Y')) {
            if ($start->format('m') === $end->format('m')) {
                // Same month/year: "Mar 01–03, 2026"
                return $start->format('M d') . '–' . $end->format('d, Y');
            }

            // Same year, different month: "Mar 30 – Apr 02, 2026"
            return $start->format('M d') . ' – ' . $end->format('M d, Y');
        }

        // Different years: "Dec 30, 2025 – Jan 02, 2026"
        return $start->format('M d, Y') . ' – ' . $end->format('M d, Y');
    }

    /**
     * Human-readable scheduler role for UI display.
     */
    public function getSchedulerRoleLabelAttribute(): string
    {
        $role = $this->creator?->role;
        if (trim((string) $role) !== '') {
            return ucwords(str_replace('_', ' ', (string) $role));
        }

        // Backward-compatible fallback for legacy records with no creator role available.
        $department = trim((string) $this->department);
        if ($department !== '') {
            return ucwords(str_replace('_', ' ', $department));
        }

        return 'N/A';
    }
}
