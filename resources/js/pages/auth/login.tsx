import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { useEffect } from 'react';

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    useEffect(() => {
        // Force the page body and html backgrounds to matching deep slate blue
        const originalHtmlBg = document.documentElement.style.backgroundColor;
        const originalBodyBg = document.body.style.backgroundColor;

        document.documentElement.style.backgroundColor = '#0b1329';
        document.body.style.backgroundColor = '#0b1329';

        return () => {
            // Restore on unmount
            document.documentElement.style.backgroundColor = originalHtmlBg;
            document.body.style.backgroundColor = originalBodyBg;
        };
    }, []);

    return (
        <>
            <Head title="Sign In" />

            <div className="flex min-h-screen flex-col items-center justify-center bg-[#0b1329] px-4 py-12">
                <div className="w-full max-w-[390px] flex flex-col items-center gap-5">
                    
                    {/* Harent Logo */}
                    <div className="mb-1">
                        <img 
                            src="/images/logo.png" 
                            alt="Harent logo" 
                            className="h-auto w-[230px] object-contain"
                        />
                    </div>

                    {/* Header */}
                    <h2 className="text-[13px] font-medium text-neutral-400">
                        Sign in to your account
                    </h2>

                    {/* Card container */}
                    <div className="w-full bg-[#131b2e] border border-neutral-800/80 rounded-2xl shadow-2xl p-6.5">
                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex flex-col gap-4.5">
                                        
                                        {/* Username / Email field */}
                                        <div className="flex flex-col gap-1.5">
                                            <Label 
                                                htmlFor="email" 
                                                className="text-[11px] font-bold text-neutral-300"
                                            >
                                                Email
                                            </Label>
                                            <Input
                                                id="email"
                                                type="text"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                placeholder="email@example.com"
                                                className="bg-[#0b1329] border border-neutral-850 text-neutral-200 focus:border-cyan-500 rounded-lg py-2 px-3 text-xs w-full focus:outline-none"
                                            />
                                            <InputError message={errors.email} />
                                        </div>

                                        {/* Password field */}
                                        <div className="flex flex-col gap-1.5">
                                            <Label 
                                                htmlFor="password" 
                                                className="text-[11px] font-bold text-neutral-300"
                                            >
                                                Password
                                            </Label>
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                placeholder="Password"
                                                className="bg-[#0b1329] border border-neutral-850 text-neutral-200 focus:border-cyan-500 rounded-lg py-2 px-3 text-xs w-full focus:outline-none"
                                            />
                                            <InputError message={errors.password} />
                                        </div>

                                        {/* Remember Me */}
                                        <div className="flex items-center space-x-2 mt-1">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                tabIndex={3}
                                                className="border-neutral-700 w-4 h-4 rounded data-[state=checked]:bg-cyan-500 data-[state=checked]:border-cyan-500"
                                            />
                                            <Label 
                                                htmlFor="remember" 
                                                className="text-[11px] font-medium text-neutral-400 cursor-pointer select-none"
                                            >
                                                Remember me
                                            </Label>
                                        </div>

                                        {/* Submit Button */}
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            tabIndex={4}
                                            className="w-full bg-gradient-to-r from-emerald-400 to-cyan-500 hover:from-emerald-300 hover:to-cyan-400 text-white font-bold py-2.5 rounded-lg shadow-md transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 mt-3 text-xs active:scale-[0.99]"
                                            data-test="login-button"
                                        >
                                            {processing && <Spinner className="text-white" />}
                                            Sign In
                                        </button>
                                    </div>
                                </>
                            )}
                        </Form>

                        {status && (
                            <div className="mt-4 text-center text-xs font-medium text-green-500">
                                {status}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
