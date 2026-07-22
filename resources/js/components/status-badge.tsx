import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusColor } from '@/types/import';

const colorClasses: Record<StatusColor, string> = {
    gray: 'border-transparent bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    success:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
    warning:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
    danger: 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
};

export function StatusBadge({
    color,
    label,
}: {
    color: StatusColor;
    label: string;
}) {
    return <Badge className={cn(colorClasses[color])}>{label}</Badge>;
}
