<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('seats')->default(4);
            $table->string('section')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->foreignId('dining_table_id')->nullable()->constrained();
            $table->string('tab_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('employee_id')->constrained();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'dining_table_id']);
        });

        // One ticket per station each time an order is fired.
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('station');
            $table->string('status')->default('open');
            $table->timestamp('bumped_at')->nullable();
            $table->foreignId('bumped_by')->nullable()->constrained('employees');
            $table->timestamps();

            $table->index(['station', 'status']);
        });

        // Name, price and modifiers are copied at the moment of sale so menu edits never change past bills.
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('menu_item_id')->constrained();
            $table->foreignId('ticket_id')->nullable()->constrained();
            $table->string('name');
            $table->string('station');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->json('modifiers')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedTinyInteger('course')->default(1);
            $table->unsignedTinyInteger('seat')->nullable();
            $table->string('status')->default('new');
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('employees');
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('dining_tables');
    }
};
