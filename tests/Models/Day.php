<?php namespace igaster\TranslateEloquent\Tests\Models;

use igaster\TranslateEloquent\TranslationTrait;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Day extends Eloquent
{
    use TranslationTrait;

    protected $guarded = [];
    protected $table = 'days';

    protected static $translatable = ['name'];

    public function getWeekendAttribute($value)
    {
        return (boolean) $value;
    }
}
