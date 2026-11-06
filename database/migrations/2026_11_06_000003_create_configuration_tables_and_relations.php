<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->time('start_time')->default('11:00:00');
            $table->time('end_time')->default('15:30:00');
            $table->decimal('default_float', 12, 2)->default(200.00);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('operational_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120)->unique();
            $table->string('default_unit_code', 30)->default('DN-M01');
            $table->string('address_reference', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', function (Blueprint $table): void {
                if (! Schema::hasColumn('shifts', 'shift_template_id')) {
                    $table->foreignId('shift_template_id')
                        ->nullable()
                        ->after('shift_code')
                        ->constrained('shift_templates')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('shifts', 'operational_location_id')) {
                    $table->foreignId('operational_location_id')
                        ->nullable()
                        ->after('shift_template_id')
                        ->constrained('operational_locations')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', function (Blueprint $table): void {
                if (Schema::hasColumn('shifts', 'shift_template_id')) {
                    $table->dropConstrainedForeignId('shift_template_id');
                }
                if (Schema::hasColumn('shifts', 'operational_location_id')) {
                    $table->dropConstrainedForeignId('operational_location_id');
                }
            });
        }

        Schema::dropIfExists('operational_locations');
        Schema::dropIfExists('shift_templates');
    }
};

