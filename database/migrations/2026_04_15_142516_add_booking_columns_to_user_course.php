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
        Schema::table('user_course', function (Blueprint $table) {
            $table->string('status')->default('pending_payment')->after('course_id');
            $table->timestamp('booked_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_course', function (Blueprint $table) {
            $table->dropColumn(['status', 'booked_at']);
        });
    }
};
