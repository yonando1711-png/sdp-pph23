import { Head } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { 
    LayoutGrid, 
    FileSpreadsheet, 
    Search, 
    Download, 
    RefreshCw,
    Check,
    X,
    AlertTriangle,
    Trash2,
    ArrowRight,
    Database,
    TrendingUp,
    FileText,
    Calculator,
    Info,
    ChevronDown
} from 'lucide-react';
import { toast } from 'sonner';

interface RawEntry {
    id: number;
    partner_tax_id: string | null;
    partner_id_tku: string | null;
    invoice_bill_date: string | null;
    invoice_lines_taxes_id: string | null;
    invoice_lines_taxes: string | null;
    invoice_lines_amount: number | null;
    reference: string | null;
    payment_reference: string | null;
    date: string | null;
    journal_items_account: string | null;
    journal_items_amount: number | null;
    number: string | null;
    partner_display_name: string | null;
    source: string;
}

interface PphEntry {
    reference: string;
    cleaned_reference: string;
    number: string;
    partner: string;
    tax_id: string | null;
    date: string | null;
    dpp: number;
    pph23: number;
    is_correct: boolean;
    difference: number;
    partner_ta?: string | null;
    partner_id?: string | null;
    invoice_bi?: string | null;
    invoice_li?: string | null;
    invoice_l2?: string | null;
    invoice_l3?: number | null;
    payment_re?: string | null;
    journal_it?: string | null;
    journal_i2?: number | null;
    partner_di?: string | null;
}

