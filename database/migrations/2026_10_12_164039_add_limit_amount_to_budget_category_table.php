<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tracked category can carry its own spending limit inside the budget.
     * NULL means the category only counts against the budget's total.
     */
    public function up(): void
    {
        Schema::table('budget_category', function (Blueprint $table) {
            $table->bigInteger('limit_amount')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('budget_category', function (Blueprint $table) {
            $table->dropColumn('limit_amount');
        });
    }
};
