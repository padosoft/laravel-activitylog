<?php
/**
 * Copyright (c) Padosoft.com 2018.
 */

/**
 * Copyright (c) https://laracasts.com/@phildawson
 */

namespace Spatie\Activitylog\Traits;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait LogsActivityWithRelations
{
    use RelationshipsTrait;
    use LogsActivity {
        LogsActivity::attributeValuesToBeLogged as attributeValuesToBeLoggedBase;
    }

    /*protected static function bootCustomLogsActivity(){
        self::bootLogsActivity();
    }*/

    public function attributeValuesToBeLogged(string $processingEvent): array
    {
        $properties = $this->attributeValuesToBeLoggedBase($processingEvent);

        $properties = $this->setRelationsToBeLogged($properties);

        return $properties;
    }

    public function getAllRelatedActivites()
    {
        $model = ActivitylogServiceProvider::getActivityModelInstance();

        return $model->allRelations($this)->get();
    }

    /**
     * @param $properties
     *
     * @return mixed
     */
    public function setRelationsToBeLogged($properties)
    {
        $relationships = $this->getModelRelations();

        foreach ($relationships as $key => $relationship) {
            if (($relationship['type'] == 'BelongsTo' || $relationship['type'] == 'MorphTo') && $relationship['foreignKey'] !== '') {
                $key = $relationship['foreignKey'] . '.' . $relationship['foreignTable'];
                $foreignKey = $relationship['foreignKey'];
                $properties['relations'][$key] = (string)$this->$foreignKey;
                if (isset($properties['old'][$foreignKey])) {
                    $key = $relationship['foreignKey'] . '_old.' . $relationship['foreignTable'];
                    $properties['relations'][$key] = (string)$properties['old'][$foreignKey];
                }
            }
        }

        return $properties;
    }
}
