<?php

use igaster\TranslateEloquent\Tests\App\TestModel;

class TestCase extends \abstractTest
{

    // -----------------------------------------------
    //   Global Setup(Run Once)
    // -----------------------------------------------

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        // Your Code here...
    }

    public static function tearDownAfterClass()
    {
        // Your Code here...
        parent::tearDownAfterClass();
    }

    // -----------------------------------------------
    //  Setup Database (Run before each Test)
    // -----------------------------------------------

    public function setUp()
    {
        parent::setUp();

        // -- Set  migrations
        Schema::create('test_table', function ($table) {
            $table->increments('id');
            $table->string('key')->nullable();
            $table->timestamps();
        });
    }

    public function _tearDown()
    {
        Schema::drop('test_table');
        parent::teadDown();
    }

    // -----------------------------------------------
    //  Tests
    // -----------------------------------------------

    public function testDummy()
    {
        $model = TestModel::create([
            'key' => 'value',
        ]);
        $model->fresh();
        $this->assertEquals("value", $model->key);
    }
}
