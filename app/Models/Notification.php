<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 
        'related_id', 
        'related_type', 
        'type', 
        'message', 
        'is_read', 
        'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    // Generate unique ID
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Polymorphic relationship
    public function related()
    {
        return $this->morphTo();
    }

    // Scope for unread notifications
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // Mark notification as read
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    // Static method to create notification
    public static function createNotification(
        $userId, 
        $relatedModel = null, 
        $type = 'general', 
        $message = ''
    ) {
        return self::create([
            'user_id' => $userId,
            'related_id' => $relatedModel ? $relatedModel->id : null,
            'related_type' => $relatedModel ? get_class($relatedModel) : null,
            'type' => $type,
            'message' => $message
        ]);
    }
}