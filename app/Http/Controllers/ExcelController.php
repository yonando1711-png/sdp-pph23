<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ExcelImportService;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ExcelController extends Controller
{
    protected ExcelImportService $excelService;

    public function __construct(ExcelImportService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Handle Odoo Excel sheet upload — parse raw rows and save to DB.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $tempPath = $file->getRealPath();

            // Parse raw rows from Excel
            $rawRows = $this->excelService->parseRaw($tempPath);

            if (empty($rawRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found in the uploaded file.',
                ], 422);
            }

            $batchId = Str::uuid()->toString();
            JournalEntry::truncate();

            \Illuminate\Support\Facades\DB::transaction(function () use ($rawRows, $batchId) {
                foreach ($rawRows as $row) {
                    JournalEntry::create(array_merge($row, [
                        'batch_id' => $batchId,
                        'source'   => 'excel',
                    ]));
                }
            });

            return response()->json([
                'success'  => true,
                'message'  => 'Excel file imported successfully',
                'count'    => count($rawRows),
                'batch_id' => $batchId,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error parsing Excel: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process stored raw journal entries into PPh 23 calculated results.
     */
    public function process(): JsonResponse
    {
        $entries = JournalEntry::orderBy('id')->get();

        if ($entries->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No imported data to process. Please import data first.',
            ], 422);
        }

        // Convert DB rows to array format for processing
        $rawRows = $entries->map(function ($entry) {
            return [
                'partner_tax_id'         => $entry->partner_tax_id,
                'partner_id_tku'         => $entry->partner_id_tku,
                'invoice_bill_date'      => $entry->invoice_bill_date,
                'invoice_lines_taxes_id' => $entry->invoice_lines_taxes_id,
                'invoice_lines_taxes'    => $entry->invoice_lines_taxes,
                'invoice_lines_amount'   => $entry->invoice_lines_amount,
                'reference'              => $entry->reference,
                'payment_reference'      => $entry->payment_reference,
                'date'                   => $entry->date,
                'journal_items_account'  => $entry->journal_items_account,
                'journal_items_amount'   => $entry->journal_items_amount,
                'number'                 => $entry->number,
                'partner_display_name'   => $entry->partner_display_name,
            ];
        })->toArray();

        $processed = $this->excelService->processRawRows($rawRows);

        return response()->json([
            'success' => true,
            'message' => 'PPh 23 calculation completed',
            'count'   => count($processed),
            'data'    => $processed,
        ]);
    }

    /**
     * Export processed PPh 23 entries to XLSX, XLS, or CSV.
     */
    public function export(Request $request)
    {
        $format = strtolower($request->query('format', 'xlsx'));
        if (!in_array($format, ['xlsx', 'xls', 'csv'])) {
            $format = 'xlsx';
        }

        $entries = JournalEntry::orderBy('id')->get();

        if ($entries->isEmpty()) {
            abort(404, 'No imported data to export.');
        }

        $rawRows = $entries->map(function ($entry) {
            return [
                'partner_tax_id'         => $entry->partner_tax_id,
                'partner_id_tku'         => $entry->partner_id_tku,
                'invoice_bill_date'      => $entry->invoice_bill_date,
                'invoice_lines_taxes_id' => $entry->invoice_lines_taxes_id,
                'invoice_lines_taxes'    => $entry->invoice_lines_taxes,
                'invoice_lines_amount'   => $entry->invoice_lines_amount,
                'reference'              => $entry->reference,
                'payment_reference'      => $entry->payment_reference,
                'date'                   => $entry->date,
                'journal_items_account'  => $entry->journal_items_account,
                'journal_items_amount'   => $entry->journal_items_amount,
                'number'                 => $entry->number,
                'partner_display_name'   => $entry->partner_display_name,
            ];
        })->toArray();

        $processed = $this->excelService->processRawRows($rawRows);

        // Apply filters to match frontend UI state
        $search = $request->query('search');
        $status = strtolower($request->query('status', 'all'));

        if ($search !== null && $search !== '') {
            $searchLower = strtolower($search);
            $processed = array_filter($processed, function ($item) use ($searchLower) {
                return (isset($item['partner']) && str_contains(strtolower($item['partner']), $searchLower)) ||
                       (isset($item['number']) && str_contains(strtolower($item['number']), $searchLower)) ||
                       (isset($item['reference']) && str_contains(strtolower($item['reference']), $searchLower)) ||
                       (isset($item['tax_id']) && str_contains(strtolower($item['tax_id']), $searchLower));
            });
        }

        if ($status === 'correct') {
            $processed = array_filter($processed, function ($item) {
                return isset($item['is_correct']) && $item['is_correct'] === true;
            });
        } elseif ($status === 'incorrect') {
            $processed = array_filter($processed, function ($item) {
                return isset($item['is_correct']) && $item['is_correct'] === false;
            });
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers matching FoxPro layout
        $headers = [
            'partner_ta', 'partner_id', 'invoice_bi', 'invoice_li', 'invoice_l2',
            'invoice_l3', 'reference', 'payment_re', 'date', 'journal_it',
            'journal_i2', 'number', 'partner_di', 'dasar', 'pph23'
        ];

        foreach ($headers as $colIndex => $header) {
            $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colString . '1', $header);
        }

        $rowIndex = 2;
        foreach ($processed as $item) {
            // Column A (partner_ta)
            $partnerTa = $item['partner_ta'];
            if ($partnerTa === null || $partnerTa === '' || $partnerTa === 0 || $partnerTa === '0') {
                $partnerTa = '';
            } else {
                $partnerTa = trim((string) $partnerTa);
            }

            // Column B (partner_id)
            $partnerId = $item['partner_id'];
            if ($partnerId === null || $partnerId === '' || $partnerId === 0 || $partnerId === '0') {
                $partnerId = '';
            } else {
                $partnerId = str_pad(trim((string) $partnerId), 22, '0', STR_PAD_RIGHT);
            }

            // Column C (invoice_bi)
            $invoiceBi = null;
            $rawInvoiceBi = $item['invoice_bi'];
            if ($rawInvoiceBi !== null && $rawInvoiceBi !== '') {
                if (is_numeric($rawInvoiceBi)) {
                    $invoiceBi = (float) $rawInvoiceBi;
                } else {
                    $ts = strtotime((string) $rawInvoiceBi);
                    if ($ts !== false) {
                        $invoiceBi = (float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts);
                    } else {
                        $invoiceBi = $rawInvoiceBi;
                    }
                }
            }

            // Column D (invoice_li)
            $invoiceLi = $item['invoice_li'] !== null ? (string) $item['invoice_li'] : '';

            // Column E (invoice_l2)
            $invoiceL2 = $item['invoice_l2'] !== null ? (string) $item['invoice_l2'] : '';

            // Column F (invoice_l3)
            $invoiceL3 = $item['invoice_l3'] !== null ? (float) $item['invoice_l3'] : (float) $item['dpp'];

            // Column G (reference)
            $reference = $item['reference'] !== null ? (string) $item['reference'] : '';

            // Column H (payment_re)
            $paymentRe = $item['payment_re'] !== null ? (string) $item['payment_re'] : '';

            // Column I (date)
            $dateVal = null;
            $rawDateVal = $item['date'];
            if ($rawDateVal !== null && $rawDateVal !== '') {
                if (is_numeric($rawDateVal)) {
                    $dateVal = (float) $rawDateVal;
                } else {
                    $ts = strtotime((string) $rawDateVal);
                    if ($ts !== false) {
                        $dateVal = (float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts);
                    } else {
                        $dateVal = $rawDateVal;
                    }
                }
            }

            // Column J (journal_it)
            $journalIt = $item['journal_it'] !== null ? (string) $item['journal_it'] : '';

            // Column K (journal_i2)
            $journalI2 = $item['journal_i2'] !== null ? (float) $item['journal_i2'] : (float) $item['dpp'];

            // Column L (number)
            $number = $item['number'] !== null ? (string) $item['number'] : '';

            // Column M (partner_di)
            $partnerDi = $item['partner_di'] !== null ? (string) $item['partner_di'] : '';

            // Column N (dasar)
            $dasar = (float) $item['dpp'];

            // Column O (pph23)
            $pph23Val = (float) $item['pph23'];

            $row = [
                $partnerTa,
                $partnerId,
                $invoiceBi,
                $invoiceLi,
                $invoiceL2,
                $invoiceL3,
                $reference,
                $paymentRe,
                $dateVal,
                $journalIt,
                $journalI2,
                $number,
                $partnerDi,
                $dasar,
                $pph23Val
            ];

            foreach ($row as $colIndex => $value) {
                $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $isDateCol = ($colIndex === 2 || $colIndex === 8); // C is 2, I is 8 (0-based)
                $isNumericCol = ($colIndex === 5 || $colIndex === 10 || $colIndex === 13 || $colIndex === 14); // F, K, N, O
                
                if ($isDateCol) {
                    if (is_numeric($value)) {
                        $sheet->setCellValue($colString . $rowIndex, (float)$value);
                    } else {
                        $sheet->setCellValueExplicit($colString . $rowIndex, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                } elseif ($isNumericCol) {
                    $sheet->setCellValue($colString . $rowIndex, (float)$value);
                } else {
                    $sheet->setCellValueExplicit($colString . $rowIndex, (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $rowIndex++;
        }

        if ($rowIndex > 2) {
            $lastRow = $rowIndex - 1;
            $sheet->getStyle('C2:C' . $lastRow)->getNumberFormat()->setFormatCode('m/d/yyyy');
            $sheet->getStyle('I2:I' . $lastRow)->getNumberFormat()->setFormatCode('m/d/yyyy');
        }

        // Detect the month of the date range from the records
        $monthName = 'data';
        foreach ($processed as $item) {
            $dateVal = $item['date'] ?? $item['invoice_bi'] ?? null;
            if ($dateVal !== null && $dateVal !== '') {
                if (is_numeric($dateVal)) {
                    try {
                        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$dateVal);
                        $monthName = strtolower($dt->format('F'));
                        break;
                    } catch (\Exception $e) {
                        // ignore and continue
                    }
                } else {
                    $ts = strtotime((string)$dateVal);
                    if ($ts !== false) {
                        $monthName = strtolower(date('F', $ts));
                        break;
                    }
                }
            }
        }

        $filename = 'pph23_' . $monthName . '.' . $format;
        if ($format === 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($format === 'xls') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
            $contentType = 'application/vnd.ms-excel';
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $contentType = 'text/csv';
        }

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export processed PPh 23 entries using the CLEANUP layout (SHEET.1 layout).
     */
    public function exportCleanup(Request $request)
    {
        $format = strtolower($request->query('format', 'xlsx'));
        if (!in_array($format, ['xlsx', 'xls', 'csv'])) {
            $format = 'xlsx';
        }

        $entries = JournalEntry::orderBy('id')->get();

        if ($entries->isEmpty()) {
            abort(404, 'No imported data to export.');
        }

        $rawRows = $entries->map(function ($entry) {
            return [
                'partner_tax_id'         => $entry->partner_tax_id,
                'partner_id_tku'         => $entry->partner_id_tku,
                'invoice_bill_date'      => $entry->invoice_bill_date,
                'invoice_lines_taxes_id' => $entry->invoice_lines_taxes_id,
                'invoice_lines_taxes'    => $entry->invoice_lines_taxes,
                'invoice_lines_amount'   => $entry->invoice_lines_amount,
                'reference'              => $entry->reference,
                'payment_reference'      => $entry->payment_reference,
                'date'                   => $entry->date,
                'journal_items_account'  => $entry->journal_items_account,
                'journal_items_amount'   => $entry->journal_items_amount,
                'number'                 => $entry->number,
                'partner_display_name'   => $entry->partner_display_name,
            ];
        })->toArray();

        $processed = $this->excelService->processRawRows($rawRows);

        // Apply filters to match frontend UI state
        $search = $request->query('search');
        $status = strtolower($request->query('status', 'all'));

        if ($search !== null && $search !== '') {
            $searchLower = strtolower($search);
            $processed = array_filter($processed, function ($item) use ($searchLower) {
                return (isset($item['partner']) && str_contains(strtolower($item['partner']), $searchLower)) ||
                       (isset($item['number']) && str_contains(strtolower($item['number']), $searchLower)) ||
                       (isset($item['reference']) && str_contains(strtolower($item['reference']), $searchLower)) ||
                       (isset($item['tax_id']) && str_contains(strtolower($item['tax_id']), $searchLower));
            });
        }

        if ($status === 'correct') {
            $processed = array_filter($processed, function ($item) {
                return isset($item['is_correct']) && $item['is_correct'] === true;
            });
        } elseif ($status === 'incorrect') {
            $processed = array_filter($processed, function ($item) {
                return isset($item['is_correct']) && $item['is_correct'] === false;
            });
        }



        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('AP for pph23');

        $headers1 = [
            'partner_ta', 'partner_id', 'invoice_bi', 'invoice_li', 'invoice_l2',
            'invoice_l3', 'reference', 'payment_re', 'date', 'journal_it',
            'journal_i2', 'number', 'partner_di', 'dasar', 'pph23'
        ];

        foreach ($headers1 as $colIndex => $header) {
            $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet1->setCellValue($colString . '1', $header);
        }

        $rowIndex1 = 2;
        foreach ($processed as $item) {
            // Column A (partner_ta)
            $partnerTa = $item['partner_ta'];
            if ($partnerTa === null || $partnerTa === '' || $partnerTa === 0 || $partnerTa === '0') {
                $partnerTa = '';
            } else {
                $partnerTa = trim((string) $partnerTa);
            }

            // Column B (partner_id)
            $partnerId = $item['partner_id'];
            if ($partnerId === null || $partnerId === '' || $partnerId === 0 || $partnerId === '0') {
                $partnerId = '';
            } else {
                $partnerId = str_pad(trim((string) $partnerId), 22, '0', STR_PAD_RIGHT);
            }

            // Column C (invoice_bi)
            $invoiceBi = null;
            $rawInvoiceBi = $item['invoice_bi'];
            if ($rawInvoiceBi !== null && $rawInvoiceBi !== '') {
                if (is_numeric($rawInvoiceBi)) {
                    $invoiceBi = (float) $rawInvoiceBi;
                } else {
                    $ts = strtotime((string) $rawInvoiceBi);
                    if ($ts !== false) {
                        $invoiceBi = (float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts);
                    } else {
                        $invoiceBi = $rawInvoiceBi;
                    }
                }
            }

            // Column D (invoice_li)
            $invoiceLi = $item['invoice_li'] !== null ? (string) $item['invoice_li'] : '';

            // Column E (invoice_l2)
            $invoiceL2 = $item['invoice_l2'] !== null ? (string) $item['invoice_l2'] : '';

            // Column F (invoice_l3)
            $invoiceL3 = $item['invoice_l3'] !== null ? (float) $item['invoice_l3'] : (float) $item['dpp'];

            // Column G (reference)
            $reference = $item['reference'] !== null ? (string) $item['reference'] : '';

            // Column H (payment_re)
            $paymentRe = $item['payment_re'] !== null ? (string) $item['payment_re'] : '';

            // Column I (date)
            $dateVal = null;
            $rawDateVal = $item['date'];
            if ($rawDateVal !== null && $rawDateVal !== '') {
                if (is_numeric($rawDateVal)) {
                    $dateVal = (float) $rawDateVal;
                } else {
                    $ts = strtotime((string) $rawDateVal);
                    if ($ts !== false) {
                        $dateVal = (float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts);
                    } else {
                        $dateVal = $rawDateVal;
                    }
                }
            }

            // Column J (journal_it)
            $journalIt = $item['journal_it'] !== null ? (string) $item['journal_it'] : '';

            // Column K (journal_i2)
            $journalI2 = $item['journal_i2'] !== null ? (float) $item['journal_i2'] : (float) $item['dpp'];

            // Column L (number)
            $number = $item['number'] !== null ? (string) $item['number'] : '';

            // Column M (partner_di)
            $partnerDi = $item['partner_di'] !== null ? (string) $item['partner_di'] : '';

            // Column N (dasar)
            $dasar = (float) $item['dpp'];

            // Column O (pph23)
            $pph23Val = (float) $item['pph23'];

            $row = [
                $partnerTa, $partnerId, $invoiceBi, $invoiceLi, $invoiceL2, $invoiceL3,
                $reference, $paymentRe, $dateVal, $journalIt, $journalI2, $number,
                $partnerDi, $dasar, $pph23Val
            ];

            foreach ($row as $colIndex => $value) {
                $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $isDateCol = ($colIndex === 2 || $colIndex === 8);
                $isNumericCol = ($colIndex === 5 || $colIndex === 10 || $colIndex === 13 || $colIndex === 14);
                
                if ($isDateCol) {
                    if (is_numeric($value)) {
                        $sheet1->setCellValue($colString . $rowIndex1, (float)$value);
                    } else {
                        $sheet1->setCellValueExplicit($colString . $rowIndex1, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                } elseif ($isNumericCol) {
                    $sheet1->setCellValue($colString . $rowIndex1, (float)$value);
                } else {
                    $sheet1->setCellValueExplicit($colString . $rowIndex1, (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $rowIndex1++;
        }

        if ($rowIndex1 > 2) {
            $lastRow = $rowIndex1 - 1;
            $sheet1->getStyle('C2:C' . $lastRow)->getNumberFormat()->setFormatCode('m/d/yyyy');
            $sheet1->getStyle('I2:I' . $lastRow)->getNumberFormat()->setFormatCode('m/d/yyyy');
        }

        // --- Create Second Sheet (SHEET.1) ---
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('SHEET.1');

        $headers2 = [
            'MONTH', 'KODE SUPPLIER', 'NAMA SUPPLIER', 'INVOICE SUPPLIER', 'tgl inv',
            '16 DIGIT', '22 DIGIT', 'TAX NO', 'JASA', '', 'PPH 23', '', ''
        ];

        foreach ($headers2 as $colIndex => $header) {
            $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet2->setCellValue($colString . '1', $header);
            
            // Set bold for all headers
            $sheet2->getStyle($colString . '1')->getFont()->setBold(true);
            
            // Apply green background for specific columns
            $greenColumns = ['A', 'B', 'C', 'D', 'E', 'H', 'J', 'L'];
            if (in_array($colString, $greenColumns)) {
                $sheet2->getStyle($colString . '1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF99FF66');
            }
        }

        // Sort data specifically for SHEET.1 by KODE SUPPLIER (partner_di)
        $processedSorted = $processed;
        usort($processedSorted, function($a, $b) {
            $ka = $a['partner_di'] ?? '';
            $kb = $b['partner_di'] ?? '';
            return strcmp($ka, $kb);
        });

        $rowIndex = 2;
        $counter = 1;
        foreach ($processedSorted as $item) {
            $number = $item['number'] ?? '';
            $partnerDi = $item['partner_di'] ?? '';
            $reference = $item['reference'] ?? '';
            $partnerTa = $item['partner_ta'] ?? '';
            
            // Format partner_id
            $partnerId = $item['partner_id'];
            if ($partnerId === null || $partnerId === '' || $partnerId === 0 || $partnerId === '0') {
                $partnerId = '';
            } else {
                $partnerId = str_pad(trim((string) $partnerId), 22, '0', STR_PAD_RIGHT);
            }

            // Date
            $dateVal = null;
            $rawDateVal = $item['date'];
            if ($rawDateVal !== null && $rawDateVal !== '') {
                if (is_numeric($rawDateVal)) {
                    $dateVal = (float) $rawDateVal;
                } else {
                    $ts = strtotime((string) $rawDateVal);
                    if ($ts !== false) {
                        $dateVal = (float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts);
                    } else {
                        $dateVal = $rawDateVal;
                    }
                }
            }

            $dasar = (float) $item['dpp'];
            $pph23Val = (float) $item['pph23'];

            $row = [
                $number,           // A: MONTH
                $partnerDi,        // B: KODE SUPPLIER
                $partnerDi,        // C: NAMA SUPPLIER
                $reference,        // D: INVOICE SUPPLIER
                $dateVal,          // E: tgl inv
                $partnerTa,        // F: 16 DIGIT
                $partnerId,        // G: 22 DIGIT
                $reference,        // H: TAX NO
                $dasar,            // I: JASA
                $dasar,            // J: (empty header duplicate)
                $pph23Val,         // K: PPH 23
                $pph23Val,         // L: (empty header duplicate)
                $counter           // M: counter
            ];

            foreach ($row as $colIndex => $value) {
                $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $isDateCol = ($colIndex === 4); // E
                $isNumericCol = ($colIndex === 8 || $colIndex === 9 || $colIndex === 10 || $colIndex === 11 || $colIndex === 12); // I, J, K, L, M
                
                if ($isDateCol) {
                    if (is_numeric($value)) {
                        $sheet2->setCellValue($colString . $rowIndex, (float)$value);
                    } else {
                        $sheet2->setCellValueExplicit($colString . $rowIndex, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                } elseif ($isNumericCol) {
                    $sheet2->setCellValue($colString . $rowIndex, (float)$value);
                } else {
                    $sheet2->setCellValueExplicit($colString . $rowIndex, (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

                // Highlight cell in yellow if it is exactly 0 or blank
                if ($value === 0 || $value === 0.0 || $value === '0' || $value === '') {
                    $sheet2->getStyle($colString . $rowIndex)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFFFF00');
                }
            }
            $rowIndex++;
            $counter++;
        }

        if ($rowIndex > 2) {
            $lastRow = $rowIndex - 1;
            $sheet2->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('m/d/yyyy');
        }

        // Set active sheet to the first one
        $spreadsheet->setActiveSheetIndex(0);

        // Detect month
        $monthName = 'data';
        foreach ($processed as $item) {
            $dateVal = $item['date'] ?? $item['invoice_bi'] ?? null;
            if ($dateVal !== null && $dateVal !== '') {
                if (is_numeric($dateVal)) {
                    try {
                        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$dateVal);
                        $monthName = strtolower($dt->format('F'));
                        break;
                    } catch (\Exception $e) { }
                } else {
                    $ts = strtotime((string)$dateVal);
                    if ($ts !== false) {
                        $monthName = strtolower(date('F', $ts));
                        break;
                    }
                }
            }
        }

        $filename = 'cleanup_' . $monthName . '.' . $format;
        if ($format === 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($format === 'xls') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
            $contentType = 'application/vnd.ms-excel';
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $contentType = 'text/csv';
        }

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
