import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

export default function MobileFilterGroup({ id, activeCount = 0, children, toggleClassName = '', fieldsClassName = '' }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                className={`simpeg-mobile-filter-toggle simpeg-utility ${toggleClassName}`.trim()}
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen((current) => !current)}
            >
                <SlidersHorizontal aria-hidden="true" className="h-4 w-4" />
                <span>Filter</span>
                {activeCount > 0 && <span className="simpeg-filter-active-count">{activeCount}</span>}
            </button>
            <div id={id} className={`simpeg-mobile-filter-fields${open ? ' is-open' : ''} ${fieldsClassName}`.trim()}>
                {children}
            </div>
        </>
    );
}
