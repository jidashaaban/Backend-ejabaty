<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('father_name')->after('name');
            $table->string('last_name')->after('father_name');
            $table->string('grade')->nullable();
            $table->text('past_education')->nullable();
            $table->decimal('last_years_mark', 5, 2)->nullable();
            $table->string('status')->default('active'); // active/unactive
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
