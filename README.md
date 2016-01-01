## Description
[![Laravel](https://img.shields.io/badge/Laravel-5.x-orange.svg)](http://laravel.com)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg)](https://tldrlegal.com/license/mit-license)
[![Downloads](https://img.shields.io/packagist/dt/igaster/laravel-translate-eloquent.svg)](https://packagist.org/packages/igaster/laravel-translate-eloquent)
[![Build Status](https://travis-ci.org/igaster/laravel-translate-eloquent.svg?branch=master)](https://travis-ci.org/igaster/laravel-translate-eloquent)



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
            $table->integer('group_id')->unsigned();
            $table->string('value')->nullable();
            $table->string('locale', 2); // Can be any lenght!
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

When you access a translatable key, then it's translation will be retrieved in the application's current locale. If no translation is defined then the Laravel's 'app.fallback_locale' will be used. If neither translation is found then an exception will be raised. So simpe!

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
    'weekend' => true,
    'name' => 'Πέμπτη',
]);

// Create a model with many translations
Day::create([
    'weekend' => true,
    'name' => [
        'el' => 'Σάββατο',
        'en' => 'Saturday',
    ]
]);
```

You can also use `$model->update();` with the same way.

A short refreshment in Laravel locale functions (Locale is defined in `app.php` configuration file):
```php
App::setLocale('de');                    // Set curent Locale
App::getLocale();                        // Get curent Locale
Config::set('app.fallback_locale','el'); // Set fallback Locale
```

### Working with the `Translations` object

You can achieve the same functionality with the `igaster\TranslateEloquent\Translations` object.

```php
$translations = $model->translations('key'); // instance of Translations collection

$translations->in('de');             // Get a translation in a locale
$translations->set('el', 'Δευτέρα'); // Set a translation in a locale

$translations->set([                 // Set a batch of translations
    'el' => 'Δευτέρα',
    'en' => 'Monday',
    'de' => 'Montag',
]);
```

## Handle Conflicts:

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

## Todo (more)

* Cascade delete model + translations
* Handle untranslated values (throwing Exception is brute force!)
* any ideas? Send me a request...