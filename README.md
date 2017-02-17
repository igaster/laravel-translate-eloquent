## Description
[![Laravel](https://img.shields.io/badge/Laravel-5.x-orange.svg)](http://laravel.com)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg)](https://tldrlegal.com/license/mit-license)
[![Downloads](https://img.shields.io/packagist/dt/igaster/laravel-translate-eloquent.svg)](https://packagist.org/packages/igaster/laravel-translate-eloquent)
[![Build Status](https://img.shields.io/travis/igaster/laravel-translate-eloquent.svg)](https://travis-ci.org/igaster/laravel-translate-eloquent)
[![Codecov](https://img.shields.io/codecov/c/github/igaster/laravel-translate-eloquent.svg)](https://codecov.io/github/igaster/laravel-translate-eloquent)

Translate any column in your Database in Laravel models. You need only one additional table to strore translations for all your models.

## Installation

Edit your project's `composer.json` file to require:

    "require": {
        "igaster/laravel-translate-eloquent": "~1.0"
    }

and install with `composer update`

## Setup

### Step 1: Create Translation Table:

Create a new migration with `artisan make:migration translations` and create the following table:

```php
    public function up()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id')->unsigned()->index();
            $table->text('value')->nullable();
            $table->string('locale', 2)->index(); // Can be any length!
        });
    }

    public function down()
    {
        Schema::drop('translations');
    }
```

migrate the database: `php artisan migrate`

### Step 2: Add translatable keys to you models

In your migrations define any number of integer keys that you want to hold translations. (Actually they are foreign key to the translatable.group_id). This is an example migration that will create a translatable key:


```php
    $table->integer('key')->unsigned()->nullable();
```

### Step 2.1: Modifying existing model fields

Define migrations to simply modify your existing models to use translations and migrate all existing data to newly created table.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use igaster\TranslateEloquent\TranslationMigrationTrait;

class AddTranslationToTeamsTable extends Migration
{
    use TranslationMigrationTrait;

    public $table = 'my_table';
    public $fields = ['key1', 'key2'];

    public $model = App\MyModel::class;
}
```

### Step 3: Setup your model:

Apply the `TranslationTrait` trait to any model that you want to have translatable keys and add these keys into the `$translatable` array:

```php
class ExampleModel extends Eloquent
{
    use \igaster\TranslateEloquent\TranslationTrait;

    protected static $translatable = ['key'];
}
```

Now you are ready to use translated keys!

## Usage

When you access a translatable key, then it's translation will be retrieved in the application's current locale. If no translation is defined then the Laravel's 'app.fallback_locale' will be used. If neither translation is found then an an empty string will be returned. So simpe!

### Work with translations:

```php
$model->key='Monday';                    // Set the translation for the current Locale.  
$model->key;                             // Get the translation for the current Locale

$model->translate('de')->key = 'Montag'; // Set translation in a locale
$model->translate('de')->key;            // Get translation in a locale
$model->translate('de','en')->key;       // Get translation in a locale / fallback locale

$model->key = [                          // Set a batch of translations
    'el' => 'Δευτέρα',
    'en' => 'Monday',
    'de' => 'Montag',
];

```

Important notes:

* When you create a new translation for the first time, you must save your model to persist the relationship: `$model->save();`. This is not necessary when updating a translation or adding a new locale.
* When you set a value for a translation then an entry in the the `translations` table will be created / updated.

### Create/Update translated models:

```php
// Create a model translated to current locale
Day::create([
    'name' => 'Πέμπτη',
]);

// Create a model with multiple translations
Day::create([
    'name' => [
        'el' => 'Σάββατο',
        'en' => 'Saturday',
    ]
]);
```

You can also use `$model->update();` with the same way.

### Laravel & Locales:

A short refreshment in Laravel locale functions (Locale is defined in `app.php` configuration file):
```php
App::setLocale('de');                    // Set curent Locale
App::getLocale();                        // Get curent Locale
Config::set('app.fallback_locale','el'); // Set fallback Locale
```

### Working with the `Translations` object

You can achieve the same functionality with the `igaster\TranslateEloquent\Translations` object.

```php
$translations = $model->translations('key'); // Get instance of Translations

$translations->in('de');             // Get a translation in a locale
$translations->set('el', 'Δευτέρα'); // Set a translation in a locale
$translations->has('el');            // Check if a translation exists

$translations->set([                 // Set a batch of translations
    'el' => 'Δευτέρα',
    'en' => 'Monday',
    'de' => 'Montag',
]);

```

Want to dive deeper into the internals?

* `Translations` object holds all translations for a key. Multiple transltions are grouped under the same `group_id` value
* `Translation` object is an Eloquent model (maps to the `translations` table) that represents a single translation for one key in one locale

```php
$translations = new Translations();          // Create a new Translations collection
$translations = new Translations($group_id); // or load Translations with $group_id
$translations->group_id;  // column `translations.group_id` groups all translations for a key

$translation = $translations->get('en');  // Get instance of `Translation` (a single translations)
$translation->id;                   // From this model you have access to the actual translations record
$translation->value='New Value';    // in your database. You can perform any raw opperation on it.
```

## Eager Loading:

You can use these query scopes as if you want to retrieve a translation with the same query:

```php
Day::findWithTranslation(1,'name');   // Day with id 1 with 'name' translated in the current Locale
Day::firstWithTranslation('name');    // First Day from the query with 'name' translated in the current Locale
Day::getWithTranslation('name');      // Collection of Day with 'name' translated in the current Locale
Day::allWithTranslation('name');      // Collection of Day with 'name' translated in the current Locale

// You can specify a locale as an extra parameter in all above scopes:
Day::firstWithTranslation('name', 'en');    // First Day from the query with 'name' translated in English

// The column name is optional and defaults to first item in your `$translatable` array:
Day::firstWithTranslation();    // First Day from the query with the first $translatable column (='name') 
                                // translated in in the current Locale
```

Notes:
* The above query scopes should be used as an endpoint of your queries as they will return either a Model or a Collection
* Eager loading is designed do reduce to a signle query the read operations when you are retrieving a model from the Database. It uses a JOIN statement and not two subsequent queries as opposed to Eloquent eager loading. One limitation of this implementation is that you can only request the translation of a signle field. 
* If your models have multiple keys that should be translated then all the subsequent read opperations will result to an extra query.

## Performance considerations:

Please notice that using a sinle Table for all trasnlations is not the optimal architecture when consindering database performance. Each translation requires one seperate query to the database. If performance is an issue you can check alternative implementations like [dimsav/laravel-translatable](https://github.com/dimsav/laravel-translatable)

## Handle Conflicts:

#### 1. __get() & __set()

This Trait makes use of the `__get()` and `__set()` magic methods to perform its ... well... magic! However if you want to implement these functions in your model or another trait then php will complain about conflicts. To overcome this problem you have to hide the Traits methods when you import it:

```php
use igaster\TranslateEloquent\TranslationTrait {
    __get as private; 
    __set as private; 
}
```

and call them manually from your `__get()` / `__set()` mehods:

```php
//--- copy these in your model if you need to implement __get() __set() methods

public function __get($key) {
    // Handle Translatable keys
    $result=$this->translatable_get($key);
    if ($this->translatable_handled)
        return $result;

    //your code goes here
    
    return parent::__get($key);
}

public function __set($key, $value) {
    // Handle Translatable keys
    $this->translatable_set($key, $value);
    if ($this->translatable_handled)
        return;

    //your code goes here

    parent::__set($key, $value);
} 
```

#### 2. boot(), create(), update() methods

This trait implements the `boot()` method to handle cascaded deletes of the translations. If you should implemeent `boot()` in your model then [rename the method](http://php.net/manual/en/language.oop5.traits.php) when you import the trait:

```php
use igaster\TranslateEloquent\TranslationTrait {
    boot as bootTranslations;
}
```

and call it in your own boot method:

```php
public static function boot()
{
    // your code goes here
    self::bootTranslations();
}
```

The same aproach can be followed if you need to override Eloquent's `create()` or `update()` methods, which are overriden in the Trait.

## Todo
* ~~Cascade delete model + translations~~ Fixed
* Eager Load multiple keys per request...
* any ideas? Send me a request...