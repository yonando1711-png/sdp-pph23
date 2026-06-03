<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class ExcelImportService
{
    /**
     * Parse the Odoo Journal Entry export Excel file and return RAW rows (no grouping).
     * Each row maps to one line in the spreadsheet.
     *
     * @param string $filePath
     * @return array
     * @throws Exception
     */
    public function parseRaw(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, true);

        if (count($rows) < 2) {
            return [];
        }

        // Parse headers
        $headers = array_map('trim', array_values($rows[1]));
        $keys = array_keys($rows[1]);

        // Map header names to indices
        $colMap = [
            'partner_tax_id'         => array_search('Partner/Tax ID', $headers),
            'partner_id_tku'         => array_search('Partner/ID TKU', $headers),
            'invoice_bill_date'      => array_search('Invoice/Bill Date', $headers),
            'invoice_lines_taxes_id' => array_search('Invoice lines/Taxes/ID', $headers),
            'invoice_lines_taxes'    => array_search('Invoice lines/Taxes', $headers),
            'invoice_lines_amount'   => array_search('Invoice lines/Amount in Currency', $headers),
            'reference'              => array_search('Reference', $headers),
            'payment_reference'      => array_search('Payment Reference', $headers),
            'date'                   => array_search('Date', $headers),
            'journal_items_account'  => array_search('Journal Items/Account', $headers),
            'journal_items_amount'   => array_search('Journal Items/Amount in Currency', $headers),
            'number'                 => array_search('Number', $headers),
            'partner_display_name'   => array_search('Partner/Display Name', $headers),
        ];

        $rawRows = [];
        $rowCount = count($rows);

        for ($i = 2; $i <= $rowCount; $i++) {
            $row = $rows[$i];
            $entry = [];

            foreach ($colMap as $field => $idx) {
                if ($idx !== false) {
                    $val = $row[$keys[$idx]] ?? null;
                    $entry[$field] = $val !== null ? trim((string) $val) : null;
                } else {
                    $entry[$field] = null;
                }
            }

            // Cast numeric fields
            if ($entry['invoice_lines_amount'] !== null && $entry['invoice_lines_amount'] !== '') {
                $entry['invoice_lines_amount'] = (float) $entry['invoice_lines_amount'];
            }
            if ($entry['journal_items_amount'] !== null && $entry['journal_items_amount'] !== '') {
                $entry['journal_items_amount'] = (float) $entry['journal_items_amount'];
            }

            // Skip completely empty rows
            $hasData = false;
            foreach ($entry as $v) {
                if ($v !== null && $v !== '' && $v !== '0') {
                    $hasData = true;
                    break;
                }
            }
            if ($hasData) {
                $rawRows[] = $entry;
            }
        }

        return $rawRows;
    }

    /**
     * Process raw journal entry rows into grouped PPh 23 summary.
     * This replicates the FoxPro calculation logic.
     *
     * @param array $rawRows
     * @return array
     */
    public function processRawRows(array $rawRows): array
    {
        $groups = [];
        $currentGroup = null;

        foreach ($rawRows as $row) {
            $ref = $row['reference'] ?? null;

            // If reference is not empty, start a new group
            if ($ref !== null && trim((string) $ref) !== '') {
                if ($currentGroup) {
                    $groups[] = $currentGroup;
                }

                $currentGroup = [
                    'partner_ta' => $row['partner_tax_id'] ?? null,
                    'partner_id' => $row['partner_id_tku'] ?? null,
                    'invoice_bi' => $row['invoice_bill_date'] ?? null,
                    'invoice_li' => $row['invoice_lines_taxes_id'] ?? null,
                    'invoice_l2' => $row['invoice_lines_taxes'] ?? null,
                    'invoice_l3' => $row['invoice_lines_amount'] ?? null,
                    'reference'  => trim((string) $ref),
                    'payment_re' => $row['payment_reference'] ?? null,
                    'date'       => $row['date'] ?? null,
                    'journal_it' => $row['journal_items_account'] ?? null,
                    'journal_i2' => $row['journal_items_amount'] ?? null,
                    'number'     => $row['number'] ?? null,
                    'partner_di' => $row['partner_display_name'] ?? null,
                    'tax_lines'  => [],
                    'journal_items' => []
                ];
            }

            if ($currentGroup) {
                // Add tax line details
                $taxDesc = $row['invoice_lines_taxes'] ?? null;
                $taxBase = $row['invoice_lines_amount'] ?? null;
                if ($taxDesc !== null && trim((string) $taxDesc) !== '') {
                    $currentGroup['tax_lines'][] = [
                        'desc'   => trim((string) $taxDesc),
                        'amount' => $taxBase !== null ? (float) $taxBase : 0.0
                    ];
                }

                // Add journal item details
                $acct = $row['journal_items_account'] ?? null;
                $jAmt = $row['journal_items_amount'] ?? null;
                if ($acct !== null && trim((string) $acct) !== '') {
                    $currentGroup['journal_items'][] = [
                        'account' => trim((string) $acct),
                        'amount'  => $jAmt !== null ? (float) $jAmt : 0.0
                    ];
                }
            }
        }

        if ($currentGroup) {
            $groups[] = $currentGroup;
        }

        $finalized = [];
        $count = count($groups);

        for ($i = 0; $i < $count; $i++) {
            $group = $groups[$i];

            // Clean reference
            $ref = $group['reference'];
            $payRef = $group['payment_re'];
            $cleanedRef = $ref;

            // Replicate FoxPro off-by-one bug: do NOT clean reference for the very last group in the file
            $isLastGroup = ($i === $count - 1);

            if (!$isLastGroup && $payRef !== null && trim((string) $payRef) !== '') {
                $trimmedPayRef = trim((string) $payRef);
                $lw = strlen($trimmedPayRef);
                if ($lw > 0) {
                    // FoxPro slices reference unconditionally based on the length of payment_re
                    if (strlen($ref) > $lw) {
                        $cleanedRef = substr($ref, $lw);
                    } else {
                        $cleanedRef = "";
                    }
                }
            }

            // Replicate FoxPro filter: delete all for empty(reference)
            if (trim($cleanedRef) === '') {
                continue;
            }

            $finalized[] = $this->finalizeGroup($group, $cleanedRef);
        }

        // Filter: Keep only entries that contain PPh 23 values
        return array_values(array_filter($finalized, function ($g) {
            return $g['pph23'] > 0 || $g['dpp'] > 0;
        }));
    }

    /**
     * Original parse method — parse Excel directly into grouped PPh 23 output.
     * Now delegates to parseRaw() + processRawRows().
     *
     * @param string $filePath
     * @return array
     * @throws Exception
     */
    public function parse(string $filePath): array
    {
        $rawRows = $this->parseRaw($filePath);
        return $this->processRawRows($rawRows);
    }

    /**
     * Finalize calculations and format a group.
     */
    private function finalizeGroup(array $group, string $cleanedRef): array
    {
        $dpp = 0.0;
        $pph23 = 0.0;

        // Calculate DPP (tax base)
        foreach ($group['tax_lines'] as $line) {
            if (stripos($line['desc'], 'PPH 23') !== false) {
                $dpp += $line['amount'];
            }
        }

        // Calculate PPh 23 (credit balance from account 212003)
        foreach ($group['journal_items'] as $item) {
            if (str_starts_with($item['account'], '212003')) {
                $pph23 += ($item['amount'] * -1.0);
            }
        }

        // Check if PPh 23 calculation is mathematically correct (should be 2% of DPP base)
        $expectedTax = round($dpp * 0.02);
        $diff = abs($pph23 - $expectedTax);
        $is_correct = ($diff <= 5.0); // Allow +/- 5 IDR for rounding differences

        // Isolate the 2% PPh 23 tax name if present in invoice_l2
        $invoice_l2 = $group['invoice_l2'];
        if ($invoice_l2 !== null && stripos($invoice_l2, 'PPH 23') !== false) {
            $invoice_l2 = '2% PPH 23 (EXCL)';
        }

        // Search the group's journal items for the first non-tax/non-AP expense or accrued account
        $journal_it = null;
        $journal_i2 = null;
        foreach ($group['journal_items'] as $item) {
            $acct = $item['account'];
            if ($acct !== null && $acct !== '') {
                if (!str_starts_with($acct, '212003') && 
                    !str_starts_with($acct, '211') && 
                    !str_starts_with($acct, '117')) {
                    $journal_it = $acct;
                    $journal_i2 = abs((float) $item['amount']);
                    break;
                }
            }
        }

        if ($journal_it === null) {
            $journal_it = $group['journal_it'];
            $journal_i2 = $group['journal_i2'] !== null ? abs((float) $group['journal_i2']) : null;
        }

        return [
            'partner_ta' => $group['partner_ta'],
            'partner_id' => $group['partner_id'],
            'invoice_bi' => $group['invoice_bi'],
            'invoice_li' => $group['invoice_li'],
            'invoice_l2' => $invoice_l2,
            'invoice_l3' => $group['invoice_l3'],
            'reference' => $cleanedRef, // Store the CLEANED reference in the reference column matching FoxPro
            'payment_re' => $group['payment_re'],
            'date' => $group['date'],
            'journal_it' => $journal_it,
            'journal_i2' => $journal_i2,
            'number' => $group['number'],
            'partner_di' => $group['partner_di'],

            // For dashboard UI grid convenience
            'partner' => $group['partner_di'] ?? '',
            'tax_id' => $group['partner_ta'] ?? '',
            'cleaned_reference' => $cleanedRef,
            'dpp' => $dpp,
            'pph23' => $pph23,
            'is_correct' => $is_correct,
            'difference' => $diff
        ];
    }
}
