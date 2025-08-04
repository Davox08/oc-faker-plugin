<?php

declare(strict_types=1);

namespace Davox\Faker\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * AddRelationsToSeedsTable Migration
 */
return new class extends Migration {
    /**
     * up builds the migration
     */
    public function up(): void
    {
        if (! Schema::hasColumn('davox_faker_seeds', 'relations')) {
            Schema::table('davox_faker_seeds', function (Blueprint $table): void {
                $table->json('relations')->nullable()->after('mappings');
            });
        }
    }

    /**
     * down reverses the migration
     */
    public function down(): void
    {
        if (Schema::hasColumn('davox_faker_seeds', 'relations')) {
            Schema::table('davox_faker_seeds', function (Blueprint $table): void {
                $table->dropColumn('relations');
            });
        }
    }
};
