import type { AppNotification } from '@/lib/notifications';
import type { Auth, Flash } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            notificationCount: number;
            latestNotifications: AppNotification[];
            flash: Flash;
            [key: string]: unknown;
        };
    }
}
