<?php namespace igaster\TranslateEloquent\Exceptions;

class TranslationNotFound extends \Exception{

    public function __construct($id, $code = 0, Exception $previous = null){
		$message = "No translation found (Translation ID: $id)";
        parent::__construct($message, $code, $previous);
    }

}