<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$cleanupPath = 'd:\project_sdp\pph23_converter\cleanup_may.xls';
$processedPath = 'd:\project_sdp\pph23_converter\processed_excel.xls';

$s1 = IOFactory::load($cleanupPath)->getSheetByName('SHEET.1');
$s2 = IOFactory::load($processedPath)->getSheetByName('SHEET.1');

$data1 = [];
$data2 = [];

$highestRow1 = min($s1->getHighestDataRow(), 200);
$highestRow2 = min($s2->getHighestDataRow(), 200);

for ($r = 2; $r <= $highestRow1; $r++) {
    $data1[] = $s1->getCell('A' . $r)->getValue();
}
for ($r = 2; $r <= $highestRow2; $r++) {
    $data2[] = $s2->getCell('A' . $r)->getValue();
}

sort($data1);
sort($data2);

echo "Number of rows in Cleanup: " . count($data1) . "\n";
echo "Number of rows in Processed: " . count($data2) . "\n";

$diffCount = 0;
for ($i=0; $i<min(count($data1), count($data2)); $i++) {
    if ($data1[$i] !== $data2[$i]) {
        $diffCount++;
    }
}
echo "Number of mismatched IDs after sorting: $diffCount\n";
