<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'batch_id',
        'source',
        'partner_tax_id',
        'partner_id_tku',
        'invoice_bill_date',
        'invoice_lines_taxes_id',
        'invoice_lines_taxes',
        'invoice_lines_amount',
        'reference',
        'payment_reference',
        'date',
        'journal_items_account',
        'journal_items_amount',
        'number',
        'partner_display_name',
    ];

    protected $casts = [
        'invoice_lines_amount' => 'decimal:2',
        'journal_items_amount' => 'decimal:2',
    ];
}
