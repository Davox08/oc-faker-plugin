<?php

declare(strict_types=1);

namespace Davox\Faker\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * CreateSeedsTable Migration
 */
return new class extends Migration {
    /**
     * up builds the migration
     */
    public function up(): void
    {
        Schema::create('davox_faker_seeds', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('plugin_code')->nullable();
            $table->string('model_class')->nullable();
            $table->integer('record_count')->default(10);
            $table->json('mappings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * down reverses the migration
     */
    public function down(): void
    {
        Schema::dropIfExists('davox_faker_seeds');
    }
};
