<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money owed between the user and another person: what they lent out and
     * what they borrowed, with how much of it has come back so far.
     */
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('space_id')->nullable()->index();
            $table->string('direction');
            $table->string('counterparty');
            $table->string('description')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('paid_amount')->default(0);
            $table->char('currency_code', 3);
            $table->date('lent_on');
            $table->date('due_date')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'settled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
