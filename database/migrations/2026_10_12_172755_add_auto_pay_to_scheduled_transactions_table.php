<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `auto_pay` lets a schedule record itself on its due date. `reminded_for`
     * is the due date the user was last told about, so the daily run reminds
     * once per occurrence however many times it runs.
     */
    public function up(): void
    {
        Schema::table('scheduled_transactions', function (Blueprint $table) {
            $table->boolean('auto_pay')->default(false);
            $table->date('reminded_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_transactions', function (Blueprint $table) {
            $table->dropColumn(['auto_pay', 'reminded_for']);
        });
    }
};
