<?php namespace igaster\TranslateEloquent\Test\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Day extends Eloquent
{
    use \igaster\TranslateEloquent\TranslationTrait;

    protected $table = 'days';

    protected static $translatable = ['name'];

    // protected $fillable = ['key'];
}
