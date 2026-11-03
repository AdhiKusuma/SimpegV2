import { cn } from '../../lib/utils';

export function Card({ className, ...props }) {
    return <div className={cn('simpeg-card rounded-2xl border shadow-sm', className)} {...props} />;
}

export function CardHeader({ className, ...props }) {
    return <div className={cn('flex flex-col gap-1.5 p-5 sm:p-6', className)} {...props} />;
}

export function CardTitle({ as: Heading = 'h3', className, ...props }) {
    return <Heading className={cn('simpeg-text text-lg font-medium', className)} {...props} />;
}

export function CardDescription({ className, ...props }) {
    return <p className={cn('simpeg-muted text-sm leading-6', className)} {...props} />;
}

export function CardContent({ className, ...props }) {
    return <div className={cn('p-5 pt-0 sm:p-6 sm:pt-0', className)} {...props} />;
}

export function CardFooter({ className, ...props }) {
    return <div className={cn('flex items-center p-5 pt-0 sm:p-6 sm:pt-0', className)} {...props} />;
}
