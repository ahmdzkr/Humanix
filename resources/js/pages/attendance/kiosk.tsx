import { Head, Link } from '@inertiajs/react';
import { Html5Qrcode } from 'html5-qrcode';
import {
    ArrowLeft,
    Camera,
    CameraOff,
    CheckCircle2,
    Keyboard,
    XCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { formatJam, formatTanggal } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as attendanceIndex, scan } from '@/routes/attendance';

type Result = {
    ok: boolean;
    message: string;
    employee: string | null;
    type?: 'check_in' | 'check_out';
};

const READER_ID = 'kiosk-qr-reader';
/** Token yang sama diabaikan selama jeda ini agar satu kartu tidak terbaca berkali-kali. */
const SAME_TOKEN_COOLDOWN_MS = 6000;
const RESULT_VISIBLE_MS = 5000;

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export default function Kiosk() {
    const [now, setNow] = useState(() => new Date());
    const [result, setResult] = useState<Result | null>(null);
    const [busy, setBusy] = useState(false);
    const [cameraState, setCameraState] = useState<
        'starting' | 'on' | 'off' | 'error'
    >('starting');
    const [manualToken, setManualToken] = useState('');
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const lastScan = useRef<{ token: string; at: number }>({
        token: '',
        at: 0,
    });
    const busyRef = useRef(false);
    const clearTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 1000);

        return () => clearInterval(timer);
    }, []);

    const submitToken = useCallback(async (token: string) => {
        const trimmed = token.trim();
        const at = Date.now();

        if (!trimmed || busyRef.current) {
            return;
        }

        if (
            lastScan.current.token === trimmed &&
            at - lastScan.current.at < SAME_TOKEN_COOLDOWN_MS
        ) {
            return;
        }

        lastScan.current = { token: trimmed, at };
        busyRef.current = true;
        setBusy(true);

        try {
            const response = await fetch(scan.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ token: trimmed }),
            });

            if (response.status === 419) {
                setResult({
                    ok: false,
                    message: 'Sesi kios kedaluwarsa. Muat ulang halaman.',
                    employee: null,
                });
            } else if (response.status === 429) {
                setResult({
                    ok: false,
                    message: 'Terlalu banyak scan. Tunggu sebentar.',
                    employee: null,
                });
            } else {
                setResult((await response.json()) as Result);
            }
        } catch {
            setResult({
                ok: false,
                message: 'Tidak terhubung ke server. Periksa jaringan.',
                employee: null,
            });
        } finally {
            busyRef.current = false;
            setBusy(false);
            clearTimeout(clearTimer.current);
            clearTimer.current = setTimeout(
                () => setResult(null),
                RESULT_VISIBLE_MS,
            );
        }
    }, []);

    // State awal sudah "starting"; tombol "Nyalakan kamera" menyetelnya sendiri.
    // html5-qrcode melempar error jika start/stop dipanggil saat transisi lain berjalan
    // (mis. React StrictMode memasang komponen dua kali). Semua transisi diantrikan di sini.
    const transition = useRef<Promise<unknown>>(Promise.resolve());
    const mounted = useRef(false);

    const startCamera = useCallback(() => {
        transition.current = transition.current.then(() => {
            const scanner = scannerRef.current ?? new Html5Qrcode(READER_ID);
            scannerRef.current = scanner;

            if (scanner.isScanning) {
                setCameraState('on');

                return;
            }

            return scanner
                .start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    (decoded) => void submitToken(decoded),
                    () => undefined,
                )
                .then(() => setCameraState('on'))
                .catch(() => setCameraState('error'));
        });

        return transition.current;
    }, [submitToken]);

    const stopCamera = useCallback(() => {
        transition.current = transition.current.then(async () => {
            if (scannerRef.current?.isScanning) {
                await scannerRef.current.stop().catch(() => undefined);
            }

            setCameraState('off');
        });

        return transition.current;
    }, []);

    useEffect(() => {
        mounted.current = true;
        void startCamera();

        return () => {
            mounted.current = false;
            // Matikan kamera hanya jika komponen benar-benar dilepas (bukan remount StrictMode).
            transition.current = transition.current.then(async () => {
                if (!mounted.current && scannerRef.current?.isScanning) {
                    await scannerRef.current.stop().catch(() => undefined);
                }
            });
        };
    }, [startCamera]);

    const submitManual = (event: FormEvent) => {
        event.preventDefault();
        void submitToken(manualToken);
        setManualToken('');
    };

    return (
        <div className="flex min-h-svh flex-col bg-navy text-white">
            <Head title="Kios Absensi" />
            <header className="flex items-center justify-between gap-4 px-4 py-4 md:px-8">
                <div className="flex items-center gap-3">
                    <AppLogoIcon className="size-10 rounded-control bg-white p-1" />
                    <div>
                        <p className="text-lg font-extrabold tracking-wide">
                            HUMANIX
                        </p>
                        <p className="text-xs text-white/60">Kios Absensi</p>
                    </div>
                </div>
                <Button
                    asChild
                    variant="outline"
                    className="rounded-control border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                >
                    <Link href={attendanceIndex.url()}>
                        <ArrowLeft className="size-4" />
                        Keluar dari kios
                    </Link>
                </Button>
            </header>

            <main className="grid flex-1 gap-6 px-4 pb-8 md:px-8 lg:grid-cols-2">
                <section className="flex flex-col items-center justify-center text-center">
                    <p className="text-sm tracking-widest text-cyan uppercase">
                        {formatTanggal(now)}
                    </p>
                    <p className="mt-2 font-mono text-6xl font-bold md:text-7xl">
                        {formatJam(now).replace(' WIB', '')}
                        <span className="ml-2 text-2xl text-white/60">WIB</span>
                    </p>
                    <p className="mt-6 max-w-sm text-white/70">
                        Arahkan QR pada ID Card ke kamera. Scan pertama hari ini
                        dicatat sebagai absen masuk, scan berikutnya sebagai
                        absen pulang.
                    </p>

                    <div
                        aria-live="assertive"
                        className={cn(
                            'mt-8 w-full max-w-md rounded-card p-6 transition',
                            result === null && 'bg-white/5 text-white/50',
                            result?.ok && 'bg-success text-white',
                            result && !result.ok && 'bg-danger text-white',
                        )}
                    >
                        {result === null ? (
                            <p>{busy ? 'Memproses…' : 'Menunggu scan…'}</p>
                        ) : (
                            <div className="flex items-start gap-4 text-left">
                                {result.ok ? (
                                    <CheckCircle2 className="size-10 shrink-0" />
                                ) : (
                                    <XCircle className="size-10 shrink-0" />
                                )}
                                <div>
                                    {result.employee && (
                                        <p className="text-xl font-bold">
                                            {result.employee}
                                        </p>
                                    )}
                                    <p className="mt-1">{result.message}</p>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                <section className="flex flex-col items-center justify-center gap-4">
                    <div className="relative w-full max-w-md overflow-hidden rounded-card border-4 border-cyan/60 bg-black">
                        <div id={READER_ID} className="aspect-square w-full" />
                        {cameraState !== 'on' && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-white/70">
                                <CameraOff className="size-10" />
                                <p>
                                    {cameraState === 'starting'
                                        ? 'Menyalakan kamera…'
                                        : cameraState === 'error'
                                          ? 'Kamera tidak bisa dipakai. Izinkan akses kamera di browser, atau gunakan input manual di bawah.'
                                          : 'Kamera dimatikan.'}
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="flex gap-2">
                        {cameraState === 'on' ? (
                            <Button
                                variant="outline"
                                className="rounded-control border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                onClick={() => void stopCamera()}
                            >
                                <CameraOff className="size-4" />
                                Matikan kamera
                            </Button>
                        ) : (
                            <Button
                                className="rounded-control"
                                onClick={() => {
                                    setCameraState('starting');
                                    void startCamera();
                                }}
                            >
                                <Camera className="size-4" />
                                Nyalakan kamera
                            </Button>
                        )}
                    </div>

                    <form
                        onSubmit={submitManual}
                        className="flex w-full max-w-md items-center gap-2"
                    >
                        <label htmlFor="manual-token" className="sr-only">
                            Kode QR manual
                        </label>
                        <div className="relative flex-1">
                            <Keyboard className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-white/50" />
                            <input
                                id="manual-token"
                                value={manualToken}
                                onChange={(e) => setManualToken(e.target.value)}
                                autoComplete="off"
                                placeholder="Input manual / scanner USB"
                                className="h-11 w-full rounded-control border border-white/20 bg-white/10 pr-3 pl-9 text-sm text-white placeholder:text-white/40 focus:border-cyan focus:outline-none"
                            />
                        </div>
                        <Button
                            type="submit"
                            className="rounded-control"
                            disabled={busy || manualToken.trim() === ''}
                        >
                            Kirim
                        </Button>
                    </form>
                </section>
            </main>
        </div>
    );
}
