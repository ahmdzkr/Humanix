import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

/** Jarak antar-bagian form mengikuti desain (40px pada 1920px), minimum 18px. */
const sectionGap = { gap: 'max(18px, calc(var(--u) * 40))' };

export default function Login({ status, canResetPassword }: Props) {
    return (
        <AuthLayout
            title="Masuk ke HUMANIX"
            description="Gunakan email dan kata sandi akun perusahaan Anda."
        >
            <Head title="Masuk" />

            {status && (
                <div className="mb-4 rounded-control bg-success/15 px-3 py-2 text-sm font-medium text-green-300">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col"
                style={sectionGap}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="nama@perusahaan.co.id"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Kata Sandi</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Masukkan kata sandi"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="-mt-1 flex items-start justify-between gap-3">
                            <div className="mt-1 flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="remember"
                                    className="font-normal"
                                >
                                    Ingat saya
                                </Label>
                            </div>
                            {canResetPassword && (
                                <TextLink
                                    href={request()}
                                    className="text-xs font-medium text-white no-underline hover:underline"
                                    tabIndex={5}
                                >
                                    Lupa kata sandi?
                                </TextLink>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="auth-submit w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Masuk
                        </Button>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
