import type { AuthLayoutProps } from '@/types';

/**
 * Layout halaman masuk & auth lainnya mengikuti desain "Login_humanix" (latar gelap 3D,
 * logo di kiri, kartu form di kanan). Tata letak & skala diatur di resources/css/app.css
 * (.auth-shell / .auth-stage / .auth-card).
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="auth-shell">
            <div className="auth-stage">
                <img
                    src="/images/login-hero.webp"
                    alt="HUMANIX — Human Resource Information System"
                    width={660}
                    height={215}
                    className="auth-hero"
                />

                <main className="auth-card">
                    <h1 className="auth-title">{title}</h1>
                    {description && (
                        <p className="auth-description">{description}</p>
                    )}
                    <div className="auth-body">{children}</div>
                </main>

                <p className="auth-footer">
                    © HUMANIX, seluruh hak cipta dilindungi
                </p>
            </div>
        </div>
    );
}
