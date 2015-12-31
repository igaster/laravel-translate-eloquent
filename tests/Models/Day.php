<?php namespace igaster\TranslateModel\Test\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Day extends Eloquent
{
    use \igaster\TranslateEloquent\TranslationTrait;

    protected $table = 'days';

    // protected $fillable = ['key'];
}
