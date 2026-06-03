import { Head, Link, usePage } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { 
    Database, 
    Users, 
    Building, 
    RefreshCw, 
    ShieldAlert, 
    Plus, 
    Edit, 
    Trash2, 
    Check, 
    X,
    Info,
    Lock
} from 'lucide-react';
import { toast } from 'sonner';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    department_id: number | null;
    department?: {
        id: number;
        name: string;
        code: string | null;
    } | null;
}

interface Department {
    id: number;
    name: string;
    code: string | null;
    users_count?: number;
}

export default function Utilities() {
    const { auth } = usePage().props as any;
    const currentUser = auth?.user;
    const isSuperadmin = currentUser?.role === 'superadmin';

    // Password lock protection
    const [isUnlocked, setIsUnlocked] = useState(() => {
        return sessionStorage.getItem('utilities_unlocked') === 'true';
    });
    const [passwordInput, setPasswordInput] = useState('');
    const [passwordError, setPasswordError] = useState(false);

    const handleUnlock = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            const response = await fetch('/api/utilities/unlock', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({ password: passwordInput })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                setIsUnlocked(true);
                setPasswordError(false);
                sessionStorage.setItem('utilities_unlocked', 'true');
                toast.success('Utilities menu unlocked successfully.');
            } else {
                setPasswordError(true);
                setPasswordInput('');
                toast.error(result.message || 'Incorrect password.');
            }
        } catch (error) {
            console.error('Failed to unlock:', error);
            toast.error('An error occurred. Please try again.');
        }
    };

    // Page state
    const [activeTab, setActiveTab] = useState<'odoo' | 'users' | 'departments'>('odoo');
    
    // Odoo state
    const [odooUrl, setOdooUrl] = useState('');
    const [odooDb, setOdooDb] = useState('');
    const [odooUser, setOdooUser] = useState('');
    const [odooPassword, setOdooPassword] = useState('');
    const [isTestingConn, setIsTestingConn] = useState(false);
    const [isSavingOdoo, setIsSavingOdoo] = useState(false);

    // Users state
    const [users, setUsers] = useState<User[]>([]);
    const [isUsersLoading, setIsUsersLoading] = useState(false);
    const [userModalOpen, setUserModalOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [userName, setUserName] = useState('');
    const [userEmail, setUserEmail] = useState('');
    const [userPassword, setUserPassword] = useState('');
    const [userRole, setUserRole] = useState('user');
    const [userDeptId, setUserDeptId] = useState('');
    const [isSavingUser, setIsSavingUser] = useState(false);

    // Departments state
    const [departments, setDepartments] = useState<Department[]>([]);
    const [isDeptsLoading, setIsDeptsLoading] = useState(false);
    const [deptModalOpen, setDeptModalOpen] = useState(false);
    const [editingDept, setEditingDept] = useState<Department | null>(null);
    const [deptName, setDeptName] = useState('');
    const [deptCode, setDeptCode] = useState('');
    const [isSavingDept, setIsSavingDept] = useState(false);

    // Passcode settings state
    const [sysPasscode, setSysPasscode] = useState('');
    const [isSavingPasscode, setIsSavingPasscode] = useState(false);

    // Fetch data on mount if superadmin
    useEffect(() => {
        if (isSuperadmin) {
            fetchOdooConfig();
            fetchUsers();
            fetchDepartments();
            fetchUnlockPassword();
        }
    }, [isSuperadmin]);

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // --- Odoo Operations ---
    const fetchOdooConfig = async () => {
        try {
            const response = await fetch('/api/odoo-config', {
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (response.ok && result.success && result.data) {
                const config = result.data;
                if (config.url) setOdooUrl(config.url);
                if (config.db) setOdooDb(config.db);
                if (config.username) setOdooUser(config.username);
                if (config.has_password) setOdooPassword('••••••••••••••••');
            }
        } catch (error) {
            console.error('Failed to load Odoo config:', error);
        }
    };

    const handleSaveOdooConfig = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!odooUrl || !odooDb || !odooUser || !odooPassword) {
            toast.error('Please fill in all configuration fields.');
            return;
        }

        setIsSavingOdoo(true);
        try {
            const response = await fetch('/api/odoo-config', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({
                    url: odooUrl,
                    db: odooDb,
                    username: odooUser,
                    password: odooPassword
                })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'Odoo configuration saved successfully!');
            } else {
                toast.error(result.message || 'Failed to save config.');
            }
        } catch (error) {
            toast.error('Error occurred while saving configuration.');
            console.error(error);
        } finally {
            setIsSavingOdoo(false);
        }
    };

    const handleTestOdooConnection = async () => {
        if (!odooUrl || !odooDb || !odooUser || !odooPassword) {
            toast.error('Please fill in all Odoo configuration fields.');
            return;
        }

        setIsTestingConn(true);
        try {
            const response = await fetch('/api/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({
                    url: odooUrl,
                    db: odooDb,
                    username: odooUser,
                    password: odooPassword
                })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'Connection successful!');
            } else {
                toast.error(result.message || 'Failed to connect to Odoo.');
            }
        } catch (error) {
            toast.error('An error occurred while testing the connection.');
            console.error(error);
        } finally {
            setIsTestingConn(false);
        }
    };

    // --- User Operations ---
    const fetchUsers = async () => {
        setIsUsersLoading(true);
        try {
            const response = await fetch('/api/users', {
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (result.success) {
                setUsers(result.data || []);
            }
        } catch (error) {
            console.error('Failed to load users:', error);
        } finally {
            setIsUsersLoading(false);
        }
    };

    const handleOpenUserModal = (user: User | null = null) => {
        setEditingUser(user);
        if (user) {
            setUserName(user.name);
            setUserEmail(user.email);
            setUserPassword('');
            setUserRole(user.role);
            setUserDeptId(user.department_id ? String(user.department_id) : '');
        } else {
            setUserName('');
            setUserEmail('');
            setUserPassword('');
            setUserRole('user');
            setUserDeptId('');
        }
        setUserModalOpen(true);
    };

    const handleSaveUser = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!userName || !userEmail || (!editingUser && !userPassword)) {
            toast.error('Please fill in all required fields.');
            return;
        }

        setIsSavingUser(true);
        try {
            const response = await fetch('/api/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({
                    id: editingUser?.id || null,
                    name: userName,
                    email: userEmail,
                    password: userPassword || null,
                    role: userRole,
                    department_id: userDeptId ? parseInt(userDeptId) : null,
                })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'User saved successfully.');
                fetchUsers();
                setUserModalOpen(false);
            } else {
                toast.error(result.message || 'Failed to save user.');
            }
        } catch (error) {
            toast.error('An error occurred while saving user.');
            console.error(error);
        } finally {
            setIsSavingUser(false);
        }
    };

    const handleDeleteUser = async (user: User) => {
        if (currentUser.id === user.id) {
            toast.error('You cannot delete your own account.');
            return;
        }
        if (!confirm(`Are you sure you want to delete user "${user.name}"?`)) {
            return;
        }

        try {
            const response = await fetch(`/api/users/${user.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'User deleted successfully.');
                fetchUsers();
            } else {
                toast.error(result.message || 'Failed to delete user.');
            }
        } catch (error) {
            toast.error('An error occurred while deleting user.');
        }
    };

    // --- Department Operations ---
    const fetchDepartments = async () => {
        setIsDeptsLoading(true);
        try {
            const response = await fetch('/api/departments', {
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (result.success) {
                setDepartments(result.data || []);
            }
        } catch (error) {
            console.error('Failed to load departments:', error);
        } finally {
            setIsDeptsLoading(false);
        }
    };

    const fetchUnlockPassword = async () => {
        try {
            const response = await fetch('/api/settings/unlock-password', {
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (response.ok && result.success) {
                setSysPasscode(result.password || '');
            }
        } catch (error) {
            console.error('Failed to load system passcode:', error);
        }
    };

    const handleSaveUnlockPassword = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!sysPasscode) {
            toast.error('Passcode cannot be empty.');
            return;
        }

        setIsSavingPasscode(true);
        try {
            const response = await fetch('/api/settings/unlock-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({ password: sysPasscode })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'Unlock passcode updated successfully!');
            } else {
                toast.error(result.message || 'Failed to update passcode.');
            }
        } catch (error) {
            toast.error('An error occurred while saving unlock passcode.');
            console.error(error);
        } finally {
            setIsSavingPasscode(false);
        }
    };

    const handleOpenDeptModal = (dept: Department | null = null) => {
        setEditingDept(dept);
        if (dept) {
            setDeptName(dept.name);
            setDeptCode(dept.code || '');
        } else {
            setDeptName('');
            setDeptCode('');
        }
        setDeptModalOpen(true);
    };

    const handleSaveDept = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!deptName) {
            toast.error('Department name is required.');
            return;
        }

        setIsSavingDept(true);
        try {
            const response = await fetch('/api/departments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                },
                body: JSON.stringify({
                    id: editingDept?.id || null,
                    name: deptName,
                    code: deptCode || null
                })
            });

            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'Department saved successfully.');
                fetchDepartments();
                fetchUsers(); // Refresh users since department names/counts might impact User list
                setDeptModalOpen(false);
            } else {
                toast.error(result.message || 'Failed to save department.');
            }
        } catch (error) {
            toast.error('An error occurred while saving department.');
            console.error(error);
        } finally {
            setIsSavingDept(false);
        }
    };

    const handleDeleteDept = async (dept: Department) => {
        if (dept.users_count && dept.users_count > 0) {
            toast.error('Cannot delete department because users are assigned to it.');
            return;
        }
        if (!confirm(`Are you sure you want to delete department "${dept.name}"?`)) {
            return;
        }

        try {
            const response = await fetch(`/api/departments/${dept.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() || '' }
            });
            const result = await response.json();
            if (response.ok && result.success) {
                toast.success(result.message || 'Department deleted successfully.');
                fetchDepartments();
            } else {
                toast.error(result.message || 'Failed to delete department.');
            }
        } catch (error) {
            toast.error('An error occurred while deleting department.');
        }
    };

    // --- Access Control Render ---
    if (!isUnlocked) {
        return (
            <>
                <Head title="Access Protected" />
                <div className="min-h-screen bg-[#090d16] text-neutral-100 p-6 flex items-center justify-center">
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 shadow-2xl p-8 max-w-md w-full flex flex-col items-center gap-6 border-neutral-700/30">
                        
                        <div className="w-16 h-16 bg-indigo-500/10 text-indigo-400 rounded-full flex items-center justify-center">
                            <Lock className="w-8 h-8" />
                        </div>
                        
                        <div className="text-center flex flex-col gap-2">
                            <h2 className="text-2xl font-bold text-white">Access Protected</h2>
                            <p className="text-neutral-400 text-sm">
                                Enter password to access Utilities Menu
                            </p>
                        </div>
                        
                        <form onSubmit={handleUnlock} className="w-full flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <input
                                    type="password"
                                    value={passwordInput}
                                    onChange={(e) => {
                                        setPasswordInput(e.target.value);
                                        setPasswordError(false);
                                    }}
                                    placeholder="Enter password"
                                    className={`w-full px-4 py-3 rounded-xl bg-neutral-900 border text-neutral-200 focus:outline-none focus:border-indigo-500 transition-all text-center text-lg tracking-widest ${
                                        passwordError 
                                            ? 'border-rose-500 focus:border-rose-500' 
                                            : 'border-neutral-800'
                                    }`}
                                    autoFocus
                                />
                                {passwordError && (
                                    <p className="text-rose-500 text-xs text-center font-medium mt-1">
                                        Incorrect password. Please try again.
                                    </p>
                                )}
                            </div>
                            
                            <button
                                type="submit"
                                className="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg active:scale-[0.99]"
                            >
                                <Lock className="w-4 h-4" />
                                Unlock
                            </button>
                        </form>
                        
                        <div className="text-center mt-2">
                            <Link
                                href="/dashboard"
                                className="text-neutral-500 hover:text-indigo-400 text-sm transition-colors"
                            >
                                ← Back to Dashboard
                            </Link>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    if (!isSuperadmin) {
        return (
            <>
                <Head title="Access Denied" />
                <div className="min-h-screen bg-[#090d16] text-neutral-100 p-6 flex items-center justify-center">
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 shadow-2xl p-8 max-w-md w-full flex flex-col items-center gap-5 text-center">
                        <div className="p-4 bg-rose-500/10 text-rose-400 rounded-2xl border border-rose-500/20">
                            <ShieldAlert className="w-12 h-12" />
                        </div>
                        <div className="flex flex-col gap-2">
                            <h2 className="text-xl font-bold text-white">Superadmin Access Required</h2>
                            <p className="text-neutral-400 text-sm">
                                You do not have permission to view this page. Only system administrators can manage configurations, users, and departments.
                            </p>
                        </div>
                        <Link
                            href="/dashboard"
                            className="bg-neutral-800 hover:bg-neutral-750 text-neutral-300 font-semibold px-6 py-2.5 rounded-xl border border-neutral-700/60 transition-all duration-200 text-sm w-full"
                        >
                            Back to Dashboard
                        </Link>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="System Utilities — PPh 23 Converter" />
            <div className="min-h-screen bg-[#090d16] text-neutral-100 p-6 flex flex-col gap-6">
                
                {/* Header */}
                <div className="flex flex-col gap-1 border-b border-neutral-800 pb-4">
                    <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <Users className="text-indigo-500 w-7 h-7" />
                        System Utilities
                    </h1>
                    <p className="text-neutral-400 text-sm">
                        Manage database integrations, user account registrations, and corporate departments.
                    </p>
                </div>

                {/* Main Utilities Configuration Tabs */}
                <div className="bg-[#0f1524] rounded-2xl border border-neutral-800/80 shadow-2xl p-6">
                    
                    {/* Tab Navigation */}
                    <div className="flex border-b border-neutral-800 pb-4 gap-4 mb-6">
                        <button
                            onClick={() => setActiveTab('odoo')}
                            className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                activeTab === 'odoo'
                                    ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20'
                                    : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/30'
                            }`}
                        >
                            <Database className="w-4 h-4" />
                            Odoo Credentials
                        </button>
                        <button
                            onClick={() => setActiveTab('users')}
                            className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                activeTab === 'users'
                                    ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20'
                                    : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/30'
                            }`}
                        >
                            <Users className="w-4 h-4" />
                            User Management
                        </button>
                        <button
                            onClick={() => setActiveTab('departments')}
                            className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                activeTab === 'departments'
                                    ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20'
                                    : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/30'
                            }`}
                        >
                            <Building className="w-4 h-4" />
                            Departments
                        </button>
                    </div>

                    {/* Tab 1: Odoo Credentials */}
                    {activeTab === 'odoo' && (
                        <div className="max-w-xl">
                            <form onSubmit={handleSaveOdooConfig} className="flex flex-col gap-4">
                                <div>
                                    <h3 className="text-lg font-semibold text-white flex items-center gap-2">
                                        <Database className="w-5 h-5 text-indigo-400" />
                                        Odoo server integration credentials
                                    </h3>
                                    <p className="text-neutral-400 text-xs mt-0.5">
                                        Specify connection details to the Odoo instance. The password will be stored encrypted on the server, and normal users will be able to perform Odoo sync operations using these saved credentials.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label className="text-xs font-semibold text-neutral-300">Odoo URL</label>
                                    <input
                                        type="url"
                                        value={odooUrl}
                                        onChange={(e) => setOdooUrl(e.target.value)}
                                        placeholder="https://company.odoo.com"
                                        className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                        required
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label className="text-xs font-semibold text-neutral-300">Database Name</label>
                                    <input
                                        type="text"
                                        value={odooDb}
                                        onChange={(e) => setOdooDb(e.target.value)}
                                        placeholder="e.g. LIVE"
                                        className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                        required
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label className="text-xs font-semibold text-neutral-300">User Email / Login</label>
                                    <input
                                        type="text"
                                        value={odooUser}
                                        onChange={(e) => setOdooUser(e.target.value)}
                                        placeholder="user@company.com"
                                        className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                        required
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label className="text-xs font-semibold text-neutral-300">API Key / Password</label>
                                    <input
                                        type="password"
                                        value={odooPassword}
                                        onChange={(e) => setOdooPassword(e.target.value)}
                                        placeholder="••••••••••••••••"
                                        className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                        required
                                    />
                                </div>

                                <div className="flex gap-3 mt-2">
                                    <button
                                        type="submit"
                                        disabled={isSavingOdoo}
                                        className="flex-1 py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-all duration-200 shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                                    >
                                        {isSavingOdoo && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                                        Save Config
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleTestOdooConnection}
                                        disabled={isTestingConn}
                                        className="flex-1 py-2.5 px-4 rounded-lg bg-neutral-800 hover:bg-neutral-750 text-neutral-200 text-sm font-semibold border border-neutral-700/60 transition-all duration-200 shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                                    >
                                        {isTestingConn && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                                        Test Connection
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Tab 2: User Management */}
                    {activeTab === 'users' && (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center justify-between pb-2">
                                <div>
                                    <h3 className="text-lg font-semibold text-white">Registered Accounts</h3>
                                    <p className="text-neutral-400 text-xs mt-0.5">Create and update accounts to access the application.</p>
                                </div>
                                <button
                                    onClick={() => handleOpenUserModal(null)}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-3.5 py-2 rounded-lg flex items-center gap-1.5 transition-all shadow"
                                >
                                    <Plus className="w-4 h-4" /> Add User
                                </button>
                            </div>

                            {isUsersLoading ? (
                                <div className="py-12 flex justify-center">
                                    <RefreshCw className="w-8 h-8 text-indigo-400 animate-spin" />
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-neutral-800/80 bg-neutral-950/20">
                                    <table className="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr className="bg-neutral-900/60 border-b border-neutral-850 text-neutral-300 font-semibold">
                                                <th className="p-3.5">Name</th>
                                                <th className="p-3.5">Email</th>
                                                <th className="p-3.5 text-center">Role</th>
                                                <th className="p-3.5">Department</th>
                                                <th className="p-3.5 pr-6 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-850 text-neutral-400">
                                            {users.length > 0 ? (
                                                users.map((user) => (
                                                    <tr key={user.id} className="hover:bg-neutral-900/20 transition-all">
                                                        <td className="p-3.5 font-semibold text-neutral-200">{user.name}</td>
                                                        <td className="p-3.5 font-mono text-neutral-400">{user.email}</td>
                                                        <td className="p-3.5 text-center">
                                                            <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                                                                user.role === 'superadmin' 
                                                                    ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' 
                                                                    : 'bg-neutral-800 text-neutral-400 border border-neutral-700/50'
                                                            }`}>
                                                                {user.role}
                                                            </span>
                                                        </td>
                                                        <td className="p-3.5">
                                                            {user.department ? (
                                                                <span className="text-neutral-300 font-medium">
                                                                    {user.department.name} 
                                                                    {user.department.code && <span className="text-neutral-500 text-[10px] ml-1 bg-neutral-900 px-1.5 py-0.5 rounded border border-neutral-850 font-mono font-normal">#{user.department.code}</span>}
                                                                </span>
                                                            ) : (
                                                                <span className="text-neutral-600 italic">None</span>
                                                            )}
                                                        </td>
                                                        <td className="p-3.5 text-center">
                                                            <div className="flex items-center justify-center gap-2">
                                                                <button
                                                                    onClick={() => handleOpenUserModal(user)}
                                                                    className="p-1 text-neutral-500 hover:text-indigo-400 rounded transition"
                                                                    title="Edit User"
                                                                >
                                                                    <Edit className="w-4 h-4" />
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDeleteUser(user)}
                                                                    disabled={currentUser.id === user.id}
                                                                    className={`p-1 rounded transition ${
                                                                        currentUser.id === user.id 
                                                                            ? 'text-neutral-800 cursor-not-allowed' 
                                                                            : 'text-neutral-500 hover:text-rose-400'
                                                                    }`}
                                                                    title="Delete User"
                                                                >
                                                                    <Trash2 className="w-4 h-4" />
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={5} className="p-8 text-center text-neutral-500 font-semibold">
                                                        No users found.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {/* System Utilities Passcode Section */}
                            <div className="border-t border-neutral-800/80 pt-8 mt-6 max-w-xl">
                                <form onSubmit={handleSaveUnlockPassword} className="flex flex-col gap-4">
                                    <div>
                                        <h3 className="text-lg font-semibold text-white flex items-center gap-2">
                                            <Lock className="w-5 h-5 text-indigo-400" />
                                            System Utilities Passcode
                                        </h3>
                                        <p className="text-neutral-400 text-xs mt-0.5">
                                            Manage the passcode required to access these system utilities. Only Superadmins can view or edit this setting.
                                        </p>
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <label className="text-xs font-semibold text-neutral-300">Unlock Passcode</label>
                                        <div className="flex gap-3">
                                            <input
                                                type="text"
                                                value={sysPasscode}
                                                onChange={(e) => setSysPasscode(e.target.value)}
                                                placeholder="Enter new passcode"
                                                className="bg-neutral-900 border border-neutral-850 rounded-lg px-3.5 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500 flex-1 font-mono"
                                                required
                                                minLength={4}
                                            />
                                            <button
                                                type="submit"
                                                disabled={isSavingPasscode}
                                                className="py-2 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 min-w-[120px]"
                                            >
                                                {isSavingPasscode && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                                                Save Passcode
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}

                    {/* Tab 3: Department Management */}
                    {activeTab === 'departments' && (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center justify-between pb-2">
                                <div>
                                    <h3 className="text-lg font-semibold text-white">Corporate Departments</h3>
                                    <p className="text-neutral-400 text-xs mt-0.5">Manage departments that users can be assigned to.</p>
                                </div>
                                <button
                                    onClick={() => handleOpenDeptModal(null)}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-3.5 py-2 rounded-lg flex items-center gap-1.5 transition-all shadow"
                                >
                                    <Plus className="w-4 h-4" /> Add Department
                                </button>
                            </div>

                            {isDeptsLoading ? (
                                <div className="py-12 flex justify-center">
                                    <RefreshCw className="w-8 h-8 text-indigo-400 animate-spin" />
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-neutral-800/80 bg-neutral-950/20">
                                    <table className="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr className="bg-neutral-900/60 border-b border-neutral-850 text-neutral-300 font-semibold">
                                                <th className="p-3.5">Code</th>
                                                <th className="p-3.5">Department Name</th>
                                                <th className="p-3.5 text-center">Assigned Users</th>
                                                <th className="p-3.5 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-850 text-neutral-400">
                                            {departments.length > 0 ? (
                                                departments.map((dept) => (
                                                    <tr key={dept.id} className="hover:bg-neutral-900/20 transition-all">
                                                        <td className="p-3.5 font-mono text-indigo-400 font-bold">{dept.code || '-'}</td>
                                                        <td className="p-3.5 font-semibold text-neutral-200">{dept.name}</td>
                                                        <td className="p-3.5 text-center font-semibold text-neutral-300">
                                                            {dept.users_count ?? 0}
                                                        </td>
                                                        <td className="p-3.5 text-center">
                                                            <div className="flex items-center justify-center gap-2">
                                                                <button
                                                                    onClick={() => handleOpenDeptModal(dept)}
                                                                    className="p-1 text-neutral-500 hover:text-indigo-400 rounded transition"
                                                                    title="Edit Department"
                                                                >
                                                                    <Edit className="w-4 h-4" />
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDeleteDept(dept)}
                                                                    disabled={!!dept.users_count && dept.users_count > 0}
                                                                    className={`p-1 rounded transition ${
                                                                        !!dept.users_count && dept.users_count > 0 
                                                                            ? 'text-neutral-800 cursor-not-allowed' 
                                                                            : 'text-neutral-500 hover:text-rose-400'
                                                                    }`}
                                                                    title="Delete Department"
                                                                >
                                                                    <Trash2 className="w-4 h-4" />
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={4} className="p-8 text-center text-neutral-500 font-semibold">
                                                        No departments configured.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                </div>
            </div>

            {/* --- Modals --- */}
            {/* User Form Modal */}
            {userModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800 shadow-2xl max-w-md w-full p-6 animate-fade-in flex flex-col gap-4">
                        <div className="flex items-center justify-between border-b border-neutral-850 pb-3">
                            <h3 className="text-base font-bold text-white">
                                {editingUser ? 'Edit User details' : 'Register New User'}
                            </h3>
                            <button onClick={() => setUserModalOpen(false)} className="text-neutral-500 hover:text-neutral-200 transition">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveUser} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">Name</label>
                                <input
                                    type="text"
                                    value={userName}
                                    onChange={(e) => setUserName(e.target.value)}
                                    placeholder="Enter full name"
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                    required
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">Email Address</label>
                                <input
                                    type="email"
                                    value={userEmail}
                                    onChange={(e) => setUserEmail(e.target.value)}
                                    placeholder="user@example.com"
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                    required
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">
                                    Password {editingUser && <span className="text-neutral-500 font-normal">(leave blank to keep current)</span>}
                                </label>
                                <input
                                    type="password"
                                    value={userPassword}
                                    onChange={(e) => setUserPassword(e.target.value)}
                                    placeholder={editingUser ? "••••••••" : "Enter password (min 8 chars)"}
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                    required={!editingUser}
                                    minLength={8}
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">Access Level (Role)</label>
                                <select
                                    value={userRole}
                                    onChange={(e) => setUserRole(e.target.value)}
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="user">User (Standard Access)</option>
                                    <option value="superadmin">Superadmin (All Access)</option>
                                </select>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">Department Association</label>
                                <select
                                    value={userDeptId}
                                    onChange={(e) => setUserDeptId(e.target.value)}
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="">No Department Assigned</option>
                                    {departments.map((dept) => (
                                        <option key={dept.id} value={dept.id}>
                                            {dept.name} {dept.code ? `(${dept.code})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex gap-3 justify-end border-t border-neutral-850 pt-4 mt-2">
                                <button
                                    type="button"
                                    onClick={() => setUserModalOpen(false)}
                                    className="py-2 px-4 rounded-lg bg-neutral-800 hover:bg-neutral-750 text-neutral-200 text-xs font-semibold border border-neutral-700/60 transition cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSavingUser}
                                    className="py-2 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                    {isSavingUser && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                                    Save User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Department Form Modal */}
            {deptModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div className="bg-[#0f1524] rounded-2xl border border-neutral-800 shadow-2xl max-w-sm w-full p-6 animate-fade-in flex flex-col gap-4">
                        <div className="flex items-center justify-between border-b border-neutral-850 pb-3">
                            <h3 className="text-base font-bold text-white">
                                {editingDept ? 'Edit Department' : 'Create New Department'}
                            </h3>
                            <button onClick={() => setDeptModalOpen(false)} className="text-neutral-500 hover:text-neutral-200 transition">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveDept} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">Department Name</label>
                                <input
                                    type="text"
                                    value={deptName}
                                    onChange={(e) => setDeptName(e.target.value)}
                                    placeholder="e.g. Accounting"
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500"
                                    required
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-semibold text-neutral-300">Department Code (Optional)</label>
                                <input
                                    type="text"
                                    value={deptCode}
                                    onChange={(e) => setDeptCode(e.target.value.toUpperCase())}
                                    placeholder="e.g. ACCT"
                                    className="bg-neutral-900 border border-neutral-850 rounded-lg px-3 py-2 text-sm text-neutral-200 focus:outline-none focus:border-indigo-500 font-mono uppercase"
                                    maxLength={10}
                                />
                            </div>

                            <div className="flex gap-3 justify-end border-t border-neutral-850 pt-4 mt-2">
                                <button
                                    type="button"
                                    onClick={() => setDeptModalOpen(false)}
                                    className="py-2 px-4 rounded-lg bg-neutral-800 hover:bg-neutral-750 text-neutral-200 text-xs font-semibold border border-neutral-700/60 transition cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSavingDept}
                                    className="py-2 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                    {isSavingDept && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                                    Save Department
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
