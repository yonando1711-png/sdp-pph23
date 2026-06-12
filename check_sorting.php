<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('d:\project_sdp\pph23_converter\processed_excel.xls');
$sheet = $spreadsheet->getSheetByName('SHEET.1');

$highestRow = $sheet->getHighestDataRow();
$output = [];
for ($row = 2; $row <= min($highestRow, 30); $row++) {
    $kodeSupplier = $sheet->getCell('B' . $row)->getValue();
    $tglInv = $sheet->getCell('E' . $row)->getValue();
    $formattedDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tglInv)->format('Y-m-d');
    $output[] = "Row $row: Date=$formattedDate | Kode=$kodeSupplier";
}

echo implode("\n", $output);
