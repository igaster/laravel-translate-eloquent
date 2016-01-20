<?php namespace igaster\TranslateEloquent;

class Translations  {

	public $group_id;

	public $translations = [];

	public function __construct($group_id = null){
		$this->group_id = $group_id ?: self::nextGroupId();
	}

	public static function nextGroupId(){
		return Translation::max('group_id') + 1;
	}

	public function get($locale){
		if (!array_key_exists($locale, $this->translations)){
			$this->translations[$locale] = Translation::where('group_id',$this->group_id)->where('locale',$locale)->first();
		}
		return $this->translations[$locale];
	}

	public function has($locale){
		return !empty($this->get($locale));
	}

	public function set($locale, $value=null){
		
		// Array format passed
		if(is_array($locale)) {
			foreach ($locale as $loc => $value) {
				$this->set($loc, $value);
			}
			return;
		}

		// Update an existing translation
		if ($this->has($locale)) {
			$this->get($locale)->update([
				'value'		=> $value,
			]);
		} else { // Create new translation
			$this->translations[$locale]=Translation::create([
				'group_id'	=> $this->group_id,
				'value'		=> $value,
				'locale'	=> $locale,
			]);
		}
	}

	public function attach(Translation $translation){
		$translation->group_id = $this->group_id;
		$this->translations[$translation->locale] = $translation;
	}


	public function in($locale, $fallback = null){
		if($this->has($locale))
			return $this->get($locale)->value;
		
		if($fallback)
			return $this->in($fallback);

		throw new Exceptions\TranslationNotFound($this->group_id);
		return '';
	}



}