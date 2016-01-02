<?php namespace igaster\TranslateEloquent\Tests\TestCase;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Orchestra\Testbench\TestCase;

class TestCaseWithDatbase extends TestCase
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

    protected $database;

    public function setUp()
    {
        parent::setUp();

        Eloquent::unguard();
        $database = new DB;
        $database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $database->bootEloquent();
        $database->setAsGlobal();
        $this->database=$database;

        // Add your migrations here. ie:

        // $this->database->schema()->create('TableName', function ($table) {
        //     $table->increments('id');
        // });        
    }

    public function tearDown() {
    	// Drop tables here. ie:
        // $this->database->schema()->drop('TableName');
    }

    // -----------------------------------------------

    public function testDatabaseConnection()
    {
    	$this->assertInstanceOf('Illuminate\Database\SQLiteConnection', $this->database->connection());
    }

    // -----------------------------------------------
    //  Added functionality
    // -----------------------------------------------

    protected function seeInDatabase($table, array $data, $connection = null)
    {
        $database = $this->database;

        $count = $database->table($table)->where($data)->count();

        $this->assertGreaterThan(0, $count, sprintf(
            'Unable to find row in database table [%s] that matched attributes [%s].', $table, json_encode($data)
        ));

        return $this;
    }

    protected function notSeeInDatabase($table, array $data, $connection = null)
    {
        $database = $this->database;

        $count = $database->table($table)->where($data)->count();

        $this->assertEquals(0, $count, sprintf(
            'Found unexpected records in database table [%s] that matched attributes [%s].', $table, json_encode($data)
        ));

        return $this;
    }
}    