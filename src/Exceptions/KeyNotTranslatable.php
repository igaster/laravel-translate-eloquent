<?php namespace igaster\TranslateEloquent\Exceptions;

class KeyNotTranslatable extends \Exception{

    public function __construct($key, $code = 0, Exception $previous = null){
		$message = "'$key' is not a valid Translatable key";
        parent::__construct($message, $code, $previous);
    }

}