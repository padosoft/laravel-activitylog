<?php

namespace Spatie\Activitylog\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;

class Activity extends Model implements ActivityContract
{
    protected $table;

    public $guarded = [];

    protected $casts = [
        'properties' => 'collection',
    ];

    protected static function boot()
    {
        Activity::saving(function ($model) {
            $model->url = $model->resolveUrl();
            $model->user_agent = $model->resolveUserAgent();
            $model->ip = $model->resolveIp();
        });
    }

    public function resolveIp()
    {
        return Request::ip();
    }

    public function resolveUserAgent()
    {
        return Request::header('User-Agent');
    }

    public function resolveUrl()
    {
        if (!App::runningInConsole()) {
            return Request::fullUrlWithQuery([]);
        }
        if (in_array('schedule:run', $_SERVER['argv'])) {
            return 'scheduler';
        }

        return 'console';
    }

    public function __construct(array $attributes = [])
    {
        $this->table = config('activitylog.table_name');

        parent::__construct($attributes);
    }

    public function subject(): MorphTo
    {
        if (config('activitylog.subject_returns_soft_deleted_models')) {
            return $this->morphTo()->withTrashed();
        }

        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the extra properties with the given name.
     *
     * @param string $propertyName
     *
     * @return mixed
     */
    public function getExtraProperty(string $propertyName)
    {
        return array_get($this->properties->toArray(), $propertyName);
    }

    public function changes(): Collection
    {
        if (!$this->properties instanceof Collection) {
            return new Collection();
        }

        return collect(array_filter($this->properties->toArray(), function ($key) {
            return in_array($key, ['attributes', 'old']);
        }, ARRAY_FILTER_USE_KEY));
    }

    public function scopeInLog(Builder $query, ...$logNames): Builder
    {
        if (is_array($logNames[0])) {
            $logNames = $logNames[0];
        }

        return $query->whereIn('log_name', $logNames);
    }

    /**
     * Scope a query to only include activities by a given causer.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $causer
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    /**
     * Scope a query to only include activities for a given subject.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $subject
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeAllRelations(Builder $query, \Illuminate\Database\Eloquent\Model $subject): Builder
    {
        return $query
            ->where(function ($q) use ($subject) {
                $q->where('subject_type', '=', $subject->getMorphClass())
                  ->where('subject_id', $subject->getKey());
            })->orWhere(function ($q) use ($subject) {
                $q->where('causer_type', '=', $subject->getMorphClass())
                  ->where('causer_id', $subject->getKey());
            })->orWhere('properties', 'LIKE', '%' . $subject->getTable() . '":"' . $subject->getKey() . '"%');
    }
}
