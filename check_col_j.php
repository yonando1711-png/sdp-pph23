<?php
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__.'/jpph23_1.xls';
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, false, true);

$uniqueAccounts = [];
for ($i = 2; $i <= count($rows); $i++) {
    $val = $rows[$i]['J'] ?? null;
    $uniqueAccounts[$val] = ($uniqueAccounts[$val] ?? 0) + 1;
}

echo "Unique accounts in Column J (journal_it) of Ref:\n";
print_r($uniqueAccounts);
