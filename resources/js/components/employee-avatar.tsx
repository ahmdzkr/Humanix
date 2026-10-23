import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export function EmployeeAvatar({
    name,
    photoUrl,
    className,
}: {
    name: string;
    photoUrl: string | null;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar
            className={cn('shrink-0 overflow-hidden rounded-full', className)}
        >
            {photoUrl && (
                <AvatarImage
                    src={photoUrl}
                    alt={`Foto ${name}`}
                    className="object-cover"
                />
            )}
            <AvatarFallback className="bg-secondary font-semibold text-primary">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
