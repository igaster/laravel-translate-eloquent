<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use igaster\TranslateModel\Translatable;
use igaster\TranslateModel\Translations;
use igaster\TranslateModel\Translation;
use igaster\TranslateModel\Exceptions\KeyNotTranslatable;
use igaster\TranslateModel\Exceptions\TranslationNotFound;

// Replace 'User' with a model that has a translatable '_key'
class TranslatableTest extends TestCase
{
    use DatabaseTransactions;

    private function getNewModel(){
        $model = factory(User::class)->create();
        return $this->reloadModel($model);
    }

    private function reloadModel($model){
        return User::find($model->id);
    }

    private function set_locale($locale, $fallback_locale){
        App::setLocale($locale);
        Config::set('app.fallback_locale', $fallback_locale);
    }

    public function test_Translations_collection() {
        // empty
        $translations = new Translations();
        $this->assertNull($translations->get('el'));
        $this->assertEquals($translations->has('el'), false);

        // set
        $translations->set('el', 'Ελληνικά');
        $this->assertEquals($translations->has('el'), true);
        $translations = new Translations($translations->group_id);
        $this->assertEquals($translations->has('el'), true);
        $this->assertInstanceOf(Translation::class, $translations->get('el'));
        $this->assertEquals($translations->in('el'), 'Ελληνικά');

        // update
        $translations->set('el', 'Δευτέρα');
        $translations->set('en', 'Monday');
        $translations = new Translations($translations->group_id);
        $this->assertEquals($translations->in('el'), 'Δευτέρα');
        $this->assertEquals($translations->has('en'), true);

        // Not found
        $this->setExpectedException(TranslationNotFound::class);
        $translations->in('invalid');
    }

    public function test_trait() {
        $model = $this->getNewModel();
        $this->assertEquals($model->isTranslatable('key'), true);
        $this->assertEquals($model->isTranslatable('name'), false);
        $this->assertEquals($model->isTranslatable('invalid'), false);
        $this->assertEquals($model->isTranslation('_key'), true);
        $this->assertEquals($model->isTranslation('name'), false);
        $this->assertEquals($model->isTranslation('_invalid'), false);

        $this->assertInstanceOf(Translations::class, $model->getTranslations('key'));
        
        $this->assertEquals(isset($model->key), true);

        $this->setExpectedException(KeyNotTranslatable::class);
        $model->getTranslationId('invalid');
    }

    public function test_set_property(){
        $model = $this->getNewModel();
        
        // Create
        App::setLocale('el');
        $model->key = 'Τρίτη';
        $model->save();
        $this->reloadModel($model);
        $this->assertEquals($model->key, 'Τρίτη');
        
        // Update
        $model->key = 'Τετάρτη';
        $this->assertEquals($model->key, 'Τετάρτη');
        
        // 2nd locale
        App::setLocale('en');
        $model->key = 'Wednesday';
        $this->assertEquals($model->key, 'Wednesday');
    }    

    public function test_set_array_format(){
        $model = $this->getNewModel();

        $model->_key->set([
            'el' => 'Ένα',
            'en' => 'One',
        ]);

        $model->save();
        $this->reloadModel($model);

        $this->assertEquals($model->_key->in('el'), 'Ένα');
        $this->assertEquals($model->_key->in('en'), 'One');
    }


    public function test_get_translations() {
        $model = $this->getNewModel();

        $this->assertInstanceOf(Translations::class,$model->_key);

        $model->_key->set([
            'el' => 'Δευτέρα',
            'en' => 'Monday',
        ]);

        $model->save();
        $this->reloadModel($model);

        $this->assertInstanceOf(Translations::class,$model->_key);
        $this->assertInstanceOf(Translation::class, $model->_key->get('el'));

        $this->assertEquals($model->_key->in('el'), 'Δευτέρα');
        $this->assertEquals($model->_key->in('en'), 'Monday');
        $this->assertEquals($model->_key->in('invalid', 'el'), 'Δευτέρα');

        App::setLocale('el');
        $this->assertEquals($model->key, 'Δευτέρα');

        $this->setExpectedException(TranslationNotFound::class);
        $model->_key->in('invalid');
    }

    public function test_get_key_array_access() {
        $model = $this->getNewModel();
        App::setLocale('el');

        // get
        $model->key= 'Δευτέρα';
        $this->assertEquals($model['key'], 'Δευτέρα');

        // set 
        $model['key']= 'Τρίτη';
        $this->assertEquals($model->key, 'Τρίτη');
    }

    public function test_fallback_locale(){
        $model = $this->getNewModel();

        $model->_key->set([
            'el' => 'Κυριακή',
            'en' => 'Sunday',
        ]);

        $this->set_locale('el','en');
        $this->assertEquals($model->key, 'Κυριακή');

        $this->set_locale('de', 'en');
        $this->assertEquals($model->key, 'Sunday');

        $this->assertEquals($model->_key->in('invalid','el'), 'Κυριακή');
        $this->assertEquals($model->_key->in('el','invalid'), 'Κυριακή');
    }

}
