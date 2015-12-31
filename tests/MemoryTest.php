<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Orchestra\Testbench\TestCase;

use igaster\TranslateEloquent\Translatable;
use igaster\TranslateEloquent\Translations;
use igaster\TranslateEloquent\Translation;
use igaster\TranslateEloquent\Exceptions\KeyNotTranslatable;
use igaster\TranslateEloquent\Exceptions\TranslationNotFound;

use igaster\TranslateEloquent\Test\Models\City;

class MemoryTest extends TestCase
{

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

        $db->schema()->create('cities', function ($table) {
            $table->increments('id');
            $table->integer('_name')->unsigned()->nullable();
            $table->integer('population');
            $table->timestamps();
        });
    }

    public function tearDown() {
        $this->db->schema()->drop('cities');
        $this->db->schema()->drop('translations');
    }

    // -----------------------------------------------

    public function test_xxx() {
        $city = City::create([
            'population' => 1000
        ]);


        $this->assertEquals(1,1);
    }
}