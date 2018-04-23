<?php
/**
 * Copyright (c) Padosoft.com 2018.
 */

namespace Spatie\Activitylog\Traits;

use Spatie\Activitylog\ActivitylogServiceProvider;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivityWithRelations
{
    use LogsActivityWithRelations;

    public function actions(): MorphMany
    {
        return $this->morphMany(ActivitylogServiceProvider::determineActivityModel(), 'causer');
    }


}