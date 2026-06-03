<?php
// Quick test: parse the sample Excel, save to DB, then process
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExcelImportService;
use App\Models\JournalEntry;
use Illuminate\Support\Str;

$service = new ExcelImportService();
$filePath = __DIR__ . '/Journal Entry (account.move) (3).xlsx';

echo "=== Step 1: Parse Raw Rows ===\n";
$rawRows = $service->parseRaw($filePath);
echo "Raw rows parsed: " . count($rawRows) . "\n";

// Show first 3 rows
for ($i = 0; $i < min(3, count($rawRows)); $i++) {
    echo "\nRow $i:\n";
    foreach ($rawRows[$i] as $k => $v) {
        echo "  $k: $v\n";
    }
}

echo "\n=== Step 2: Save to DB ===\n";
$batchId = Str::uuid()->toString();
JournalEntry::truncate();
\Illuminate\Support\Facades\DB::transaction(function() use ($rawRows, $batchId) {
    foreach ($rawRows as $row) {
        JournalEntry::create(array_merge($row, [
            'batch_id' => $batchId,
            'source' => 'excel',
        ]));
    }
});
echo "Saved " . JournalEntry::count() . " rows to DB\n";

echo "\n=== Step 3: Process PPh 23 ===\n";
$entries = JournalEntry::orderBy('id')->get()->map(function ($e) {
    return [
        'partner_tax_id' => $e->partner_tax_id,
        'partner_id_tku' => $e->partner_id_tku,
        'invoice_bill_date' => $e->invoice_bill_date,
        'invoice_lines_taxes_id' => $e->invoice_lines_taxes_id,
        'invoice_lines_taxes' => $e->invoice_lines_taxes,
        'invoice_lines_amount' => $e->invoice_lines_amount,
        'reference' => $e->reference,
        'payment_reference' => $e->payment_reference,
        'date' => $e->date,
        'journal_items_account' => $e->journal_items_account,
        'journal_items_amount' => $e->journal_items_amount,
        'number' => $e->number,
        'partner_display_name' => $e->partner_display_name,
    ];
})->toArray();

$processed = $service->processRawRows($entries);
echo "Processed transactions: " . count($processed) . "\n";

$totalDPP = array_sum(array_column($processed, 'dpp'));
$totalPPh23 = array_sum(array_column($processed, 'pph23'));
$incorrect = count(array_filter($processed, fn($p) => !$p['is_correct']));

echo "\nTotal DPP: Rp " . number_format($totalDPP, 2) . "\n";
echo "Total PPh 23: Rp " . number_format($totalPPh23, 2) . "\n";
echo "Incorrect: $incorrect\n";
echo "\nExpected DPP: Rp 157,818,964.00\n";
echo "Expected PPh 23: Rp 3,156,388.00\n";
echo "Match: " . ($totalDPP == 157818964 && $totalPPh23 == 3156388 ? "YES ✅" : "NO ❌") . "\n";
