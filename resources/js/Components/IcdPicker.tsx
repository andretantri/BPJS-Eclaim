import { useEffect, useState } from 'react';
import { Loader2, Search, X } from 'lucide-react';

export interface IcdOption {
    id: number;
    kode: string;
    deskripsi_id: string;
    kategori?: string | null;
}

interface IcdPickerProps {
    type: 'icd10' | 'icd9cm';
    value: string[];
    onChange: (codes: string[]) => void;
    initialOptions?: IcdOption[];
    multiple?: boolean;
    max?: number;
    disabled?: boolean;
    placeholder?: string;
    error?: string;
}

const mergeOptions = (current: IcdOption[], incoming: IcdOption[]): IcdOption[] => {
    const byCode = new Map(current.map((option) => [option.kode, option]));

    incoming.forEach((option) => byCode.set(option.kode, option));

    return Array.from(byCode.values()).sort((a, b) => a.kode.localeCompare(b.kode));
};

export default function IcdPicker({
    type,
    value,
    onChange,
    initialOptions = [],
    multiple = false,
    max = 1,
    disabled = false,
    placeholder,
    error,
}: IcdPickerProps) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<IcdOption[]>(initialOptions);
    const [options, setOptions] = useState<IcdOption[]>(initialOptions);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setResults(initialOptions);
        setOptions((current) => mergeOptions(current, initialOptions));
    }, [initialOptions]);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            setResults(initialOptions);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);

            try {
                const endpoint = type === 'icd10' ? route('api.icd10.search') : route('api.icd9cm.search');
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat referensi ICD.');
                }

                const payload = (await response.json()) as { data?: IcdOption[] };
                const found = payload.data ?? [];

                setResults(found);
                setOptions((current) => mergeOptions(current, found));
            } catch {
                if (!controller.signal.aborted) {
                    setResults([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query, type, initialOptions]);

    const select = (code: string) => {
        if (disabled) {
            return;
        }

        if (!multiple) {
            onChange(value[0] === code ? [] : [code]);
            setQuery('');
            setResults([]);

            return;
        }

        if (value.includes(code)) {
            onChange(value.filter((item) => item !== code));

            return;
        }

        if (value.length >= max) {
            return;
        }

        onChange([...value, code]);
        setQuery('');
        setResults([]);
    };

    const selected = value
        .map((code) => options.find((option) => option.kode === code))
        .filter((option): option is IcdOption => option !== undefined);

    return (
        <div className="space-y-1.5">
            <div className={`flex flex-wrap items-center gap-1.5 px-2 py-1.5 border rounded-lg bg-white ${error ? 'border-rose-400' : 'border-slate-300'}`}>
                {selected.map((option) => (
                    <span key={option.kode} className="inline-flex items-center gap-1 pl-2 pr-1 py-1 bg-teal-50 text-teal-800 border border-teal-200 rounded text-[11px] font-medium">
                        <span className="font-mono">{option.kode}</span>
                        {option.deskripsi_id && <span className="font-normal text-teal-700/80 max-w-[180px] truncate">{option.deskripsi_id}</span>}
                        {!disabled && (
                            <button type="button" onClick={() => select(option.kode)} className="p-0.5 hover:bg-teal-100 rounded" aria-label={`Hapus ${option.kode}`}>
                                <X className="w-3 h-3" />
                            </button>
                        )}
                    </span>
                ))}
                {(!multiple || value.length < max) && (
                    <div className="flex flex-1 items-center gap-1.5 min-w-[140px]">
                        {loading ? (
                            <Loader2 className="w-3.5 h-3.5 text-slate-400 animate-spin" />
                        ) : (
                            <Search className="w-3.5 h-3.5 text-slate-400" />
                        )}
                        <input
                            type="text"
                            value={query}
                            disabled={disabled}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={placeholder ?? (type === 'icd10' ? 'Cari kode atau diagnosis…' : 'Cari kode atau prosedur…')}
                            className="flex-1 min-w-0 py-1 text-xs outline-none disabled:bg-transparent"
                        />
                    </div>
                )}
            </div>

            {query.trim().length >= 2 && results.length > 0 && (
                <div className="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
                    {results.map((option) => {
                        const isSelected = value.includes(option.kode);

                        return (
                            <button
                                key={option.kode}
                                type="button"
                                disabled={disabled || isSelected || (multiple && value.length >= max)}
                                onClick={() => select(option.kode)}
                                className="w-full px-2.5 py-2 text-left flex items-start gap-2 hover:bg-slate-50 disabled:opacity-50"
                            >
                                <span className="font-mono text-[11px] font-semibold text-teal-700 shrink-0">{option.kode}</span>
                                <span className="text-[11px] text-slate-700 leading-snug">
                                    {option.deskripsi_id}
                                    {option.kategori && <span className="block text-[10px] text-slate-400">{option.kategori}</span>}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            {query.trim().length >= 2 && !loading && results.length === 0 && (
                <p className="text-[10px] text-slate-400">Tidak ada kode aktif yang cocok.</p>
            )}

            {error && <p className="text-[10px] text-rose-600">{error}</p>}
            {multiple && <p className="text-[10px] text-slate-400">Maksimal {max} kode. Pilih dari referensi aktif.</p>}
        </div>
    );
}
