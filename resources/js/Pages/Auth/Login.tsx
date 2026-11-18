import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { LogIn } from 'lucide-react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Masuk ke Sistem Casemix" />

            {status && (
                <div className="mb-4 text-xs font-medium text-emerald-600 bg-emerald-50 p-2.5 rounded-lg border border-emerald-200">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="email" value="Email Petugas" className="text-xs font-semibold text-slate-700" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full text-xs"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-1 text-xs" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" className="text-xs font-semibold text-slate-700" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full text-xs"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-1 text-xs" />
                </div>

                <div className="flex items-center justify-between text-xs">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked as false)}
                        />
                        <span className="ms-2 text-slate-600">Ingat sesi saya</span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-teal-600 hover:text-teal-800 hover:underline"
                        >
                            Lupa password?
                        </Link>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm"
                >
                    <LogIn className="w-4 h-4" />
                    <span>Masuk ke Ruang Kerja</span>
                </button>
            </form>
        </GuestLayout>
    );
}
