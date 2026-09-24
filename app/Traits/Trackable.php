<?php

namespace App\Traits;

use App\Services\ActivityLogger;

trait Trackable
{
    protected static array $_trackingData = [];

    protected array $trackExclude = ['password', 'remember_token', 'updated_at'];

    public function trackExcluded(): array
    {
        return $this->trackExclude;
    }

    public static function bootTrackable(): void
    {
        if (! request()->is('admin/*')) {
            return;
        }

        static::created(function ($model) {
            ActivityLogger::created($model);
        });

        static::updating(function ($model) {
            static::$_trackingData[$model->getKey()] = [
                'oldValues' => $model->getOriginal(),
                'dirtyKeys' => array_keys($model->getDirty()),
            ];
        });

        static::updated(function ($model) {
            $data = static::$_trackingData[$model->getKey()] ?? [];
            $oldValues = $data['oldValues'] ?? [];
            $dirtyKeys = $data['dirtyKeys'] ?? [];

            unset(static::$_trackingData[$model->getKey()]);

            if (empty($dirtyKeys)) {
                return;
            }

            foreach ($model->trackExclude as $field) {
                unset($oldValues[$field]);
                $dirtyKeys = array_values(array_filter($dirtyKeys, fn ($k) => $k !== $field));
            }

            if (empty($dirtyKeys)) {
                return;
            }

            $filteredOld = array_intersect_key($oldValues, array_flip($dirtyKeys));
            $filteredNew = array_intersect_key($model->getAttributes(), array_flip($dirtyKeys));

            ActivityLogger::updated($model, $filteredOld, $filteredNew);
        });

        static::deleted(function ($model) {
            ActivityLogger::deleted($model);
        });
    }
}
