<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('crud-service-generator.database.table_name_log'), function (Blueprint $table) {
            $table->id();

            if (config('crud-service-generator.use_uuids', false)) {
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->uuidMorphs('auditable');
            } else {
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->morphs('auditable');
            }

            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('crud-service-generator.database.table_name_log'));
    }
};