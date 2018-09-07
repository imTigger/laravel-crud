<?php

namespace Imtigger\LaravelCRUD;

use Dimsav\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;

trait CRUDTranslatable {

    /**
     * Trigger when store method
     * Override this method to add additinal operations
     *
     * @return Model|Translatable $entity
     */
    protected function storeSave() {
        /** @var Model|Translatable $entity */
        $entity = parent::storeSave();

        // Fill translated attributes
        if (property_exists($this->entityClass, 'translatedAttributes')) {
            foreach ($entity->translatedAttributes As $translatedAttribute) {
                $locales = \Illuminate\Support\Facades\Config::get('translatable.locales');
                foreach ($locales As $locale) {
                    $entity->translateOrNew($locale)->$translatedAttribute = Input::get("{$translatedAttribute}.{$locale}");
                }
            }
        }

        $entity->save();

        return $entity;
    }
    
    /**
     * Trigger when update method
     * Override this method to add additinal operations
     *
     * @param Model|Translatable $entity
     * @return Model|Translatable $entity
     */
    protected function updateSave($entity) {
        $entity = parent::updateSave($entity);

        // Fill translated attributes
        if (property_exists($entity, 'translatedAttributes')) {
            foreach ($entity->translatedAttributes As $translatedAttribute) {
                $locales = \Illuminate\Support\Facades\Config::get('translatable.locales');
                foreach ($locales As $locale) {
                    $entity->translateOrNew($locale)->$translatedAttribute = Input::get("{$translatedAttribute}.{$locale}");
                }
            }
        }

        $entity->save();

        return $entity;
    }
}