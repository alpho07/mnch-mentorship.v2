<?php

namespace App\Models;

use App\Models\Concerns\HasFileManagement;
use App\Models\Concerns\HasResourceAnalytics;
use App\Models\Concerns\HasAccessControl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resource extends Model {

    use HasFactory,
        SoftDeletes,
        HasFileManagement,
        HasResourceAnalytics,
        HasAccessControl,
        LogsActivity;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'meta_description',
        'featured_image', 'resource_type_id', 'category_id', 'author_id',
        'status', 'visibility', 'is_featured', 'is_downloadable',
        'published_at', 'file_path', 'file_size', 'file_type',
        'external_url', 'duration', 'difficulty_level', 'prerequisites',
        'learning_outcomes', 'sort_order', 'view_count', 'download_count',
        'like_count', 'dislike_count'
    ];
    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_downloadable' => 'boolean',
        'prerequisites' => 'array',
        'learning_outcomes' => 'array',
        'file_size' => 'integer',
    ];
    protected $with = ['resourceType', 'category', 'author'];
    protected $appends = [
        'file_url', 'thumbnail_url', 'formatted_file_size', 'read_time',
        'interaction_like_count', 'interaction_dislike_count',
        'bookmark_count', 'comment_count'
    ];

    protected static function boot() {
        parent::boot();

        static::creating(function ($model) {
            // Ensure original_name is set
            if (empty($model->original_name) && !empty($model->file_name)) {
                $model->original_name = $model->file_name;
            }
        });
    }

    // Spatie Activity Log Configuration
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()
                        ->logOnly(['title', 'status', 'visibility', 'is_featured', 'excerpt'])
                        ->logOnlyDirty()
                        ->dontSubmitEmptyLogs()
                        ->setDescriptionForEvent(fn(string $eventName) => "Resource was {$eventName}")
                        ->useLogName('resource');
    }

    // Custom activity log descriptions
    public function getDescriptionForEvent(string $eventName): string {
        return match ($eventName) {
            'created' => "Resource '{$this->title}' was created",
            'updated' => "Resource '{$this->title}' was updated",
            'deleted' => "Resource '{$this->title}' was deleted",
            'published' => "Resource '{$this->title}' was published",
            'unpublished' => "Resource '{$this->title}' was unpublished",
            default => "Resource '{$this->title}' was {$eventName}",
        };
    }

    // === RELATIONSHIPS ===
    public function resourceType(): BelongsTo {
        return $this->belongsTo(ResourceType::class);
    }

    public function category(): BelongsTo {
        return $this->belongsTo(ResourceCategory::class);
    }

    public function author(): BelongsTo {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany {
        return $this->belongsToMany(Tag::class, 'resource_tags');
    }

    public function comments(): HasMany {
        return $this->hasMany(ResourceComment::class);
    }

    public function interactions(): HasMany {
        return $this->hasMany(ResourceInteraction::class);
    }

    public function views(): HasMany {
        return $this->hasMany(ResourceView::class);
    }

    public function downloads(): HasMany {
        return $this->hasMany(ResourceDownload::class);
    }

    public function accessGroups(): BelongsToMany {
        return $this->belongsToMany(AccessGroup::class, 'resource_access_groups');
    }

    public function scopedFacilities(): BelongsToMany {
        return $this->belongsToMany(Facility::class, 'resource_facilities');
    }

    public function scopedCounties(): BelongsToMany {
        return $this->belongsToMany(County::class, 'resource_counties');
    }

    public function scopedDepartments(): BelongsToMany {
        return $this->belongsToMany(Department::class, 'resource_departments');
    }

    public function programModules(): BelongsToMany {
        return $this->belongsToMany(ProgramModule::class, 'resource_program_modules');
    }

    // === QUERY SCOPES ===
    public function scopePublished(Builder $query): Builder {
        return $query->where('status', 'published')
                        ->where('published_at', '<=', now());
    }

    public function scopePublic(Builder $query): Builder {
        return $query->where('visibility', 'public');
    }

    public function scopeByType(Builder $query, $type): Builder {
        return $query->whereHas('resourceType', fn($q) => $q->where('slug', $type));
    }

    public function scopeByCategory(Builder $query, $categoryId): Builder {
        return $query->where('category_id', $categoryId);
    }

    public function scopeFeatured(Builder $query): Builder {
        return $query->where('is_featured', true);
    }

    public function scopePopular(Builder $query, int $days = 30): Builder {
        return $query->withCount(['views' => fn($q) => $q->where('created_at', '>=', now()->subDays($days))])
                        ->orderByDesc('views_count');
    }

    public function scopeSearch(Builder $query, string $search): Builder {
        return $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                            ->orWhere('excerpt', 'like', "%{$search}%")
                            ->orWhere('content', 'like', "%{$search}%")
                            ->orWhereHas('tags', fn($tagQ) => $tagQ->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('category', fn($catQ) => $catQ->where('name', 'like', "%{$search}%"));
                });
    }

    // === CUSTOM ACTIVITY LOG METHODS ===
    public function logStatusChange(string $oldStatus, string $newStatus): void {
        activity()
                ->performedOn($this)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ])
                ->log("Status changed from {$oldStatus} to {$newStatus}");
    }

    public function logFileUpload(string $filename): void {
        activity()
                ->performedOn($this)
                ->causedBy(auth()->user())
                ->withProperties(['filename' => $filename])
                ->log("File uploaded: {$filename}");
    }

    public function logDownload(?User $user = null): void {
        activity()
                ->performedOn($this)
                ->causedBy($user)
                ->log('Resource downloaded');
    }

    public function logView(?User $user = null): void {
        activity()
                ->performedOn($this)
                ->causedBy($user)
                ->log('Resource viewed');
    }

    // === MODEL EVENTS ===
// Update the model events in Resource model
    protected static function booted() {
        // Clean up files when resource is deleted
        static::deleting(function (Resource $resource) {
            $resource->deleteAllFiles();
        });

        // Generate slug automatically
        static::creating(function (Resource $resource) {
            if (empty($resource->slug)) {
                $resource->slug = \Str::slug($resource->title);
            }
        });

        // Handle primary file logic
        static::saved(function (Resource $resource) {
            // Ensure only one primary file per resource
            $primaryFiles = $resource->files()->where('is_primary', true)->get();
            if ($primaryFiles->count() > 1) {
                // Keep the first one, remove primary flag from others
                $primaryFiles->skip(1)->each(function ($file) {
                    $file->update(['is_primary' => false]);
                });
            }
        });
    }

    public function files(): HasMany {
        return $this->hasMany(ResourceFile::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryFile(): HasOne {
        return $this->hasOne(ResourceFile::class)->where('is_primary', true);
    }

// Update file-related methods in Resource model
    public function hasFiles(): bool {
        return $this->files()->exists();
    }

    public function hasPrimaryFile(): bool {
        return $this->primaryFile()->exists();
    }

    public function canBeDownloaded(): bool {
        return $this->is_downloadable &&
                $this->status === 'published' &&
                $this->hasFiles();
    }

    // === ROUTE KEY ===
    public function getRouteKeyName() {
        return 'slug';
    }
}
