<?php namespace igaster\TranslateEloquent;

trait TranslationTrait{

    // $this->key (=string)
    public function isTranslatable($key){
        return isset($this->translatable) && in_array($key, $this->translatable);
        // return array_key_exists("_$key", $this->attributes);
    }

    // $this->_key (=Translation)
    public function isTranslation($key){
        return $key[0]=='_' && $this->isTranslatable(substr($key, 1));
        // return array_key_exists("$key", $this->attributes) && $key[0]=='_';
    }

    public function getTranslationKey($key){
        if($this->isTranslatable($key))
            return $key;

        if($this->isTranslation($key))
            return substr($key, 1);

        throw new Exceptions\KeyNotTranslatable($key);
    }

    public function getTranslationId($key){
        $key = $this->getTranslationKey($key);

        if(array_key_exists($key, $this->attributes))
            return $this->attributes[$key];
        else
            return null;

    }

    protected $translations = [];

    public function getTranslations($key){
        $group_id = $this->getTranslationId($key);
        if(!array_key_exists($group_id, $this->translations)){
            $this->translations[$group_id] = new Translations($group_id);
        }
        return $this->translations[$group_id];
    }

    //---------------[Locale Helpers]------------------

    protected function translation_locale(){
        return \App::getLocale();
    }

    protected function translation_fallback(){
        return \Config::get('app.fallback_locale');
    }

    //-------------------------------------------------

    protected $translatable_handled;

    protected function translatable_get($key){
        $this->translatable_handled=false;

        if($this->isTranslation($key)){
            $translations = $this->getTranslations($key);
            $this->translatable_handled=true;
            return $translations;
        }

        if($this->isTranslatable($key)){
            $this->translatable_handled=true;
            $translations = $this->getTranslations($key);
            return $translations->in($this->translation_locale(), $this->translation_fallback());
        }
    }

    protected function translatable_set($key, $value){
        $this->translatable_handled=false;

        if($this->isTranslatable($key)){
            $translations = $this->getTranslations($key);
            $translations->set($this->translation_locale(), $value);
            $this->attributes[$this->getTranslationKey($key)] = $translations->group_id;
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
        return ($this->isTranslation($key) || $this->isTranslatable($key)  || parent::__isset($key));
    }
}