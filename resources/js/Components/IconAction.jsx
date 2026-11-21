import { Link } from '@inertiajs/react';
import { Eye, Pencil, Trash2 } from 'lucide-react';

const baseClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition focus:outline-none focus:ring-4';
const editClass = 'simpeg-utility hover:bg-slate-100 focus:ring-[var(--simpeg-focus-ring)] dark:hover:bg-slate-800';
const viewClass = 'simpeg-utility hover:bg-blue-50 focus:ring-blue-600/20 dark:hover:bg-blue-950/30';
const deleteClass = 'border-red-200 text-red-700 hover:bg-red-50 focus:ring-red-600/20 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40';

export default function IconAction({ href, onClick, label, variant = 'edit', disabled = false, className: extraClass = '' }) {
    const Icon = variant === 'delete' ? Trash2 : variant === 'view' ? Eye : Pencil;
    const variantClass = variant === 'delete' ? deleteClass : variant === 'view' ? viewClass : editClass;
    const className = `${baseClass} ${variantClass} ${extraClass}`.trim();
    const icon = <Icon aria-hidden="true" className="h-4 w-4" />;

    if (href) {
        return <Link href={href} title={label} aria-label={label} className={className}>{icon}</Link>;
    }

    return <button type="button" onClick={onClick} disabled={disabled} title={label} aria-label={label} className={`${className} disabled:cursor-not-allowed disabled:opacity-50`}>{icon}</button>;
}
