<?php
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__.'/Journal Entry (account.move) (3).xlsx';
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, false, true);

echo "Raw Excel rows for BILLS/2026/02/0586:\n";
for ($i = 2; $i <= count($rows); $i++) {
    if (($rows[$i]['L'] ?? '') === 'BILLS/2026/02/0586') {
        echo "Row $i: " . json_encode($rows[$i]) . "\n";
    }
}
