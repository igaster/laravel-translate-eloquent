<?php

use igaster\TranslateEloquent\Tests\TestCase\TestCaseWithDatbase;

use igaster\TranslateEloquent\Translatable;
use igaster\TranslateEloquent\Translations;
use igaster\TranslateEloquent\Translation;
use igaster\TranslateEloquent\Exceptions\KeyNotTranslatable;
use igaster\TranslateEloquent\Exceptions\TranslationNotFound;

use igaster\TranslateEloquent\Tests\Models\Day;

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


    public function createTwoDays(){
        $day1 = Day::create([
            'weekend' => false,
            'name' => [
                'el' => 'Τετάρτη',
                'en' => 'Wednesday',
            ]
        ]);

        $day2 = Day::create([
            'weekend' => true,
            'name' => [
                'el' => 'Κυριακή',
                'en' => 'Sunday',
            ]
        ]);

        return [$day1, $day2];
    }

    public function test_model_update(){
        list($day1,$day2) = $this->createTwoDays();

        $this->assertEquals($day1->translate('en')->name, 'Wednesday');
        $day1 = $this->reloadModel($day1);
        $this->assertEquals($day1->translate('en')->name, 'Wednesday');
        $this->assertEquals(false, $day1->weekend);

        App::setLocale('el');
        $day1->update([
            'weekend' => true,
            'name' => 'Πέμπτη',
        ]);
        $this->assertEquals($day1->translate('el')->name, 'Πέμπτη');
    }

    public function test_model_delete(){
        list($day1,$day2) = $this->createTwoDays();

        $this->seeInDatabase('translations', ['value' => 'Wednesday']);
        $day1->delete();
        $this->notSeeInDatabase('days', ['id' => $day1->id]);
        $this->notSeeInDatabase('translations', ['value' => 'Wednesday']);
        $this->notSeeInDatabase('translations', ['value' => 'Τετάρτη']);
        $this->seeInDatabase('translations', ['value' => 'Κυριακή']);
    }

    public function test_eager_load_translation_first(){
        $this->createTwoDays();
        App::setLocale('el');
        $model = Day::where('weekend',true)->firstWithTranslation('name');
        $this->assertEquals('Κυριακή', $model->name);
    }

    public function test_eager_load_translation_find(){
        $this->createTwoDays();
        App::setLocale('el');
        $model = Day::findWithTranslation(1,'name');
        $this->assertEquals('Τετάρτη', $model->name);

        $model = Day::findWithTranslation(2);
        $this->assertEquals('Κυριακή', $model->name);

    }

    public function test_eager_load_translation_get(){
        $this->createTwoDays();
        App::setLocale('el');
        $collection = Day::orderBy('id')->getWithTranslation('name');
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $collection);
        $this->assertEquals(2, $collection->count());
        $this->assertInstanceOf(Day::class, $collection->first());
        $this->assertEquals('Τετάρτη', $collection->first()->name);
    }

    public function test_eager_load_translation_all(){
        $this->createTwoDays();
        App::setLocale('el');
        $collection = Day::orderBy('id')->allWithTranslation();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $collection);
        $this->assertEquals(2, $collection->count());
        $this->assertInstanceOf(Day::class, $collection->first());
        $this->assertEquals('Τετάρτη', $collection->first()->name);
    }    
}
