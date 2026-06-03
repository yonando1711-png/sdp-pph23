<?php

namespace App\Services;

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use PhpXmlRpc\Encoder;
use Exception;
use Illuminate\Support\Facades\Log;

class OdooXmlrpcService
{
    private Encoder $encoder;

    public function __construct()
    {
        $this->encoder = new Encoder();
    }

    /**
     * Authenticate and return user ID.
     */
    public function authenticate(string $url, string $db, string $username, string $password): ?int
    {
        try {
            $client = new Client($this->formatUrl($url) . "/xmlrpc/2/common");
            $client->setSSLVerifyPeer(false);
            $client->setSSLVerifyHost(false);

            $req = new Request('authenticate', [
                $this->encoder->encode($db),
                $this->encoder->encode($username),
                $this->encoder->encode($password),
                $this->encoder->encode(array())
            ]);

            $resp = $client->send($req);
            
            if ($resp->faultCode()) {
                Log::error("Odoo Auth Fault: " . $resp->faultString());
                return null;
            }

            $uid = $resp->value()->scalarval();
            return is_int($uid) ? $uid : null;
        } catch (Exception $e) {
            Log::error("Odoo Auth Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch and compute PPh 23 entries from Odoo.
     */
    public function fetchPph23Entries(
        string $url,
        string $db,
        string $username,
        string $password,
        int $uid,
        string $startDate,
        string $endDate
    ): array {
        $objectClient = new Client($this->formatUrl($url) . "/xmlrpc/2/object");
        $objectClient->setSSLVerifyPeer(false);
        $objectClient->setSSLVerifyHost(false);

        // 1. Search for vendor bills (move_type = 'in_invoice') in date range
        $domain = [
            [$this->encoder->encode('move_type'), $this->encoder->encode('='), $this->encoder->encode('in_invoice')],
            [$this->encoder->encode('state'), $this->encoder->encode('='), $this->encoder->encode('posted')],
            [$this->encoder->encode('invoice_line_ids.tax_ids.name'), $this->encoder->encode('ilike'), $this->encoder->encode('2% PPH 23 (EXCL)')],
            [$this->encoder->encode('date'), $this->encoder->encode('>='), $this->encoder->encode($startDate)],
            [$this->encoder->encode('date'), $this->encoder->encode('<='), $this->encoder->encode($endDate)],
        ];

        $fields = [
            'id', 'name', 'ref', 'payment_reference', 'invoice_date', 'date', 
            'partner_id', 'invoice_line_ids', 'line_ids'
        ];

        $moves = $this->executeKw(
            $objectClient, $db, $uid, $password, 
            'account.move', 'search_read', 
            [$domain], 
            ['fields' => $fields]
        );

        if (empty($moves)) {
            return [];
        }

        // Collect all invoice lines and journal item IDs to fetch them in batch
        $allInvoiceLineIds = [];
        $allJournalItemIds = [];
        foreach ($moves as $move) {
            if (!empty($move['invoice_line_ids'])) {
                $allInvoiceLineIds = array_merge($allInvoiceLineIds, $move['invoice_line_ids']);
            }
            if (!empty($move['line_ids'])) {
                $allJournalItemIds = array_merge($allJournalItemIds, $move['line_ids']);
            }
        }

        // Fetch invoice lines in batch
        $invoiceLinesMap = [];
        if (!empty($allInvoiceLineIds)) {
            $invoiceLines = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'account.move.line', 'read',
                [$allInvoiceLineIds],
                ['fields' => ['id', 'tax_ids', 'price_subtotal']]
            );
            foreach ($invoiceLines as $line) {
                $invoiceLinesMap[$line['id']] = $line;
            }
        }

        // Fetch journal items in batch
        $journalItemsMap = [];
        if (!empty($allJournalItemIds)) {
            $journalItems = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'account.move.line', 'read',
                [$allJournalItemIds],
                ['fields' => ['id', 'account_id', 'credit', 'debit', 'balance']]
            );
            foreach ($journalItems as $item) {
                $journalItemsMap[$item['id']] = $item;
            }
        }

        // Fetch Odoo Tax names (since tax_ids in account.move.line are IDs)
        // Let's get unique tax IDs
        $taxIds = [];
        foreach ($invoiceLinesMap as $line) {
            if (!empty($line['tax_ids'])) {
                $taxIds = array_merge($taxIds, $line['tax_ids']);
            }
        }
        $taxIds = array_unique($taxIds);

        $taxNamesMap = [];
        if (!empty($taxIds)) {
            $taxes = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'account.tax', 'read',
                [array_values($taxIds)],
                ['fields' => ['id', 'name']]
            );
            foreach ($taxes as $tax) {
                $taxNamesMap[$tax['id']] = $tax['name'];
            }
        }

        // Process moves
        $results = [];
        foreach ($moves as $move) {
            $dpp = 0.0;
            $pph23 = 0.0;

            $taxLines = [];
            $journalItems = [];

            // Compute DPP from invoice lines
            if (!empty($move['invoice_line_ids'])) {
                foreach ($move['invoice_line_ids'] as $lineId) {
                    $line = $invoiceLinesMap[$lineId] ?? null;
                    if ($line) {
                        $taxNames = [];
                        if (!empty($line['tax_ids'])) {
                            foreach ($line['tax_ids'] as $tId) {
                                $taxNames[] = $taxNamesMap[$tId] ?? '';
                            }
                        }
                        $taxDesc = implode(', ', $taxNames);
                        $taxLines[] = [
                            'desc' => $taxDesc,
                            'amount' => (float)($line['price_subtotal'] ?? 0.0)
                        ];

                        if (stripos($taxDesc, 'PPH 23') !== false) {
                            $dpp += (float)($line['price_subtotal'] ?? 0.0);
                        }
                    }
                }
            }

            // Compute PPh 23 tax liability from journal items
            if (!empty($move['line_ids'])) {
                foreach ($move['line_ids'] as $itemId) {
                    $item = $journalItemsMap[$itemId] ?? null;
                    if ($item) {
                        $acctName = is_array($item['account_id']) ? $item['account_id'][1] : '';
                        $acctCode = is_array($item['account_id']) ? $item['account_id'][0] : '';
                        
                        $journalItems[] = [
                            'account' => $acctName,
                            'amount' => (float)($item['balance'] ?? 0.0)
                        ];

                        if (str_starts_with($acctName, '212003') || str_starts_with($acctCode, '212003')) {
                            // In Odoo, credit is positive on database balance but negative on displays,
                            // or balance is negative for credit. Let's make it positive.
                            $pph23 += ((float)($item['balance'] ?? 0.0) * -1.0);
                        }
                    }
                }
            }

            // Only keep if contains PPh 23 or DPP base
            if ($dpp > 0 || $pph23 > 0) {
                // Get partner info
                $partnerName = is_array($move['partner_id']) ? $move['partner_id'][1] : '';
                
                // Read partner's Tax ID (VAT) and ID TKU
                $partnerId = is_array($move['partner_id']) ? $move['partner_id'][0] : null;
                $taxId = null;
                $tkuNumber = null;
                if ($partnerId) {
                    $partnerData = $this->executeKw(
                        $objectClient, $db, $uid, $password,
                        'res.partner', 'read',
                        [[$partnerId]],
                        ['fields' => ['vat', 'tku_number']]
                    );
                    $taxId = $partnerData[0]['vat'] ?? null;
                    $tkuNumber = $partnerData[0]['tku_number'] ?? null;
                }

                // Reference details
                $ref = $move['ref'] ?: '';
                $payRef = $move['payment_reference'] ?: '';
                $cleanedRef = $ref;
                if (!empty($payRef) && str_starts_with($ref, $payRef)) {
                    $cleanedRef = trim(substr($ref, strlen($payRef)));
                }

                // Calculations correct check
                $expectedTax = round($dpp * 0.02);
                $diff = abs($pph23 - $expectedTax);
                $isCorrect = ($diff <= 5.0);

                $firstTaxDesc = !empty($taxLines) ? $taxLines[0]['desc'] : '';
                $firstTaxAmount = !empty($taxLines) ? $taxLines[0]['amount'] : 0.0;
                $firstAcctName = !empty($journalItems) ? $journalItems[0]['account'] : '';
                
                // Get the account item that has the actual amount (not tax line itself)
                $firstAcctAmount = 0.0;
                foreach ($journalItems as $jItem) {
                    if (strpos($jItem['account'], '212003') === false) {
                        $firstAcctAmount = abs($jItem['amount']);
                        break;
                    }
                }

                $results[] = [
                    'partner_ta' => $taxId,
                    'partner_id' => $tkuNumber ?: $taxId,
                    'invoice_bi' => $move['invoice_date'] ?? null,
                    'invoice_li' => '10', // Default Odoo Tax ID
                    'invoice_l2' => $firstTaxDesc,
                    'invoice_l3' => $firstTaxAmount,
                    'reference' => $cleanedRef,
                    'payment_re' => $payRef,
                    'date' => $move['date'] ?? null,
                    'journal_it' => $firstAcctName,
                    'journal_i2' => $firstAcctAmount ?: $dpp,
                    'number' => $move['name'] ?? '',
                    'partner_di' => $partnerName,
                    
                    // For dashboard UI grid convenience
                    'partner' => $partnerName,
                    'tax_id' => $taxId,
                    'cleaned_reference' => $cleanedRef,
                    'dpp' => $dpp,
                    'pph23' => $pph23,
                    'is_correct' => $isCorrect,
                    'difference' => $diff
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch raw journal entry rows from Odoo matching the Excel export structure.
     * Returns one row per invoice line / journal item combination — same shape as parseRaw().
     */
    public function fetchRawJournalEntries(
        string $url,
        string $db,
        string $username,
        string $password,
        int $uid,
        string $startDate,
        string $endDate
    ): array {
        $objectClient = new Client($this->formatUrl($url) . "/xmlrpc/2/object");
        $objectClient->setSSLVerifyPeer(false);
        $objectClient->setSSLVerifyHost(false);

        // Search for vendor bills in date range
        $domain = [
            [$this->encoder->encode('move_type'), $this->encoder->encode('='), $this->encoder->encode('in_invoice')],
            [$this->encoder->encode('state'), $this->encoder->encode('='), $this->encoder->encode('posted')],
            [$this->encoder->encode('invoice_line_ids.tax_ids.name'), $this->encoder->encode('ilike'), $this->encoder->encode('2% PPH 23 (EXCL)')],
            [$this->encoder->encode('date'), $this->encoder->encode('>='), $this->encoder->encode($startDate)],
            [$this->encoder->encode('date'), $this->encoder->encode('<='), $this->encoder->encode($endDate)],
        ];

        $fields = [
            'id', 'name', 'ref', 'payment_reference', 'invoice_date', 'date',
            'partner_id', 'invoice_line_ids', 'line_ids'
        ];

        $moves = $this->executeKw(
            $objectClient, $db, $uid, $password,
            'account.move', 'search_read',
            [$domain],
            ['fields' => $fields]
        );

        if (empty($moves)) {
            return [];
        }

        // Collect all line IDs for batch fetch
        $allInvoiceLineIds = [];
        $allJournalItemIds = [];
        foreach ($moves as $move) {
            if (!empty($move['invoice_line_ids'])) {
                $allInvoiceLineIds = array_merge($allInvoiceLineIds, $move['invoice_line_ids']);
            }
            if (!empty($move['line_ids'])) {
                $allJournalItemIds = array_merge($allJournalItemIds, $move['line_ids']);
            }
        }

        // Fetch invoice lines
        $invoiceLinesMap = [];
        if (!empty($allInvoiceLineIds)) {
            $invoiceLines = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'account.move.line', 'read',
                [$allInvoiceLineIds],
                ['fields' => ['id', 'tax_ids', 'price_subtotal']]
            );
            foreach ($invoiceLines as $line) {
                $invoiceLinesMap[$line['id']] = $line;
            }
        }

        // Fetch journal items
        $journalItemsMap = [];
        if (!empty($allJournalItemIds)) {
            $journalItems = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'account.move.line', 'read',
                [$allJournalItemIds],
                ['fields' => ['id', 'account_id', 'credit', 'debit', 'balance']]
            );
            foreach ($journalItems as $item) {
                $journalItemsMap[$item['id']] = $item;
            }
        }

        // Fetch tax names
        $taxIds = [];
        foreach ($invoiceLinesMap as $line) {
            if (!empty($line['tax_ids'])) {
                $taxIds = array_merge($taxIds, $line['tax_ids']);
            }
        }
        $taxIds = array_unique($taxIds);

        $taxNamesMap = [];
        if (!empty($taxIds)) {
            $taxes = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'account.tax', 'read',
                [array_values($taxIds)],
                ['fields' => ['id', 'name']]
            );
            foreach ($taxes as $tax) {
                $taxNamesMap[$tax['id']] = $tax['name'];
            }
        }

        // Fetch partner VAT and TKU info in batch
        $partnerIds = [];
        foreach ($moves as $move) {
            if (is_array($move['partner_id']) && !empty($move['partner_id'][0])) {
                $partnerIds[] = $move['partner_id'][0];
            }
        }
        $partnerIds = array_unique($partnerIds);

        $partnerVatMap = [];
        $partnerTkuMap = [];
        if (!empty($partnerIds)) {
            $partners = $this->executeKw(
                $objectClient, $db, $uid, $password,
                'res.partner', 'read',
                [array_values($partnerIds)],
                ['fields' => ['id', 'vat', 'tku_number']]
            );
            foreach ($partners as $p) {
                $partnerVatMap[$p['id']] = $p['vat'] ?? null;
                $partnerTkuMap[$p['id']] = $p['tku_number'] ?? null;
            }
        }

        // Build raw rows — each move produces multiple rows (one per invoice line + one per journal item)
        $rawRows = [];

        foreach ($moves as $move) {
            $partnerName = is_array($move['partner_id']) ? $move['partner_id'][1] : '';
            $partnerId = is_array($move['partner_id']) ? $move['partner_id'][0] : null;
            $taxId = $partnerId ? ($partnerVatMap[$partnerId] ?? null) : null;
            $tkuId = $partnerId ? ($partnerTkuMap[$partnerId] ?? null) : null;
            $ref = $move['ref'] ?: '';
            $payRef = $move['payment_reference'] ?: '';

            $isFirstRow = true;

            // Create rows from invoice lines
            if (!empty($move['invoice_line_ids'])) {
                foreach ($move['invoice_line_ids'] as $lineId) {
                    $line = $invoiceLinesMap[$lineId] ?? null;
                    if (!$line) continue;

                    $taxNames = [];
                    if (!empty($line['tax_ids'])) {
                        foreach ($line['tax_ids'] as $tId) {
                            $taxNames[] = $taxNamesMap[$tId] ?? '';
                        }
                    }

                    $rawRows[] = [
                        'partner_tax_id'         => $isFirstRow ? $taxId : null,
                        'partner_id_tku'         => $isFirstRow ? ($tkuId ?: $taxId) : null,
                        'invoice_bill_date'      => $isFirstRow ? ($move['invoice_date'] ?? null) : null,
                        'invoice_lines_taxes_id' => !empty($line['tax_ids']) ? (string) $line['tax_ids'][0] : null,
                        'invoice_lines_taxes'    => implode(', ', $taxNames),
                        'invoice_lines_amount'   => (float) ($line['price_subtotal'] ?? 0.0),
                        'reference'              => $isFirstRow ? $ref : null,
                        'payment_reference'      => $isFirstRow ? $payRef : null,
                        'date'                   => $isFirstRow ? ($move['date'] ?? null) : null,
                        'journal_items_account'  => null,
                        'journal_items_amount'   => null,
                        'number'                 => $isFirstRow ? ($move['name'] ?? '') : null,
                        'partner_display_name'   => $isFirstRow ? $partnerName : null,
                    ];
                    $isFirstRow = false;
                }
            }

            // Create rows from journal items
            if (!empty($move['line_ids'])) {
                foreach ($move['line_ids'] as $itemId) {
                    $item = $journalItemsMap[$itemId] ?? null;
                    if (!$item) continue;

                    $acctName = is_array($item['account_id']) ? $item['account_id'][1] : '';

                    $rawRows[] = [
                        'partner_tax_id'         => $isFirstRow ? $taxId : null,
                        'partner_id_tku'         => $isFirstRow ? ($tkuId ?: $taxId) : null,
                        'invoice_bill_date'      => $isFirstRow ? ($move['invoice_date'] ?? null) : null,
                        'invoice_lines_taxes_id' => null,
                        'invoice_lines_taxes'    => null,
                        'invoice_lines_amount'   => null,
                        'reference'              => $isFirstRow ? $ref : null,
                        'payment_reference'      => $isFirstRow ? $payRef : null,
                        'date'                   => $isFirstRow ? ($move['date'] ?? null) : null,
                        'journal_items_account'  => $acctName,
                        'journal_items_amount'   => (float) ($item['balance'] ?? 0.0),
                        'number'                 => $isFirstRow ? ($move['name'] ?? '') : null,
                        'partner_display_name'   => $isFirstRow ? $partnerName : null,
                    ];
                    $isFirstRow = false;
                }
            }
        }

        return $rawRows;
    }

    /**
     * Format Odoo URL (remove trailing slash)
     */
    private function formatUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    /**
     * Execute direct XML-RPC keywords using phpxmlrpc client.
     */
    private function executeKw(
        Client $client,
        string $db,
        int $uid,
        string $password,
        string $model,
        string $method,
        array $args,
        array $kwargs = []
    ) {
        $req = new Request('execute_kw', [
            $this->encoder->encode($db),
            $this->encoder->encode($uid),
            $this->encoder->encode($password),
            $this->encoder->encode($model),
            $this->encoder->encode($method),
            $this->encoder->encode($args),
            $this->encoder->encode($kwargs)
        ]);

        $resp = $client->send($req);
        
        if ($resp->faultCode()) {
            throw new Exception("Odoo XML-RPC Error on {$model}->{$method}: " . $resp->faultString());
        }

        return $this->encoder->decode($resp->value());
    }
}
