<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;
use MongoDB\BSON\ObjectId;
use App\Models\User;
use App\Models\Department;
use App\Models\UserSignature;

/**
 * Draft Model - MongoDB Collection for Unfinished Memos
 * 
 * Schema Design:
 * {
 *   _id: ObjectId,
 *   creatorId: ObjectId,           // REQUIRED: Reference to user who created the draft
 *   subject: String,               // Memo subject/title
 *   message: String,               // Memo content/body
 *   priority: String,              // 'high', 'medium', 'low'
 *   recipientIds: [ObjectId],      // Array of recipient user IDs
 *   departmentId: ObjectId,        // Target department (for department-wide memos)
 *   attachments: [Object],         // Array of attachment metadata
 *   signatureId: ObjectId,         // Reference to user signature
 *   attachmentPath: String,        // Path to attachment files
 *   scheduledSendAt: Date,         // Scheduled send date (if any)
 *   scheduleEndAt: Date,           // Schedule end date (if any)
 *   allDayEvent: Boolean,          // Is this an all-day event
 *   metadata: {
 *     lastEditedAt: Date,
 *     editCount: Number,
 *     autoSavedAt: Date,
 *     clientVersion: String,
 *     deviceInfo: String
 *   },
 *   status: String,                // 'draft', 'auto_saved'
 *   version: Number,               // Draft version for tracking changes
 *   createdAt: Date,
 *   updatedAt: Date,
 *   deletedAt: Date                // Soft delete support
 * }
 * 
 * Indexes:
 * - creatorId (for filtering by user)
 * - { creatorId: 1, status: 1 } (compound for active drafts)
 * - { creatorId: 1, updatedAt: -1 } (for recent drafts listing)
 * - createdAt (for cleanup queries)
 */
class Draft extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The connection name for the model.
     * Uses MongoDB connection
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     */
    protected $collection = 'drafts';

    /**
     * Map MongoDB camelCase timestamps to Eloquent expectations
     */
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'creatorId',           // User who created the draft (REQUIRED)
        'subject',             // Memo subject
        'message',             // Memo content
        'priority',            // high, medium, low
        'recipientIds',        // Array of recipient IDs
        'departmentId',        // Target department ID
        'attachments',         // Attachment metadata
        'signatureId',         // User signature reference
        'attachmentPath',      // File path for attachments
        'scheduledSendAt',     // Scheduled send timestamp
        'scheduleEndAt',       // Schedule end timestamp
        'allDayEvent',         // All-day event flag
        'metadata',            // Additional metadata object
        'status',              // draft, auto_saved
        'version',             // Version number
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'allDayEvent' => 'boolean',
        'version' => 'integer',
        'scheduledSendAt' => 'datetime',
        'scheduleEndAt' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'deletedAt',
    ];

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'status' => 'draft',
        'version' => 1,
        'priority' => 'medium',
        'recipientIds' => [],
        'attachments' => [],
        'allDayEvent' => false,
        'metadata' => [
            'editCount' => 0,
        ],
    ];

    /**
     * Relationship: Creator of the draft (aliased as 'sender' for UI consistency)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'creatorId');
    }

    /**
     * Alias for sender (legacy support if needed)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creatorId');
    }

    /**
     * Accessor for created_at (UI expects snake_case)
     */
    public function getCreatedAtAttribute()
    {
        return $this->{self::CREATED_AT};
    }

    /**
     * Accessor for updated_at (UI expects snake_case)
     */
    public function getUpdatedAtAttribute()
    {
        return $this->{self::UPDATED_AT};
    }

    /**
     * Relationship: Target department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'departmentId');
    }

    /**
     * Relationship: User signature
     */
    public function signature()
    {
        return $this->belongsTo(UserSignature::class, 'signatureId');
    }

    /**
     * Relationship: Intended recipients
     */
    public function recipients()
    {
        if (empty($this->recipientIds)) {
            return collect([]);
        }
        return User::whereIn('_id', $this->recipientIds)->get();
    }

    /**
     * Scope: Filter by creator ID
     * SECURITY: This is the primary filter to ensure users only see their own drafts
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|ObjectId $creatorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCreator($query, $creatorId)
    {
        $normalizedId = $this->normalizeUserId($creatorId);
        return $query->where('creatorId', $normalizedId);
    }

    /**
     * Scope: Get only active drafts (not soft-deleted)
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'deleted');
    }

    /**
     * Scope: Order by most recently updated
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('updatedAt', 'desc');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Convert user ID to consistent format for MongoDB comparison
     * 
     * @param mixed $userId
     * @return ObjectId|string
     */
    protected function normalizeUserId($userId)
    {
        if ($userId instanceof ObjectId) {
            return $userId;
        }
        
        // Handle string ObjectId (24 character hex)
        if (is_string($userId) && strlen($userId) === 24 && ctype_xdigit($userId)) {
            try {
                return new ObjectId($userId);
            } catch (\Exception $e) {
                return (string) $userId;
            }
        }
        
        return (string) $userId;
    }

    /**
     * Update metadata when draft is modified
     */
    public function touchMetadata()
    {
        $metadata = $this->metadata ?? [];
        $metadata['lastEditedAt'] = now()->toIso8601String();
        $metadata['editCount'] = ($metadata['editCount'] ?? 0) + 1;
        
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Auto-save draft with timestamp
     */
    public function markAutoSaved($clientVersion = null, $deviceInfo = null)
    {
        $metadata = $this->metadata ?? [];
        $metadata['autoSavedAt'] = now()->toIso8601String();
        
        if ($clientVersion) {
            $metadata['clientVersion'] = $clientVersion;
        }
        
        if ($deviceInfo) {
            $metadata['deviceInfo'] = $deviceInfo;
        }
        
        $this->metadata = $metadata;
        $this->status = 'auto_saved';
        $this->save();
    }

    /**
     * Convert draft to memo data for submission
     */
    public function toMemoData()
    {
        return [
            'subject' => $this->subject,
            'message' => $this->message,
            'priority' => $this->priority,
            'recipient_ids' => $this->recipientIds,
            'department_id' => $this->departmentId,
            'attachments' => $this->attachments,
            'signature_id' => $this->signatureId,
            'attachment_path' => $this->attachmentPath,
            'scheduled_send_at' => $this->scheduledSendAt,
            'schedule_end_at' => $this->scheduleEndAt,
            'all_day_event' => $this->allDayEvent,
        ];
    }

    /**
     * Create indexes for the drafts collection
     * Run this via artisan command or migration
     */
    public static function createIndexes()
    {
        $collection = self::raw();
        
        // Index on creatorId for user-specific queries
        $collection->createIndex(['creatorId' => 1], [
            'name' => 'creatorId_index',
            'background' => true
        ]);
        
        // Compound index for active drafts by creator
        $collection->createIndex(['creatorId' => 1, 'status' => 1], [
            'name' => 'creator_status_index',
            'background' => true
        ]);
        
        // Compound index for recent drafts listing
        $collection->createIndex(['creatorId' => 1, 'updatedAt' => -1], [
            'name' => 'creator_recent_index',
            'background' => true
        ]);
        
        // Index for cleanup queries
        $collection->createIndex(['createdAt' => 1], [
            'name' => 'created_index',
            'background' => true,
            'expireAfterSeconds' => 2592000 // 30 days TTL for old drafts
        ]);
    }
}
