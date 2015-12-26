<?php namespace igaster\TranslateEloquent;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model {

    protected $table = 'translations';
    protected $fillable = ['group_id', 'locale', 'value'];
    public $timestamps = false;

}