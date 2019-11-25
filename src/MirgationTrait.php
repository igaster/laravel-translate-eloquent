<?php namespace igaster\TranslateEloquent;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use igaster\TranslateEloquent\Translation;
use igaster\TranslateEloquent\Translations;

trait TranslationMigrationTrait {
	public function up(){
		Schema::table($this->table, function (Blueprint $table) {
			foreach($this->fields as $field){
				$table->renameColumn($field, 'TranslationMigrationTraitTmp_' . $field);
			}
        });

        Schema::table($this->table, function (Blueprint $table) {
        	foreach($this->fields as $field){
            	$table->integer($field)->unsigned()->nullable()->after('TranslationMigrationTraitTmp_' . $field);
            }
        });

        foreach ($this->model::all() as $entry) {
        	foreach($this->fields as $field){
	            $group_id = Translations::nextGroupId();

	            Translation::create([
	                'group_id' => $group_id,
	                'locale' => config()->get('app.fallback_locale'),
	                'value' => $entry->{'TranslationMigrationTraitTmp_' . $field},
	            ]);

	            $entry->$field = $group_id;
	        }

	        $entry->save();
        }

        Schema::table($this->table, function (Blueprint $table) {
        	foreach($this->fields as $field){
            	$table->dropColumn('TranslationMigrationTraitTmp_' . $field);
            }
        });
	}

	public function down(){
        Schema::table($this->table, function (Blueprint $table) {
        	foreach($this->fields as $field){
            	$table->renameColumn($field, 'TranslationMigrationTraitTmp_' . $field);
            }
        });

        Schema::table($this->table, function (Blueprint $table) {
        	foreach($this->fields as $field){
            	$table->string($field)->after('TranslationMigrationTraitTmp_' . $field);
            }
        });

        foreach ($this->model::all() as $entry) {
        	foreach($this->fields as $field){
	            $translation = Translation::where('group_id', $entry->{'TranslationMigrationTraitTmp_' . $field})->first();

	            $entry->$field = $translation->value();

	            $translation()->delete();
	        }

	        $entry->save();
        }

        Schema::table($this->table, function (Blueprint $table) {
        	foreach($this->fields as $field){
            	$table->dropColumn('TranslationMigrationTraitTmp_' . $field);
            }
        });
    }
}

?>