<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OdooXmlrpcService;
use App\Services\ExcelImportService;
use App\Models\JournalEntry;
use App\Models\OdooConfig;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OdooController extends Controller
{
    protected OdooXmlrpcService $odooService;
    protected ExcelImportService $excelService;

    public function __construct(OdooXmlrpcService $odooService, ExcelImportService $excelService)
    {
        $this->odooService = $odooService;
        $this->excelService = $excelService;
    }

    /**
     * Test connections to Odoo server.
     */
    public function testConnection(Request $request): JsonResponse
    {
        // Only superadmins can test/save configuration
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'url' => 'required|url',
            'db' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $password = $request->input('password');
        if ($password === '••••••••••••••••') {
            $config = OdooConfig::first();
            $password = $config ? $config->password : '';
        }

        $uid = $this->odooService->authenticate(
            $request->input('url'),
            $request->input('db'),
            $request->input('username'),
            $password
        );

        if ($uid) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully connected to Odoo! User ID: ' . $uid,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to connect. Please check your Odoo URL, Database name, Username/Email, and API Key.',
        ], 401);
    }

    /**
     * Sync raw data from Odoo API — save journal entry rows to DB.
     */
    public function sync(Request $request): JsonResponse
    {
        // Sync is accessible to any user, but relies on stored database config
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $config = OdooConfig::first();
        if (!$config || !$config->url || !$config->db || !$config->username || !$config->password) {
            return response()->json([
                'success' => false,
                'message' => 'Odoo integration is not configured. Please contact a superadmin to configure integration settings on the Utilities page.',
            ], 422);
        }

        $url = $config->url;
        $db = $config->db;
        $username = $config->username;
        $password = $config->password;

        // 1. Authenticate first
        $uid = $this->odooService->authenticate($url, $db, $username, $password);

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed. Please verify credentials.',
            ], 401);
        }

        try {
            // 2. Fetch raw journal entry data from Odoo
            $rawRows = $this->odooService->fetchRawJournalEntries(
                $url, $db, $username, $password, $uid,
                $request->input('start_date'),
                $request->input('end_date')
            );

            if (empty($rawRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vendor bills found for the selected date range.',
                ], 422);
            }

            // 3. Clear previous data and save new batch
            $batchId = Str::uuid()->toString();
            JournalEntry::truncate();

            \Illuminate\Support\Facades\DB::transaction(function () use ($rawRows, $batchId) {
                foreach ($rawRows as $row) {
                    JournalEntry::create(array_merge($row, [
                        'batch_id' => $batchId,
                        'source'   => 'odoo',
                    ]));
                }
            });

            return response()->json([
                'success'  => true,
                'message'  => 'Synced successfully from Odoo',
                'count'    => count($rawRows),
                'batch_id' => $batchId,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Synchronization failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all stored journal entries for Dashboard display.
     */
    public function getEntries(): JsonResponse
    {
        $entries = JournalEntry::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'count'   => $entries->count(),
            'source'  => $entries->first()?->source ?? null,
            'data'    => $entries,
        ]);
    }

    /**
     * Get saved Odoo configuration (API Key masked for security).
     */
    public function getConfig(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $config = OdooConfig::first();

        return response()->json([
            'success' => true,
            'data' => $config ? [
                'url' => $config->url,
                'db' => $config->db,
                'username' => $config->username,
                'has_password' => !empty($config->password),
            ] : null,
        ]);
    }

    /**
     * Save Odoo configuration securely.
     */
    public function saveConfig(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'url' => 'required|url',
            'db' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $config = OdooConfig::first() ?: new OdooConfig();
        $config->url = $request->input('url');
        $config->db = $request->input('db');
        $config->username = $request->input('username');

        $pwd = $request->input('password');
        if ($pwd !== '••••••••••••••••') {
            $config->password = $pwd; // encrypted via cast
        }
        
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Odoo configuration saved securely on the server.',
        ]);
    }

    /**
     * Clear all stored journal entries.
     */
    public function clearEntries(): JsonResponse
    {
        JournalEntry::truncate();

        return response()->json([
            'success' => true,
            'message' => 'All journal entries cleared.',
        ]);
    }

}
