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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 36)->index(); // UUID to group rows from same import
            $table->string('source', 10)->default('excel'); // 'excel' or 'odoo'

            // Raw columns matching Odoo Journal Entry export
            $table->string('partner_tax_id')->nullable();       // Partner/Tax ID (NPWP)
            $table->string('partner_id_tku')->nullable();       // Partner/ID TKU
            $table->string('invoice_bill_date')->nullable();    // Invoice/Bill Date
            $table->string('invoice_lines_taxes_id')->nullable(); // Invoice lines/Taxes/ID
            $table->string('invoice_lines_taxes')->nullable();  // Invoice lines/Taxes (e.g. "2% PPH 23 (EXCL)")
            $table->decimal('invoice_lines_amount', 15, 2)->nullable(); // Invoice lines/Amount in Currency
            $table->string('reference')->nullable();            // Reference
            $table->string('payment_reference')->nullable();    // Payment Reference
            $table->string('date')->nullable();                 // Date
            $table->string('journal_items_account')->nullable(); // Journal Items/Account
            $table->decimal('journal_items_amount', 15, 2)->nullable(); // Journal Items/Amount in Currency
            $table->string('number')->nullable();               // Number (Bill Number)
            $table->string('partner_display_name')->nullable(); // Partner/Display Name

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