export default function Dashboard() {
    const [rawEntries, setRawEntries] = useState<RawEntry[]>([]);
    const [processedEntries, setProcessedEntries] = useState<PphEntry[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isProcessing, setIsProcessing] = useState(false);
    const [isClearing, setIsClearing] = useState(false);
    const [rawSearchQuery, setRawSearchQuery] = useState('');
    const [processedSearchQuery, setProcessedSearchQuery] = useState('');
    const [filterStatus, setFilterStatus] = useState<'all' | 'correct' | 'incorrect'>('all');
    const [dataSource, setDataSource] = useState<string | null>(null);
    const [activeView, setActiveView] = useState<'raw' | 'processed'>('raw');
    const [showExportDropdown, setShowExportDropdown] = useState(false);
    const dropdownRef = React.useRef<HTMLDivElement>(null);

    // Load raw entries on mount
    useEffect(() => {
        fetchEntries();
    }, []);

    // Handle click outside for dropdown closing
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setShowExportDropdown(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, [dropdownRef]);

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    const fetchEntries = async () => {
        setIsLoading(true);
        try {
            const response = await fetch('/api/journal-entries', {
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (result.success) {
                setRawEntries(result.data || []);
                setDataSource(result.source);
            }
        } catch (error) {
            console.error('Failed to load entries:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleProcess = async () => {
        setIsProcessing(true);
        try {
            const response = await fetch('/api/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                }
            });
            const result = await response.json();
            if (response.ok && result.success) {
                setProcessedEntries(result.data);
                setActiveView('processed');
                toast.success(`Successfully processed ${result.count} PPh 23 transactions!`);
            } else {
                toast.error(result.message || 'Processing failed.');
            }
        } catch (error) {
            toast.error('An error occurred during processing.');
            console.error(error);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleClearData = async () => {
        if (!confirm('Are you sure you want to clear all imported data?')) return;
        
        setIsClearing(true);
        try {
            const response = await fetch('/api/journal-entries', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (result.success) {
                setRawEntries([]);
                setProcessedEntries([]);
                setDataSource(null);
                setActiveView('raw');
                toast.success('All data cleared.');
            }
        } catch (error) {
            toast.error('Failed to clear data.');
        } finally {
            setIsClearing(false);
        }
    };

    // Stats
    const totalDPP = processedEntries.reduce((sum, item) => sum + item.dpp, 0);
    const totalPPh23 = processedEntries.reduce((sum, item) => sum + item.pph23, 0);
    const incorrectCount = processedEntries.filter(item => !item.is_correct).length;

    // Filter raw entries
    const filteredRawEntries = rawEntries.filter(item => {
        if (!rawSearchQuery) return true;
        const q = rawSearchQuery.toLowerCase();
        return (
            (item.partner_display_name && item.partner_display_name.toLowerCase().includes(q)) ||
            (item.number && item.number.toLowerCase().includes(q)) ||
            (item.reference && item.reference.toLowerCase().includes(q)) ||
            (item.partner_tax_id && item.partner_tax_id.includes(q)) ||
            (item.invoice_lines_taxes && item.invoice_lines_taxes.toLowerCase().includes(q)) ||
            (item.journal_items_account && item.journal_items_account.toLowerCase().includes(q))
        );
    });

    // Filter processed entries
    const filteredProcessedEntries = processedEntries.filter(item => {
        const matchesSearch = !processedSearchQuery ||
            item.partner.toLowerCase().includes(processedSearchQuery.toLowerCase()) ||
            item.number.toLowerCase().includes(processedSearchQuery.toLowerCase()) ||
            item.reference.toLowerCase().includes(processedSearchQuery.toLowerCase()) ||
            (item.tax_id && item.tax_id.includes(processedSearchQuery));

        if (filterStatus === 'correct') return matchesSearch && item.is_correct;
        if (filterStatus === 'incorrect') return matchesSearch && !item.is_correct;
        return matchesSearch;
    });

    // Export processed data to xlsx, xls, or csv via backend
    const handleExportBackend = (format: 'xlsx' | 'xls' | 'csv') => {
        if (filteredProcessedEntries.length === 0) {
            toast.error('No data available to export.');
            return;
        }

        const queryParams = new URLSearchParams({
            format,
            search: processedSearchQuery,
            status: filterStatus
        });
        
        window.location.href = `/api/export?${queryParams.toString()}`;
        toast.info(`Generating and exporting jpph23_1 as .${format.toUpperCase()}...`);
    };

    // Export e-Bupot CSV
    const exportEBupotCSV = () => {
        if (filteredProcessedEntries.length === 0) {
            toast.error('No data available to export.');
            return;
        }

        const headers = [
            'Nomor Dokumen', 'Tanggal Dokumen (dd/mm/yyyy)', 'Penerima Penghasilan NPWP/NIK',
            'Nama Penerima Penghasilan', 'Kode Objek Pajak', 'Penghasilan Bruto (DPP)',
            'Tarif (2%)', 'PPH Dipotong'
        ];
        const csvRows = [headers.join(',')];

        filteredProcessedEntries.forEach(item => {
            let formattedDate = '';
            if (item.date) {
                const d = new Date(item.date);
                if (!isNaN(d.getTime())) {
                    const day = String(d.getDate()).padStart(2, '0');
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const year = d.getFullYear();
                    formattedDate = `${day}/${month}/${year}`;
                }
            }

            const row = [
                `"${item.number}"`, `"${formattedDate}"`, `"${item.tax_id || ''}"`,
                `"${item.partner.replace(/"/g, '""')}"`, '"24-104-01"',
                item.dpp, 2, item.pph23
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + csvRows.join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `e-Bupot_PPh23_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success('e-Bupot import CSV exported successfully!');
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-6 bg-[#090d16] text-neutral-100 min-h-full">
                
                {/* Header + Actions */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <LayoutGrid className="text-indigo-500 w-7 h-7" />
                            Dashboard
                        </h1>
                        <p className="text-neutral-400 text-sm">
                            View imported journal entries and process PPh 23 withholding tax calculations.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {rawEntries.length > 0 && (
                            <>
                                <button
                                    onClick={handleProcess}
                                    disabled={isProcessing}
                                    className="bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-white font-bold px-5 py-2.5 rounded-xl shadow-lg hover:shadow-cyan-500/10 transition-all duration-200 flex items-center gap-2 cursor-pointer text-sm disabled:opacity-50"
                                >
                                    {isProcessing ? (
                                        <><RefreshCw className="w-4 h-4 animate-spin" /> Processing...</>
                                    ) : (
                                        <><Calculator className="w-4 h-4" /> Process PPh 23</>
                                    )}
                                </button>
                                <button
                                    onClick={handleClearData}
                                    disabled={isClearing}
                                    className="bg-neutral-800 hover:bg-neutral-750 text-neutral-300 hover:text-rose-400 font-semibold px-4 py-2.5 rounded-xl border border-neutral-700/60 transition-all duration-200 flex items-center gap-2 cursor-pointer text-sm"
                                >
                                    <Trash2 className="w-4 h-4" /> Clear
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* Empty State */}
                {!isLoading && rawEntries.length === 0 && (
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 shadow-2xl p-12 flex flex-col items-center justify-center gap-5 text-center">
                        <div className="p-5 bg-indigo-500/10 text-indigo-400 rounded-2xl border border-indigo-500/20">
                            <Database className="w-12 h-12" />
                        </div>
                        <div className="flex flex-col gap-2">
                            <h2 className="text-xl font-bold text-white">No Data Imported</h2>
                            <p className="text-neutral-400 text-sm max-w-md">
                                Go to <strong>Import Data</strong> in the sidebar to upload an Excel file or sync from Odoo API. Imported data will appear here for visualization and processing.
                            </p>
                        </div>
                        <a
                            href="/dashboard/import"
                            className="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-6 py-2.5 rounded-xl shadow-md transition-all duration-200 flex items-center gap-2 text-sm mt-2"
                        >
                            <ArrowRight className="w-4 h-4" /> Go to Import Data
                        </a>
                    </div>
                )}

                {/* Loading State */}
                {isLoading && (
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 p-12 flex items-center justify-center">
                        <RefreshCw className="w-8 h-8 text-indigo-400 animate-spin" />
                    </div>
                )}

                {/* Stats Cards */}
                {rawEntries.length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="bg-[#0f1524] p-5 rounded-xl border border-neutral-800/80 flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Raw Rows</span>
                            <span className="text-3xl font-bold text-white">{rawEntries.length.toLocaleString()}</span>
                            <span className="text-xs text-neutral-500">Source: <span className="text-indigo-400 font-semibold capitalize">{dataSource || 'N/A'}</span></span>
                        </div>
                        {processedEntries.length > 0 && (
                            <>
                                <div className="bg-[#0f1524] p-5 rounded-xl border border-neutral-800/80 flex flex-col gap-1.5">
                                    <span className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Processed Transactions</span>
                                    <span className="text-3xl font-bold text-white">{processedEntries.length}</span>
                                </div>
                                <div className="bg-[#0f1524] p-5 rounded-xl border border-neutral-800/80 flex flex-col gap-1.5">
                                    <span className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Total DPP (Tax Base)</span>
                                    <span className="text-2xl font-bold text-indigo-400">Rp {totalDPP.toLocaleString()}</span>
                                </div>
                                <div className="bg-[#0f1524] p-5 rounded-xl border border-neutral-800/80 flex flex-col gap-1.5">
                                    <span className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Total PPh 23 Tax</span>
                                    <div className="flex items-center gap-3">
                                        <span className="text-2xl font-bold text-emerald-400">Rp {totalPPh23.toLocaleString()}</span>
                                        {incorrectCount > 0 && (
                                            <span className="text-xs bg-rose-500/10 text-rose-400 border border-rose-500/25 px-2 py-0.5 rounded font-bold">
                                                {incorrectCount} errors
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                )}

                {/* View Toggle + Data Tables */}
                {rawEntries.length > 0 && (
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 shadow-2xl p-6 flex flex-col gap-5">
                        
                        {/* Tab Toggle */}
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-neutral-800 pb-5">
                            <div className="flex items-center gap-3">
                                <div className="flex bg-neutral-900 p-1 rounded-lg border border-neutral-800/80">
                                    <button
                                        onClick={() => setActiveView('raw')}
                                        className={`px-4 py-1.5 rounded-md text-sm font-semibold transition-all ${
                                            activeView === 'raw'
                                                ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/20'
                                                : 'text-neutral-400 hover:text-neutral-200'
                                        }`}
                                    >
                                        <FileSpreadsheet className="w-4 h-4 inline mr-1.5 -mt-0.5" />
                                        Journal Entries
                                    </button>
                                    <button
                                        onClick={() => setActiveView('processed')}
                                        disabled={processedEntries.length === 0}
                                        className={`px-4 py-1.5 rounded-md text-sm font-semibold transition-all ${
                                            activeView === 'processed'
                                                ? 'bg-teal-600/20 text-teal-400 border border-teal-500/20'
                                                : processedEntries.length === 0
                                                    ? 'text-neutral-600 cursor-not-allowed'
                                                    : 'text-neutral-400 hover:text-neutral-200'
                                        }`}
                                    >
                                        <TrendingUp className="w-4 h-4 inline mr-1.5 -mt-0.5" />
                                        PPh 23 Results
                                        {processedEntries.length > 0 && (
                                            <span className="ml-1.5 bg-teal-500/20 text-teal-400 px-1.5 py-0.5 rounded text-[10px] font-bold">
                                                {processedEntries.length}
                                            </span>
                                        )}
                                    </button>
                                </div>
                            </div>

                            {/* Controls for the active view */}
                            <div className="flex flex-wrap items-center gap-3">
                                {activeView === 'raw' && (
                                    <div className="relative">
                                        <input
                                            type="text"
                                            placeholder="Search partner, number, ref, tax..."
                                            value={rawSearchQuery}
                                            onChange={(e) => setRawSearchQuery(e.target.value)}
                                            className="bg-neutral-900 border border-neutral-800/80 rounded-lg pl-9 pr-4 py-1.5 text-xs text-neutral-200 focus:outline-none focus:border-indigo-500 w-full md:w-64"
                                        />
                                        <Search className="w-4 h-4 text-neutral-500 absolute left-3 top-2" />
                                    </div>
                                )}

                                {activeView === 'processed' && (
                                    <>
                                        <div className="relative">
                                            <input
                                                type="text"
                                                placeholder="Search partner, number, ref..."
                                                value={processedSearchQuery}
                                                onChange={(e) => setProcessedSearchQuery(e.target.value)}
                                                className="bg-neutral-900 border border-neutral-800/80 rounded-lg pl-9 pr-4 py-1.5 text-xs text-neutral-200 focus:outline-none focus:border-indigo-500 w-full md:w-60"
                                            />
                                            <Search className="w-4 h-4 text-neutral-500 absolute left-3 top-2" />
                                        </div>

                                        <div className="flex bg-neutral-900 p-0.5 rounded-lg border border-neutral-800/80 text-xs font-semibold">
                                            {(['all', 'correct', 'incorrect'] as const).map(status => (
                                                <button
                                                    key={status}
                                                    onClick={() => setFilterStatus(status)}
                                                    className={`px-3 py-1 rounded-md transition-all capitalize ${
                                                        filterStatus === status
                                                            ? 'bg-neutral-800 text-white'
                                                            : 'text-neutral-400 hover:text-neutral-200'
                                                    }`}
                                                >
                                                    {status === 'incorrect' ? 'Errors' : status === 'all' ? 'All' : 'Correct'}
                                                </button>
                                            ))}
                                        </div>

                                        <div className="flex gap-2">
                                            <div className="relative" ref={dropdownRef}>
                                                <button
                                                    onClick={() => setShowExportDropdown(!showExportDropdown)}
                                                    className="bg-neutral-800 hover:bg-neutral-750 text-neutral-200 text-xs font-semibold border border-neutral-700/60 rounded-lg px-4 py-1.5 transition-all duration-200 cursor-pointer flex items-center gap-1.5"
                                                >
                                                    <Download className="w-3.5 h-3.5" /> Export Data
                                                    <ChevronDown className="w-3.5 h-3.5 text-neutral-400" />
                                                </button>
                                                {showExportDropdown && (
                                                    <div className="absolute right-0 mt-1.5 w-48 bg-[#0f1524] border border-neutral-800 rounded-lg shadow-xl z-50 py-1 overflow-hidden animate-fade-in">
                                                        <button
                                                            onClick={() => {
                                                                handleExportBackend('xlsx');
                                                                setShowExportDropdown(false);
                                                            }}
                                                            className="w-full text-left px-4 py-2 text-xs text-neutral-300 hover:bg-indigo-600/10 hover:text-indigo-400 transition-colors flex items-center gap-2"
                                                        >
                                                            <FileText className="w-3.5 h-3.5 text-indigo-400" />
                                                            Excel Spreadsheet (.xlsx)
                                                        </button>
                                                        <button
                                                            onClick={() => {
                                                                handleExportBackend('xls');
                                                                setShowExportDropdown(false);
                                                            }}
                                                            className="w-full text-left px-4 py-2 text-xs text-neutral-300 hover:bg-indigo-600/10 hover:text-indigo-400 transition-colors flex items-center gap-2"
                                                        >
                                                            <FileSpreadsheet className="w-3.5 h-3.5 text-indigo-400" />
                                                            Legacy Excel (.xls)
                                                        </button>
                                                        <button
                                                            onClick={() => {
                                                                handleExportBackend('csv');
                                                                setShowExportDropdown(false);
                                                            }}
                                                            className="w-full text-left px-4 py-2 text-xs text-neutral-300 hover:bg-indigo-600/10 hover:text-indigo-400 transition-colors flex items-center gap-2"
                                                        >
                                                            <FileText className="w-3.5 h-3.5 text-indigo-400" />
                                                            Comma Separated (.csv)
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                            <button
                                                onClick={exportEBupotCSV}
                                                className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg px-4 py-1.5 transition-all duration-200 shadow-md cursor-pointer flex items-center gap-1.5"
                                            >
                                                <Download className="w-3.5 h-3.5" /> e-Bupot CSV
                                            </button>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Raw Data Table */}
                        {activeView === 'raw' && (
                            <div className="overflow-x-auto rounded-xl border border-neutral-800/80 bg-neutral-950/20">
                                <table className="w-full text-left border-collapse text-[11px]">
                                    <thead>
                                        <tr className="bg-neutral-900/60 border-b border-neutral-850 text-neutral-300 font-semibold">
                                            <th className="py-1.5 px-2 whitespace-nowrap">#</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Number</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Bill Date</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Accounting Date</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Partner</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Tax ID (NPWP)</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Reference</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Invoice lines/Taxes</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap text-right">Invoice Amount</th>
                                            <th className="py-1.5 px-2 whitespace-nowrap">Journal Account</th>
                                            <th className="py-1.5 pl-2 pr-4 whitespace-nowrap text-right">Journal Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-850 text-neutral-400">
                                        {filteredRawEntries.length > 0 ? (
                                            filteredRawEntries.slice(0, 500).map((item, index) => (
                                                <tr key={item.id || index} className="hover:bg-neutral-900/20 transition-all duration-100">
                                                    <td className="py-1.5 px-2 text-neutral-600">{index + 1}</td>
                                                    <td className="py-1.5 px-2 font-semibold text-neutral-200 whitespace-nowrap">{item.number || '-'}</td>
                                                    <td className="py-1.5 px-2 whitespace-nowrap text-amber-400">{item.invoice_bill_date || '-'}</td>
                                                    <td className="py-1.5 px-2 whitespace-nowrap text-emerald-400">{item.date || '-'}</td>
                                                    <td className="py-1.5 px-2 font-medium text-neutral-300 min-w-[150px] break-words" title={item.partner_display_name || ''}>
                                                        {item.partner_display_name || '-'}
                                                    </td>
                                                    <td className="py-1.5 px-2 font-mono text-[10px]">{item.partner_tax_id || '-'}</td>
                                                    <td className="py-1.5 px-2 min-w-[120px] break-words" title={item.reference || ''}>
                                                        {item.reference || '-'}
                                                    </td>
                                                    <td className="py-1.5 px-2 min-w-[120px] break-words" title={item.invoice_lines_taxes || ''}>
                                                        {item.invoice_lines_taxes ? (
                                                            <span className={`${item.invoice_lines_taxes.toLowerCase().includes('pph 23') ? 'text-teal-400 font-semibold' : ''}`}>
                                                                {item.invoice_lines_taxes}
                                                            </span>
                                                        ) : '-'}
                                                    </td>
                                                    <td className="py-1.5 px-2 text-right text-indigo-400 font-medium whitespace-nowrap">
                                                        {item.invoice_lines_amount != null ? Number(item.invoice_lines_amount).toLocaleString() : '-'}
                                                    </td>
                                                    <td className="py-1.5 px-2 min-w-[180px] break-words" title={item.journal_items_account || ''}>
                                                        {item.journal_items_account ? (
                                                            <span className={`${item.journal_items_account.startsWith('212003') ? 'text-amber-400 font-semibold' : ''}`}>
                                                                {item.journal_items_account}
                                                            </span>
                                                        ) : '-'}
                                                    </td>
                                                    <td className="py-1.5 pl-2 pr-4 text-right font-medium whitespace-nowrap">
                                                        {item.journal_items_amount != null ? (
                                                            <span className={Number(item.journal_items_amount) < 0 ? 'text-rose-400' : 'text-emerald-400'}>
                                                                {Number(item.journal_items_amount).toLocaleString()}
                                                            </span>
                                                        ) : '-'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={10} className="p-8 text-center text-neutral-500 font-semibold">
                                                    No entries match your search.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                {filteredRawEntries.length > 500 && (
                                    <div className="px-4 py-2 bg-neutral-900/40 border-t border-neutral-850 text-xs text-neutral-500 text-center">
                                        Showing 500 of {filteredRawEntries.length.toLocaleString()} rows. Use search to narrow results.
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Processed PPh 23 Table */}
                        {activeView === 'processed' && processedEntries.length > 0 && (
                            <div className="overflow-x-auto rounded-xl border border-neutral-800/80 bg-neutral-950/20">
                                <table className="w-full text-left border-collapse text-[11px]">
                                    <thead>
                                        <tr className="bg-neutral-900/60 border-b border-neutral-850 text-neutral-300 font-semibold">
                                            <th className="py-1.5 px-2">Bill Number</th>
                                            <th className="py-1.5 px-2">Bill Date</th>
                                            <th className="py-1.5 px-2">Accounting Date</th>
                                            <th className="py-1.5 px-2">Partner Display Name</th>
                                            <th className="py-1.5 px-2">Tax ID (NPWP)</th>
                                            <th className="py-1.5 px-2">Cleaned Reference</th>
                                            <th className="py-1.5 px-2 text-right">DPP Base (Rp)</th>
                                            <th className="py-1.5 px-2 text-right">PPh 23 (Rp)</th>
                                            <th className="py-1.5 pl-2 pr-4 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-850 text-neutral-400">
                                        {filteredProcessedEntries.length > 0 ? (
                                            filteredProcessedEntries.map((item, index) => (
                                                <tr key={index} className="hover:bg-neutral-900/20 transition-all duration-150">
                                                    <td className="py-1.5 px-2 font-semibold text-neutral-200">{item.number}</td>
                                                    <td className="py-1.5 px-2 whitespace-nowrap text-amber-400">{item.invoice_bi || '-'}</td>
                                                    <td className="py-1.5 px-2 whitespace-nowrap text-emerald-400">{item.date || '-'}</td>
                                                    <td className="py-1.5 px-2 font-medium text-neutral-300 min-w-[180px] break-words" title={item.partner}>
                                                        {item.partner}
                                                    </td>
                                                    <td className="py-1.5 px-2 font-mono text-[10px]">{item.tax_id || '-'}</td>
                                                    <td className="py-1.5 px-2 min-w-[140px] break-words" title={`Original: ${item.reference}`}>
                                                        {item.cleaned_reference}
                                                    </td>
                                                    <td className="py-1.5 px-2 text-right text-indigo-400 font-medium">
                                                        {item.dpp.toLocaleString()}
                                                    </td>
                                                    <td className="py-1.5 px-2 text-right text-emerald-400 font-medium">
                                                        {item.pph23.toLocaleString()}
                                                    </td>
                                                    <td className="py-1.5 pl-2 pr-4 text-center">
                                                        {item.is_correct ? (
                                                            <span className="inline-flex items-center gap-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">
                                                                <Check className="w-3 h-3" /> Correct
                                                            </span>
                                                        ) : (
                                                            <span
                                                                className="inline-flex items-center gap-1 bg-rose-500/10 text-rose-400 border border-rose-500/25 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider cursor-help"
                                                                title={`Discrepancy of Rp ${Math.round(item.difference)} from 2%`}
                                                            >
                                                                <AlertTriangle className="w-3 h-3" /> Error
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={8} className="p-8 text-center text-neutral-500 font-semibold">
                                                    No transactions match your search/filter parameters.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Info hint when no processing done yet */}
                        {activeView === 'raw' && processedEntries.length === 0 && rawEntries.length > 0 && (
                            <div className="flex items-start gap-3 bg-neutral-900/60 p-4 rounded-lg border border-neutral-850 text-neutral-400 text-xs">
                                <Info className="w-5 h-5 text-teal-400 shrink-0 mt-0.5" />
                                <span>
                                    Click the <strong className="text-teal-400">Process PPh 23</strong> button above to calculate withholding tax. 
                                    The system will group entries by Reference, identify <strong>2% PPH 23</strong> tax lines, 
                                    and compute DPP base from account <strong>212003</strong> journal items.
                                </span>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
