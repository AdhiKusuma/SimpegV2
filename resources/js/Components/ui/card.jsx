import { cn } from '../../lib/utils';

export function Card({ className, ...props }) {
    return <div data-slot="card" className={cn('simpeg-card rounded-xl border border-[var(--border)] bg-[var(--card)] text-[var(--card-foreground)] shadow-sm', className)} {...props} />;
}

export function CardHeader({ className, ...props }) {
    return <div data-slot="card-header" className={cn('flex flex-col gap-1.5 p-5 sm:p-6', className)} {...props} />;
}

export function CardTitle({ as: Heading = 'h3', className, ...props }) {
    return <Heading data-slot="card-title" className={cn('text-lg font-medium text-[var(--card-foreground)]', className)} {...props} />;
}

export function CardDescription({ className, ...props }) {
    return <p data-slot="card-description" className={cn('text-sm leading-6 text-[var(--muted-foreground)]', className)} {...props} />;
}

export function CardContent({ className, ...props }) {
    return <div data-slot="card-content" className={cn('p-5 pt-0 sm:p-6 sm:pt-0', className)} {...props} />;
}

export function CardFooter({ className, ...props }) {
    return <div data-slot="card-footer" className={cn('flex items-center p-5 pt-0 sm:p-6 sm:pt-0', className)} {...props} />;
}
