<?php

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file1 = __DIR__.'/pph23_february (1).xls';
$file2 = __DIR__.'/jpph23_1.xls';

if (!file_exists($file1)) {
    die("File 1 not found: $file1\n");
}
if (!file_exists($file2)) {
    die("File 2 not found: $file2\n");
}

function inspectFile($path, $name) {
    echo "=== Inspecting $name ($path) ===\n";
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, false, true);
    
    echo "Total Rows: " . count($rows) . "\n";
    
    // Print headers
    if (isset($rows[1])) {
        echo "Headers: " . json_encode($rows[1]) . "\n";
    }
    
    // Print first 5 data rows
    echo "First 5 data rows:\n";
    for ($i = 2; $i <= min(6, count($rows)); $i++) {
        echo "Row $i: " . json_encode($rows[$i]) . "\n";
    }
    echo "\n";
}

inspectFile($file1, "Our Exported File");
inspectFile($file2, "FoxPro Exported File");
