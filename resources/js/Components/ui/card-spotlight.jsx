import { cn } from '../../lib/utils';

export function CardSpotlight({ className, children, ...props }) {
    return <div className={cn('simpeg-card-spotlight', className)} {...props}>{children}</div>;
}

