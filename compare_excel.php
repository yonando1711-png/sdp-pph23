<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$cleanupPath = 'd:\project_sdp\pph23_converter\cleanup_may.xls';
$pph23Path = 'd:\project_sdp\pph23_converter\pph23_may.xls';
$processedPath = 'd:\project_sdp\pph23_converter\processed_excel.xls';

function compareFirstSheet($file1, $file2) {
    $s1 = IOFactory::load($file1)->getSheet(0);
    $s2 = IOFactory::load($file2)->getSheet(0);
    
    $highestCol1 = $s1->getHighestDataColumn();
    $highestRow1 = min($s1->getHighestDataRow(), 50); // compare up to 50 rows for speed
    
    $diffs = [];
    for ($row = 1; $row <= $highestRow1; $row++) {
        foreach (range('A', $highestCol1) as $col) {
            $v1 = $s1->getCell($col . $row)->getFormattedValue();
            $v2 = $s2->getCell($col . $row)->getFormattedValue();
            if ($v1 !== $v2) {
                $diffs[] = "Row $row, Col $col: '$file1' has '$v1', but '$file2' has '$v2'";
            }
        }
    }
    return $diffs;
}

function compareSheet1($cleanupFile, $processedFile) {
    $s1 = IOFactory::load($cleanupFile)->getSheetByName('SHEET.1');
    $s2 = IOFactory::load($processedFile)->getSheetByName('SHEET.1');
    
    $diffs = [];
    
    // Check headers style
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];
    foreach ($cols as $col) {
        $c1 = $s1->getCell($col . '1')->getValue();
        $c2 = $s2->getCell($col . '1')->getValue();
        if ($c1 !== $c2 && !($c1 === null && $c2 === '')) {
             $diffs[] = "Header $col differs: Cleanup='$c1' vs Processed='$c2'";
        }
        
        $color1 = $s1->getStyle($col . '1')->getFill()->getStartColor()->getRGB();
        $color2 = $s2->getStyle($col . '1')->getFill()->getStartColor()->getRGB();
        
        $bold1 = $s1->getStyle($col . '1')->getFont()->getBold();
        $bold2 = $s2->getStyle($col . '1')->getFont()->getBold();
        
        // Handle transparency diffs (000000 vs nothing, etc) but roughly we expect FF99FF66
        if ($color1 !== $color2 && !in_array($color1, ['000000', '']) && !in_array($color2, ['000000', ''])) {
             $diffs[] = "Style Header $col Color: Cleanup='$color1', Processed='$color2'";
        }
        if ($bold1 !== $bold2 && $col !== 'M') { // skip M since it wasn't bold in original
             $diffs[] = "Style Header $col Bold: Cleanup='$bold1', Processed='$bold2'";
        }
    }
    
    // Check sorting / data
    $highestRow = min($s1->getHighestDataRow(), 50);
    for ($row = 2; $row <= $highestRow; $row++) {
        foreach ($cols as $col) {
            $v1 = $s1->getCell($col . $row)->getFormattedValue();
            $v2 = $s2->getCell($col . $row)->getFormattedValue();
            
            // Format dates roughly if one is string one is number
            // Let's just compare value
            $val1 = $s1->getCell($col . $row)->getValue();
            $val2 = $s2->getCell($col . $row)->getValue();
            if ($val1 != $val2 && $col !== 'M') { // ignore column M counter because of row shifts
               $diffs[] = "Data Row $row, Col $col: Cleanup='$val1', Processed='$val2'";
            }
        }
    }
    return $diffs;
}

echo "--- COMPARING CLEANUP vs PPH23 FIRST SHEET ---\n";
$diff1 = compareFirstSheet($cleanupPath, $pph23Path);
if (empty($diff1)) echo "No differences found in first sheet!\n";
else {
    echo "Found " . count($diff1) . " differences (showing first 10):\n";
    echo implode("\n", array_slice($diff1, 0, 10)) . "\n";
}

echo "\n--- COMPARING CLEANUP vs PROCESSED_EXCEL SHEET.1 ---\n";
$diff2 = compareSheet1($cleanupPath, $processedPath);
if (empty($diff2)) echo "No differences found in SHEET.1!\n";
else {
    echo "Found " . count($diff2) . " differences (showing first 15):\n";
    echo implode("\n", array_slice($diff2, 0, 15)) . "\n";
}
