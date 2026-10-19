<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // A drawer session holds one cash drawer's float and takings from opening to count-up.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('drawer');
            $table->foreignId('opened_by')->constrained('employees');
            $table->unsignedBigInteger('float_ssp')->default(0);
            $table->unsignedBigInteger('float_usd')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('employees');
            $table->unsignedBigInteger('counted_ssp')->nullable();
            $table->unsignedBigInteger('counted_usd')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->nullable()->after('guest_count');
            $table->unsignedBigInteger('tax')->nullable()->after('subtotal');
            $table->unsignedBigInteger('service_charge')->nullable()->after('tax');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('employees');
        });

        // amount = what this payment takes off the bill, in venue currency minor units.
        // tendered = what the guest handed over, in the tender's own currency minor units.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('tender');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('tip')->default(0);
            $table->string('currency', 3);
            $table->unsignedBigInteger('tendered');
            $table->unsignedInteger('exchange_rate')->nullable();
            $table->unsignedBigInteger('change_given')->default(0);
            $table->string('reference')->nullable();
            $table->foreignId('cash_session_id')->nullable()->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['subtotal', 'tax', 'service_charge']);
        });
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('settings');
    }
};
