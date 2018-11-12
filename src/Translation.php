<?php

namespace igaster\TranslateEloquent;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $table = 'translations';
    protected $fillable = ['group_id', 'locale', 'value'];
    public $timestamps = false;


    public function delete()
    {
        parent::delete();

        if ($this->locale !== 'xx') {
            if (!Translation::where('group_id', $this->group_id)->exists()) {
                Translation::create([
                    'group_id' => $this->group_id,
                    'locale'   => 'xx',
                    'value'    => '',
                ]);
            }
        }
    }
}
