<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'action',
        'title',
        'status',
        'event_date',
        'event_data',
        'reason',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_data' => 'array',
        'performed_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action.
     */
    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

