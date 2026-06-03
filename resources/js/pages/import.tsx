import { Head, Link } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { 
    FileSpreadsheet, 
    Link2, 
    UploadCloud, 
    Clock, 
    FileText, 
    RefreshCw,
    Info,
    ArrowRight,
    CheckCircle
} from 'lucide-react';
import { toast } from 'sonner';

export default function ImportData() {
    // Tab state: 'excel' or 'odoo'
    const [activeTab, setActiveTab] = useState<'excel' | 'odoo'>('excel');
    
    // Date range for API sync
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    
    // Loading states
    const [isSyncing, setIsSyncing] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    
    // Excel Drag & Drop state
    const [dragActive, setDragActive] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);

    // Success state — show after successful import
    const [importSuccess, setImportSuccess] = useState(false);
    const [importCount, setImportCount] = useState(0);

    // Load default dates on mount
    useEffect(() => {
        // Default date range: current month
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
        setStartDate(firstDay);
        setEndDate(lastDay);
    }, []);

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // Sync data from Odoo API — saves to DB using credentials stored on server
    const handleSync = async () => {
        if (!startDate || !endDate) {
            toast.error('Please select a date range.');
            return;
        }

        setIsSyncing(true);
        setImportSuccess(false);
        try {
            const response = await fetch('/api/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate
                })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                setImportCount(result.count);
                setImportSuccess(true);
                toast.success(`Successfully synced ${result.count} rows from Odoo!`);
            } else {
                toast.error(result.message || 'Sync failed.');
            }
        } catch (error) {
            toast.error('An error occurred during Odoo synchronization.');
            console.error(error);
        } finally {
            setIsSyncing(false);
        }
    };

    // Excel Drag and Drop Handlers
    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            const file = e.dataTransfer.files[0];
            const ext = file.name.split('.').pop()?.toLowerCase();
            if (['xlsx', 'xls', 'csv'].includes(ext || '')) {
                setSelectedFile(file);
                toast.info(`Selected file: ${file.name}`);
            } else {
                toast.error('Invalid file type. Please upload XLSX, XLS or CSV files.');
            }
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setSelectedFile(e.target.files[0]);
        }
    };

    // Upload & Parse Excel File — saves raw data to DB
    const handleExcelUpload = async () => {
        if (!selectedFile) {
            toast.error('Please select an Excel file first.');
            return;
        }

        setIsUploading(true);
        setImportSuccess(false);
        const formData = new FormData();
        formData.append('file', selectedFile);

        try {
            const response = await fetch('/api/upload-excel', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: formData
            });

            const result = await response.json();
            if (response.ok && result.success) {
                setImportCount(result.count);
                setImportSuccess(true);
                toast.success(`Successfully imported ${result.count} rows from Excel!`);
            } else {
                toast.error(result.message || 'Failed to upload/parse Excel.');
            }
        } catch (error) {
            toast.error('An error occurred during file upload.');
            console.error(error);
        } finally {
            setIsUploading(false);
        }
    };

    return (
        <>
            <Head title="Import Data — PPh 23 Converter" />
            <div className="min-h-screen bg-[#090d16] text-neutral-100 p-6 flex flex-col gap-6">
                
                {/* Header */}
                <div className="flex flex-col gap-1 border-b border-neutral-800 pb-4">
                    <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <FileSpreadsheet className="text-indigo-500 w-7 h-7" />
                        Import Data
                    </h1>
                    <p className="text-neutral-400 text-sm">
                        Upload an Odoo Excel export or sync directly via Odoo XML-RPC API. Imported data will be saved and displayed on the Dashboard.
                    </p>
                </div>

                {/* Success Card */}
                {importSuccess && (
                    <div className="bg-emerald-500/5 rounded-2xl border border-emerald-500/20 p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 animate-fade-in">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-emerald-500/10 text-emerald-400 rounded-xl">
                                <CheckCircle className="w-8 h-8" />
                            </div>
                            <div>
                                <h3 className="text-lg font-bold text-white">Import Successful!</h3>
                                <p className="text-neutral-400 text-sm">
                                    <strong className="text-emerald-400">{importCount.toLocaleString()}</strong> journal entry rows have been saved. Go to the Dashboard to view and process.
                                </p>
                            </div>
                        </div>
                        <Link
                            href="/dashboard"
                            className="bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-white font-bold px-6 py-3 rounded-xl shadow-lg hover:shadow-cyan-500/10 transition-all duration-200 flex items-center gap-2 cursor-pointer text-sm shrink-0"
                        >
                            Go to Dashboard
                            <ArrowRight className="w-4 h-4" />
                        </Link>
                    </div>
                )}

                {/* Main Configuration Tabs */}
                <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 shadow-2xl p-6">
                    
                    {/* Tab Navigation */}
                    <div className="flex border-b border-neutral-800 pb-4 gap-4 mb-6">
                        <button
                            onClick={() => setActiveTab('excel')}
                            className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                activeTab === 'excel'
                                    ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20'
                                    : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/30'
                            }`}
                        >
                            <UploadCloud className="w-4 h-4" />
                            Excel Import
                        </button>
                        <button
                            onClick={() => setActiveTab('odoo')}
                            className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                activeTab === 'odoo'
                                    ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20'
                                    : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/30'
                            }`}
                        >
                            <Link2 className="w-4 h-4" />
                            Odoo API Sync
                        </button>
                    </div>

                    {/* Tab 1: Excel Import */}
                    {activeTab === 'excel' && (
                        <div className="flex flex-col gap-5">
                            <div>
                                <h3 className="text-lg font-semibold text-white">Upload Odoo Excel File</h3>
                                <p className="text-neutral-400 text-xs mt-0.5">
                                    Upload the journal entries file (.xlsx, .xls, or .csv) exported from Odoo. Raw data will be saved to the database.
                                </p>
                            </div>

                            {/* Drag and Drop Zone */}
                            <div
                                onDragEnter={handleDrag}
                                onDragOver={handleDrag}
                                onDragLeave={handleDrag}
                                onDrop={handleDrop}
                                className={`border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center gap-4 transition-all duration-200 cursor-pointer ${
                                    dragActive 
                                        ? 'border-indigo-500 bg-indigo-600/5' 
                                        : 'border-neutral-800 hover:border-neutral-700 bg-neutral-900/20'
                                }`}
                                onClick={() => document.getElementById('excel-file-input')?.click()}
                            >
                                <input
                                    id="excel-file-input"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    className="hidden"
                                    onChange={handleFileChange}
                                />
                                <UploadCloud className="w-12 h-12 text-neutral-500 animate-pulse" />
                                <div className="text-center">
                                    <p className="text-sm font-semibold text-neutral-200">
                                        {selectedFile ? selectedFile.name : 'Choose file or drag and drop'}
                                    </p>
                                    <p className="text-xs text-neutral-500 mt-1">
                                        XLSX, XLS, or CSV up to 10MB
                                    </p>
                                </div>
                            </div>

                            <button
                                onClick={handleExcelUpload}
                                disabled={isUploading || !selectedFile}
                                className={`w-full py-3 rounded-lg font-semibold text-sm transition-all duration-200 shadow-lg ${
                                    isUploading || !selectedFile
                                        ? 'bg-neutral-850 text-neutral-500 border border-neutral-800 cursor-not-allowed'
                                        : 'bg-indigo-600 hover:bg-indigo-500 text-white cursor-pointer active:scale-[0.99]'
                                } flex items-center justify-center gap-2`}
                            >
                                {isUploading ? (
                                    <>
                                        <RefreshCw className="w-4 h-4 animate-spin" />
                                        Uploading & Importing...
                                    </>
                                ) : (
                                    <>
                                        <FileText className="w-4 h-4" />
                                        Upload & Import
                                    </>
                                )}
                            </button>
                        </div>
                    )}

                    {/* Tab 2: Odoo API Sync */}
                    {activeTab === 'odoo' && (
                        <div className="max-w-md mx-auto flex flex-col justify-between gap-4 py-1">
                            <div className="flex flex-col gap-4">
                                <div>
                                    <h3 className="text-lg font-semibold text-white flex items-center gap-2">
                                        <Clock className="w-5 h-5 text-indigo-400" />
                                        Sync Parameters
                                    </h3>
                                    <p className="text-neutral-400 text-xs mt-0.5">
                                        Select the date range to pull Odoo Vendor Bills.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label className="text-xs font-semibold text-neutral-300">Start Date</label>
                                    <input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label className="text-xs font-semibold text-neutral-300">End Date</label>
                                    <input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                    />
                                </div>

                                <div className="flex items-start gap-2 bg-neutral-900/60 p-3 rounded-lg border border-neutral-850 text-neutral-400 text-xs mt-1">
                                    <Info className="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" />
                                    <span>
                                        The system will fetch all vendor bill journal entries within the date range and save them to the database for viewing on the Dashboard.
                                    </span>
                                </div>
                            </div>

                            <button
                                onClick={handleSync}
                                disabled={isSyncing}
                                className="w-full py-3 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-all duration-200 shadow-lg flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 mt-4"
                            >
                                {isSyncing ? (
                                    <>
                                        <RefreshCw className="w-4 h-4 animate-spin" />
                                        Syncing with Odoo API...
                                    </>
                                ) : (
                                    <>
                                        <RefreshCw className="w-4 h-4" />
                                        Sync Now
                                    </>
                                )}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
