<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('d:\project_sdp\pph23_converter\processed_excel.xls');
$sheet = $spreadsheet->getSheet(0);

$cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];

$output = [];
foreach ($cols as $col) {
    $cell = $sheet->getCell($col . '1');
    $style = $sheet->getStyle($col . '1');
    $fill = $style->getFill();
    $font = $style->getFont();
    
    $color = $fill->getStartColor()->getRGB();
    $bold = $font->getBold();
    $value = $cell->getValue();
    
    $output[] = "$col ($value): Color=$color, Bold=" . ($bold ? 'yes' : 'no');
}

echo implode("\n", $output);
