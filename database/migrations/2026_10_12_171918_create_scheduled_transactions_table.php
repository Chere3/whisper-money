<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money the user expects to move but that has not moved yet: a bill, a
     * subscription, a salary. Kept apart from `transactions` on purpose, since
     * every reader of that table treats a row as money that already moved.
     */
    public function up(): void
    {
        Schema::create('scheduled_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('space_id')->nullable()->index();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->bigInteger('amount');
            $table->char('currency_code', 3);
            $table->text('notes')->nullable();
            $table->string('frequency');
            $table->date('next_due_date');
            $table->unsignedTinyInteger('anchor_day');
            $table->date('end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_transactions');
    }
};
