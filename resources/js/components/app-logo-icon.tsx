import type { ImgHTMLAttributes } from 'react';

// Ikon logo HUMANIX (potongan dari public/images/logo-humanix.png).
export default function AppLogoIcon({
    alt = 'HUMANIX',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/humanix-icon.png"
            alt={alt}
            draggable={false}
            {...props}
        />
    );
}
