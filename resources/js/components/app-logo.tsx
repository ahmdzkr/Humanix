import AppLogoIcon from '@/components/app-logo-icon';

// Logo lengkap saat sidebar terbuka, ikon saja saat sidebar diciutkan.
export default function AppLogo() {
    return (
        <>
            <img
                src="/images/logo-humanix.png"
                alt="HUMANIX — Human Resource Information System"
                className="h-12 w-auto max-w-full object-contain group-data-[collapsible=icon]:hidden"
                draggable={false}
            />
            <AppLogoIcon className="hidden size-8 object-contain group-data-[collapsible=icon]:block" />
        </>
    );
}
