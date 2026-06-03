<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file1 = __DIR__ . '/jpph23_1.xls';
$file2 = __DIR__ . '/pph23_february.xls';

$s1 = IOFactory::load($file1);
$sheet1 = $s1->getActiveSheet();
$rows1 = $sheet1->toArray(null, true, false, true);

$s2 = IOFactory::load($file2);
$sheet2 = $s2->getActiveSheet();
$rows2 = $sheet2->toArray(null, true, false, true);

echo "=== Row 2 ===\n";
echo "FoxPro (Column C - invoice_bi): '" . $sheet1->getCell('C2')->getValue() . "' (Formatted: '" . $sheet1->getCell('C2')->getFormattedValue() . "')\n";
echo "New Program (Column C - invoice_bi): '" . $sheet2->getCell('C2')->getValue() . "' (Formatted: '" . $sheet2->getCell('C2')->getFormattedValue() . "')\n";

echo "\nFoxPro (Column I - date): '" . $sheet1->getCell('I2')->getValue() . "' (Formatted: '" . $sheet1->getCell('I2')->getFormattedValue() . "')\n";
echo "New Program (Column I - date): '" . $sheet2->getCell('I2')->getValue() . "' (Formatted: '" . $sheet2->getCell('I2')->getFormattedValue() . "')\n";

echo "\n=== Let's inspect the cell format of FoxPro C2 ===\n";
$cell = $sheet1->getCell('C2');
echo "DataType: " . $cell->getDataType() . "\n";
echo "NumberFormat: " . $sheet1->getStyle('C2')->getNumberFormat()->getFormatCode() . "\n";
