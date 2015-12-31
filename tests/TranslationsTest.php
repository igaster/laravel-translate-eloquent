<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Orchestra\Testbench\TestCase;

use igaster\TranslateEloquent\Translatable;
use igaster\TranslateEloquent\Translations;
use igaster\TranslateEloquent\Translation;
use igaster\TranslateEloquent\Exceptions\KeyNotTranslatable;
use igaster\TranslateEloquent\Exceptions\TranslationNotFound;

use igaster\TranslateModel\Test\Models\Day;

class TranslatableTest extends TestCase
{
    // use DatabaseTransactions;

    // -----------------------------------------------
    //  Load .env Environment Variables
    // -----------------------------------------------

    public static function setUpBeforeClass()
    {
        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = new Dotenv\Dotenv(__DIR__.'/../');
            $dotenv->load();
        }
    }

    // -----------------------------------------------
    //  Setup Database
    // -----------------------------------------------

    protected $db;
    public function setUp()
    {
        parent::setUp();

        Eloquent::unguard();
        $db = new DB;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();
        $this->db=$db;

        // -- Set  migrations
        $db->schema()->create('translations', function ($table) {
            $table->increments('id');
            $table->string('group_id');
            $table->string('value');
            $table->string('locale', 2); // Can be any lenght!
        });

        $db->schema()->create('days', function ($table) {
            $table->increments('id');
            $table->integer('_name')->unsigned()->nullable();
            $table->boolean('weekend')->default(false);
            $table->timestamps();
        });
    }

    public function tearDown() {
        $this->db->schema()->drop('days');
        $this->db->schema()->drop('translations');
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
        $this->assertEquals($model->isTranslatable('name'), true);
        $this->assertEquals($model->isTranslatable('weekend'), false);
        $this->assertEquals($model->isTranslatable('invalid'), false);
        $this->assertEquals($model->isTranslation('_name'), true);
        $this->assertEquals($model->isTranslation('weekend'), false);
        $this->assertEquals($model->isTranslation('_invalid'), false);

        $this->assertInstanceOf(Translations::class, $model->getTranslations('name'));
        
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

        $model->_name->set([
            'el' => 'Ένα',
            'en' => 'One',
        ]);

        $model->save();
        $this->reloadModel($model);

        $this->assertEquals($model->_name->in('el'), 'Ένα');
        $this->assertEquals($model->_name->in('en'), 'One');
    }


    public function test_get_translations() {
        $model = $this->getNewModel();

        $this->assertInstanceOf(Translations::class,$model->_name);

        $model->_name->set([
            'el' => 'Δευτέρα',
            'en' => 'Monday',
        ]);

        $model->save();
        $this->reloadModel($model);

        $this->assertInstanceOf(Translations::class,$model->_name);
        $this->assertInstanceOf(Translation::class, $model->_name->get('el'));

        $this->assertEquals($model->_name->in('el'), 'Δευτέρα');
        $this->assertEquals($model->_name->in('en'), 'Monday');
        $this->assertEquals($model->_name->in('invalid', 'el'), 'Δευτέρα');

        App::setLocale('el');
        $this->assertEquals($model->name, 'Δευτέρα');

        $this->setExpectedException(TranslationNotFound::class);
        $model->_name->in('invalid');
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

    public function test_fallback_locale(){
        $model = $this->getNewModel();

        $model->_name->set([
            'el' => 'Κυριακή',
            'en' => 'Sunday',
        ]);

        $this->set_locale('el','en');
        $this->assertEquals($model->name, 'Κυριακή');

        $this->set_locale('de', 'en');
        $this->assertEquals($model->name, 'Sunday');

        $this->assertEquals($model->_name->in('invalid','el'), 'Κυριακή');
        $this->assertEquals($model->_name->in('el','invalid'), 'Κυριακή');
    }

}
