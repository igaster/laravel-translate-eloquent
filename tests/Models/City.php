<?php namespace igaster\TranslateEloquent\Test\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class City extends Eloquent
{
    use \igaster\TranslateEloquent\TranslationTrait;

    protected $table = 'cities';

    protected $translatable = ['name'];
}
