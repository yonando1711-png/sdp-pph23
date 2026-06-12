<?php
require 'vendor/autoload.php';
$file = 'd:\project_sdp\pph23_converter\processed_excel.xls';
if (!file_exists($file)) {
    echo "File not found\n";
    exit;
}
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
foreach ($spreadsheet->getSheetNames() as $sheetName) {
    echo "\nSheet: $sheetName\n";
    $sheet = $spreadsheet->getSheetByName($sheetName);
    $rows = $sheet->toArray(null, true, false, true);
    if (empty($rows)) {
        echo "Empty sheet\n";
        continue;
    }
    $headers = array_values($rows[1] ?? []);
    echo "Headers: " . implode(', ', $headers) . "\n";
    $row2 = array_values($rows[2] ?? []);
    echo "Row 2: " . implode(', ', $row2) . "\n";
}
