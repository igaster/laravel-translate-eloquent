<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Orchestra\Testbench\TestCase;

use igaster\TranslateEloquent\Translatable;
use igaster\TranslateEloquent\Translations;
use igaster\TranslateEloquent\Translation;
use igaster\TranslateEloquent\Exceptions\KeyNotTranslatable;
use igaster\TranslateEloquent\Exceptions\TranslationNotFound;

use igaster\TranslateEloquent\Test\Models\Day;

class TranslationTest extends TestCaseWithDatbase
{

    // -----------------------------------------------
    //  Setup Database
    // -----------------------------------------------

    public function setUp()
    {
        parent::setUp();

        // -- Set  migrations
        $this->database->schema()->create('translations', function ($table) {
            $table->increments('id');
            $table->string('group_id');
            $table->string('value');
            $table->string('locale', 2); // Can be any lenght!
        });

        $this->database->schema()->create('days', function ($table) {
            $table->increments('id');
            $table->integer('name')->unsigned()->nullable();
            $table->boolean('weekend')->default(false);
            $table->timestamps();
        });
    }

    public function tearDown() {
        $this->database->schema()->drop('days');
        $this->database->schema()->drop('translations');
    }

    // -----------------------------------------------

    private function getNewModel(){
        $model = Day::create();
        return $this->reloadModel($model);
    }

    private function reloadModel($model){
        return Day::find($model->id);
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
        $this->assertEquals(true,  Day::isTranslatable('name'));
        $this->assertEquals(false, Day::isTranslatable('weekend'));
        $this->assertEquals(false, Day::isTranslatable('invalid'));

        $this->assertNull($model->getTranslationId('name'));

        $this->assertInstanceOf(Translations::class, $model->translations('name'));
        
        $this->assertEquals(isset($model->name), true);

        $this->setExpectedException(KeyNotTranslatable::class);
        $model->getTranslationId('invalid');
    }

    public function test_set_property(){
        $model = $this->getNewModel();
        
        // Create
        App::setLocale('el');
        $model->name = 'Τρίτη';
        $model->save();
        $this->reloadModel($model);
        $this->assertEquals($model->name, 'Τρίτη');
        
        // Update
        $model->name = 'Τετάρτη';
        $this->assertEquals($model->name, 'Τετάρτη');
        
        // 2nd locale
        App::setLocale('en');
        $model->name = 'Wednesday';
        $this->assertEquals($model->name, 'Wednesday');
    }    

    public function test_set_array_format(){
        $model = $this->getNewModel();

        $model->translations('name')->set([
            'el' => 'Ένα',
            'en' => 'One',
        ]);

        $model->save();
        $this->reloadModel($model);

        $this->assertEquals($model->translations('name')->in('el'), 'Ένα');
        $this->assertEquals($model->translations('name')->in('en'), 'One');
    }

    public function test_set_array_format_from_model(){
        $model = $this->getNewModel();

        $model->name = [
            'el' => 'Τρία',
            'en' => 'Three',
        ];

        $model->save();
        $this->reloadModel($model);

        $this->assertEquals($model->translations('name')->in('el'), 'Τρία');
        $this->assertEquals($model->translations('name')->in('en'), 'Three');
    }

    public function test_get_translations() {
        $model = $this->getNewModel();

        $this->assertInstanceOf(Translations::class,$model->translations('name'));

        $model->translations('name')->set([
            'el' => 'Δευτέρα',
            'en' => 'Monday',
        ]);

        $model->save();
        $this->reloadModel($model);

        $this->assertInstanceOf(Translations::class,$model->translations('name'));
        $this->assertInstanceOf(Translation::class, $model->translations('name')->get('el'));

        $this->assertEquals($model->translations('name')->in('el'), 'Δευτέρα');
        $this->assertEquals($model->translations('name')->in('en'), 'Monday');
        $this->assertEquals($model->translations('name')->in('invalid', 'el'), 'Δευτέρα');

        App::setLocale('el');
        $this->assertEquals($model->name, 'Δευτέρα');

        $this->setExpectedException(TranslationNotFound::class);
        $model->translations('name')->in('invalid');
    }

    public function test_get_key_array_access() {
        $model = $this->getNewModel();
        App::setLocale('el');

        // get
        $model->name= 'Δευτέρα';
        $this->assertEquals($model['name'], 'Δευτέρα');

        // set 
        $model['name']= 'Τρίτη';
        $this->assertEquals($model->name, 'Τρίτη');
    }


