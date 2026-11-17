import { ChevronDown, Download, FileImage, FileSpreadsheet, FileText, Printer } from 'lucide-react';
import { useState } from 'react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from './ui/dropdown-menu';
import { exportPageAsSvg, printPageAsPdf } from './pageExport';

const DEFAULT_ICON = FileSpreadsheet;

export default function ExportDropdown({ items = [], className = '', label = 'Export', title = 'Pilih format export', disabled = false }) {
    const [open, setOpen] = useState(false);
    const hasPdfExport = items.some((item) => item?.format === 'pdf' || /pdf/i.test(item?.label || ''));
    const availableItems = [
        ...items,
        { key: 'page-svg', icon: FileImage, label: 'Unduh gambar SVG', action: exportPageAsSvg },
        ...(!hasPdfExport ? [{ key: 'page-pdf', icon: Printer, label: 'Cetak / simpan PDF', action: printPageAsPdf }] : []),
    ].filter((item) => item?.href || item?.action);

    if (availableItems.length === 0 && !disabled) return null;

    if (disabled) {
        return (
            <div className={`simpeg-export-dropdown ${className}`.trim()}>
                <button type="button" disabled className="simpeg-export-trigger inline-flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold opacity-60">
                    <Download aria-hidden="true" className="h-4 w-4" />
                    <span>{label}</span>
                </button>
            </div>
        );
    }

    return (
        <div className={`simpeg-export-dropdown ${className}`.trim()}>
            <DropdownMenu open={open} onOpenChange={setOpen}>
                <DropdownMenuTrigger asChild>
                    <button type="button" className="simpeg-export-trigger inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition focus:outline-none focus:ring-4">
                        <Download aria-hidden="true" className="h-4 w-4" />
                        <span>{label}</span>
                        <ChevronDown aria-hidden="true" className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="simpeg-export-menu absolute right-0 top-[calc(100%+0.5rem)] w-72">
                    <p className="simpeg-export-menu-title">{title}</p>
                    {availableItems.map((item, index) => {
                        const Icon = item.icon || DEFAULT_ICON;
                        const key = item.key || `${item.href}-${index}`;

                        if (item.action) {
                            return <DropdownMenuItem key={key} onClick={item.action}><Icon aria-hidden="true" className="h-4 w-4" />{item.label}</DropdownMenuItem>;
                        }

                        return <DropdownMenuItem key={key} asChild><a href={item.href} target={item.target} rel={item.rel || (item.target === '_blank' ? 'noreferrer' : undefined)}><Icon aria-hidden="true" className="h-4 w-4" />{item.label}</a></DropdownMenuItem>;
                    })}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

export { FileImage, FileSpreadsheet, FileText, Printer };
