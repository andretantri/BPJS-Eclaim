import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { ShieldCheck } from 'lucide-react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-900 p-4">
            <div className="w-full max-w-md space-y-6">
                {/* Logo and Hospital Header */}
                <div className="text-center space-y-2">
                    <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-teal-500/20 border border-teal-500/30 text-teal-400">
                        <ShieldCheck className="w-7 h-7" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-white tracking-tight">ClaimGuard</h1>
                        <p className="text-xs text-teal-400 font-medium tracking-wider uppercase">
                            Sistem Manajemen Klaim BPJS & Casemix
                        </p>
                        <p className="text-[11px] text-slate-400 mt-1">Unit Casemix & Klaim BPJS</p>
                    </div>
                </div>

                {/* Form Card */}
                <div className="w-full overflow-hidden bg-white px-7 py-6 shadow-2xl rounded-2xl border border-slate-200">
                    {children}
                </div>

                <div className="text-center text-[11px] text-slate-500">
                    ClaimGuard Casemix System · Version 2.0 (Laravel + Inertia + React)
                </div>
            </div>
        </div>
    );
}