    public function test_translate_to_locale(){
        $model = $this->getNewModel();
        
        $model->translations('name')->set([
            'el' => 'Ένα',
            'en' => 'One',
            'de' => 'Eins',
        ]);

        $this->set_locale('en','de');
        $this->assertEquals($model->translate('el')->name, 'Ένα');
        $this->assertEquals($model->translate('it', 'el')->name, 'Ένα');
        $this->assertEquals($model->name, 'One');

        $model->translate('el')->name =  'Δύο';
        $model->name='Two';
        $this->assertEquals($model->translations('name')->in('el'), 'Δύο');
        $this->assertEquals($model->translations('name')->in('en'), 'Two');

    }

    public function test_fallback_locale(){
        $model = $this->getNewModel();

        $model->translations('name')->set([
            'el' => 'Κυριακή',
            'en' => 'Sunday',
        ]);

        $this->set_locale('el','en');
        $this->assertEquals($model->name, 'Κυριακή');

        $this->set_locale('de', 'en');
        $this->assertEquals($model->name, 'Sunday');

        $this->assertEquals($model->translations('name')->in('invalid','el'), 'Κυριακή');
        $this->assertEquals($model->translations('name')->in('el','invalid'), 'Κυριακή');
    }

    public function test_model_new(){
        $model = new Day();
        $this->assertInstanceOf(Translations::class,$model->translations('name'));

        $model->name = 'Τρίτη';
        $this->assertEquals($model->name, 'Τρίτη');
        $model->save();
        $model = $this->reloadModel($model);
        $this->assertEquals($model->name, 'Τρίτη');

        $model = new Day();
        $model->translations('name')->set('el', 'Τετάρτη');
        $model->save();
        $model = $this->reloadModel($model);
        $this->assertEquals($model->translate('el')->name, 'Τετάρτη');
    }

    public function test_model_create_single_translation(){
        App::setLocale('el');
        $model = Day::create([
            'weekend' => true,
            'name' => 'Πέμπτη',
        ]);
        $this->assertEquals($model->name, 'Πέμπτη');
        $model = $this->reloadModel($model);
        $this->assertEquals($model->name, 'Πέμπτη');
        $this->assertEquals($model->weekend, true);
    }

    public function test_model_create_multiple_translations(){
        $model = Day::create([
            'weekend' => true,
            'name' => [
                'el' => 'Σάββατο',
                'en' => 'Saturday',
            ]
        ]);
        $this->assertEquals($model->translate('en')->name, 'Saturday');
        $model = $this->reloadModel($model);
        $this->assertEquals($model->translate('en')->name, 'Saturday');
    }

    public function test_model_update(){
        $model = Day::create([
            'weekend' => false,
            'name' => [
                'el' => 'Κυριακή',
                'en' => 'Sunday',
            ]
        ]);
        $this->assertEquals($model->translate('en')->name, 'Sunday');

        $model->update([
            'weekend' => true,
            'name' => [
                'el' => 'Τετάρτη',
                'en' => 'Wednesday',
            ]
        ]);

        $this->assertEquals($model->translate('en')->name, 'Wednesday');
        $model = $this->reloadModel($model);
        $this->assertEquals($model->translate('en')->name, 'Wednesday');
        $this->assertEquals(true, $model->weekend);

        App::setLocale('el');
        $model->update([
            'weekend' => true,
            'name' => 'Πέμπτη',
        ]);
        $this->assertEquals($model->translate('el')->name, 'Πέμπτη');
    }

    public function test_model_delete(){
        $model = Day::create([
            'weekend' => false,
            'name' => [
                'el' => 'zzz-el',
                'en' => 'zzz-en',
            ]
        ]);

        $model = Day::create([
            'weekend' => false,
            'name' => [
                'el' => 'xxx-el',
                'en' => 'xxx-en',
            ]
        ]);
        $this->seeInDatabase('translations', ['value' => 'xxx-el']);
        $model->delete();
        $this->notSeeInDatabase('days', ['id' => $model->id]);
        $this->notSeeInDatabase('translations', ['value' => 'xxx-el']);
        $this->notSeeInDatabase('translations', ['value' => 'xxx-en']);
        $this->seeInDatabase('translations', ['value' => 'zzz-el']);
    }
}
