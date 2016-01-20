<?php namespace igaster\TranslateEloquent;

use Illuminate\Database\Eloquent\Collection;

trait TranslationTrait{
    public $translations = [];

    public static function isTranslatable($key){
        return isset(self::$translatable) && in_array($key, self::$translatable);
    }

    public function getTranslationId($key){
        if(!self::isTranslatable($key))
            throw new Exceptions\KeyNotTranslatable($key);

        if(array_key_exists($key, $this->attributes))
            return $this->attributes[$key];
        else
            return null;
    }

    public function translations($key){
        $group_id = $this->getTranslationId($key);
        if(!array_key_exists($group_id, $this->translations)){
            $translations = new Translations($group_id);
            $group_id = $translations->group_id;

            $this->translations[$group_id] = $translations;
            $this->attributes[$key] = $group_id;
        }
        return $this->translations[$group_id];
    }

    //---------------[ Locale Helpers ]------------------

    protected $locale = null;
    protected $fallback_locale = null;
    public function translate($locale, $fallback_locale = null){
        $this->locale = $locale;
        $this->fallback_locale = $fallback_locale;
        return $this;
    }

    protected function translation_locale(){
        return $this->locale ?: \App::getLocale();
    }

    protected function translation_fallback(){
        return $this->fallback_locale ?: \Config::get('app.fallback_locale');
    }

    //---------------[ eager Loading ]------------------

    private function rebuildModelFromQuery($model){

        // Recontsruct the Translation object
        $translation = new Translation([
            'id'        => $model->tr_id,
            'value'     => $model->tr_value,
            'locale'    => $model->tr_locale,
            'group_id'  => $model->tr_group_id,
        ]);
        $translation->exists = true;

        // Atatch the Translation to the Model
        $group_id = $model->tr_group_id;
        $translations = new Translations($group_id);
        $translations->attach($translation);
        $model->translations[$group_id] = $translations;

        // Clean Model from injected translation keys
        foreach (['tr_id', 'tr_value', 'tr_locale', 'tr_group_id'] as $key) {
            unset($model->attributes[$key]);
            unset($model->original[$key]);
        }

        return $model;        
    }

    public function scopeFirstWithTranslation($query, $key = null,$locale = null) {
        
        $locale = $locale ?: $this->translation_locale();
        $key = $key ?: reset(static::$translatable);

        // Join model with translations to reduce queries
        $model = $query->leftJoin('translations', function ($join) use ($key, $locale) {
            $join->on($this->getTable().".$key", '=', 'translations.group_id')
                 ->on('translations.locale' , '=', $locale);
        })->first([
            $this->getTable().'.*',
            'translations.id as tr_id',
            'translations.value as tr_value',
            'translations.locale as tr_locale',
            'translations.group_id as tr_group_id',
        ]);

        $model = $this->rebuildModelFromQuery($model);

        return $model;
    }

    public function scopeFindWithTranslation($query, $id, $key = null, $locale = null) {
        return $query->where($this->getTable().'.id', '=', $id)->firstWithTranslation($key, $locale);
    }

    public function scopeGetWithTranslation($query, $key = null,$locale = null) {
        
        $locale = $locale ?: $this->translation_locale();
        $key = $key ?: reset(static::$translatable);

        // Join model with translations to reduce queries
        $models = $query->leftJoin('translations', function ($join) use ($key, $locale) {
            $join->on($this->getTable().".$key", '=', 'translations.group_id')
                 ->on('translations.locale' , '=', $locale);
        })->get([
            $this->getTable().'.*',
            'translations.id as tr_id',
            'translations.value as tr_value',
            'translations.locale as tr_locale',
            'translations.group_id as tr_group_id',
        ]);

        $items = [];
        foreach ($models as $model) {
            $items[] = $this->rebuildModelFromQuery($model);
        }
        return new Collection($items);
    }


    //-------------------------------------------------

    protected $translatable_handled;

    protected function translatable_get($key){
        $this->translatable_handled=false;

        if(self::isTranslatable($key)){
            $this->translatable_handled=true;
            $translations = $this->translations($key);
            $result = $translations->in($this->translation_locale(), $this->translation_fallback());
            $this->translate(null, null);
            return $result;
        }
    }

    protected function translatable_set($key, $value){
        $this->translatable_handled=false;

        if(self::isTranslatable($key)){
            $translations = $this->translations($key);
            if (is_array($value)){
                $translations->set($value);
            } else {            
                $translations->set($this->translation_locale(), $value);
            }
            $this->attributes[$key] = $translations->group_id;
            $this->translate(null, null);
            $this->translatable_handled=true;
        }
    }

    //--- copy these in your model if you need to implement __get() __set() methods

    public function __get($key) {
        // Handle Translatable keys
        $result=$this->translatable_get($key);
        if ($this->translatable_handled)
            return $result;

        //your code goes here
        
        return parent::__get($key);
    }

    public function __set($key, $value) {
        // Handle Translatable keys
        $this->translatable_set($key, $value);
        if ($this->translatable_handled)
            return;

        //your code goes here

        parent::__set($key, $value);
    } 

    //-------------------------------------------------

    public function __isset($key) {
        return (self::isTranslatable($key)  || parent::__isset($key));
    }

    public static function create(array $attributes = [])
    {
        $translations = [];
        foreach ($attributes as $key => $value) {
            if(self::isTranslatable($key)) {
                $translations[$key] = $value;
                $attributes[$key] = null;
            }
        }
        $model = new static($attributes);

        foreach ($translations as $key => $value) {
            $model->translatable_set($key, $value);
        }

        $model->save();
        return $model;
    }

    public function update(array $attributes = [], array $options = [])
    {
        $translations = [];
        foreach ($attributes as $key => $value) {
            if(self::isTranslatable($key)) {
                $translations[$key] = $value;
                $attributes[$key] = null;
            }
        }
        parent::update($attributes, $options);

        foreach ($translations as $key => $value) {
            $this->translatable_set($key, $value);
        }
        $this->save();
        return $this;
    }

    public static function boot()
    {
        self::deleting(function ($model) {
            foreach (self::$translatable as $key) {
                Translation::where('group_id',$model->attributes[$key])->delete();
            }
        });
    }

}