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
            $table->string('group_id');
            $table->string('value');
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

### Step 3: Apply the Trait:

Apply the `TranslationTrait` trait to any model that you want to have translatable keys:

```php
    use igaster\TranslateEloquent\TranslationTrait;
```
now simply set the `$translatable` array in your model with the name of the keys that should be trasnlatable:

```php
    protected $translatable = ['name'];
```

Now you are ready to use translated keys!

## Usage

To get a translated value simpy access the model key without the underscore. The curent application's locale will be used by default to retreive a translation. If no translation is defined then the Laravel's 'app.fallback_locale' will be used. If neither translation is found then an exception will be raised. So simpe!

Get Translations:

```php
$model->key;            // get the translated value of key for the current Locale
$model->_key->in('de'); // request a translation in a different locale
$model->_key;           // get instance of igaster\TranslateEloquent\Translations
```

Set Translations:

```php
$model->day='Montag';               // set the translation for current Locale.  

$model->_day->set('en', 'Monday');  // Set a translation for a given locale

$model->_day->set([                 // Set all translations
    'el' => 'Δευτέρα',
    'en' => 'Monday',
    'de' => 'Montag',
]);

$model->save();                     // Don't forget to save your model to save the relationship
```
When you set a value for a translation then an entry in the the `translations` table will be created / updated.


A short refreshment in Laravel locale functions (Locale is defined in `app.php` configuration file):
```php
App::setLocale('de');                    // Set curent Locale
App::getLocale();                        // Get curent Locale
Config::set('app.fallback_locale','el'); // Set fallback Locale
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
* Implement Model::create() & Model::update functions
* any ideas? Send me a request...