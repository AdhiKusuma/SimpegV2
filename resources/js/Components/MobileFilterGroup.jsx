import { SlidersHorizontal } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';

export default function MobileFilterGroup({ id, activeCount = 0, children, toggleClassName = '', fieldsClassName = '' }) {
    const [open, setOpen] = useState(false);
    const toggleRef = useRef(null);

    useLayoutEffect(() => {
        const toggle = toggleRef.current;
        const form = toggle?.closest('form');
        const shell = form?.closest(
            'section, .simpeg-card, .simpeg-card-soft, .simpeg-form-card, .simpeg-filter-shell, .simpeg-filter-only-shell, .simpeg-audit-filter-card, .simpeg-quota-filter-card, .employees-index-filter-box, .simpeg-table-controls, .employees-index-controls',
        ) || form?.parentElement;

        if (!form || !shell) {
            return;
        }

        form.classList.add('simpeg-unified-filter-form');

        shell.classList.add('simpeg-filter-list-shell');

        // Older list pages still put the filter and table in one card wrapper.
        // Flatten only that wrapper at runtime; the table surface is styled by
        // the shared list rules on its own direct child.
        const flatten = (element, styles) => {
            Object.entries(styles).forEach(([property, value]) => {
                element.style.setProperty(property, value, 'important');
            });
        };

        flatten(shell, {
            'box-sizing': 'border-box',
            height: 'auto',
            'min-height': '0',
            margin: '0 0 1.25rem',
            padding: '0',
            overflow: 'visible',
            border: '0',
            'border-radius': '0',
            background: 'transparent',
            'background-color': 'transparent',
            'box-shadow': 'none',
        });

        flatten(form, {
            height: 'auto',
            'min-height': '0',
            margin: '0 0 1rem',
            padding: '0',
            border: '0',
            'border-radius': '0',
            background: 'transparent',
            'background-color': 'transparent',
            'box-shadow': 'none',
        });
    }, []);

    return (
        <>
            <button
                type="button"
                ref={toggleRef}
                className={`simpeg-mobile-filter-toggle simpeg-filter-dropdown-trigger simpeg-unified-filter-trigger simpeg-utility ${toggleClassName}`.trim()}
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen((current) => !current)}
            >
                <SlidersHorizontal aria-hidden="true" className="h-4 w-4" />
                <span>Filter</span>
                {activeCount > 0 && (
                    <span
                        className="simpeg-filter-active-count"
                        style={{
                            backgroundColor: '#0D5FE0',
                            borderColor: '#0D5FE0',
                            color: '#FFFFFF',
                            WebkitTextFillColor: '#FFFFFF',
                        }}
                    >
                        {activeCount}
                    </span>
                )}
            </button>
            <div id={id} className={`simpeg-mobile-filter-fields simpeg-filter-dropdown-panel simpeg-unified-filter-panel ${open ? 'is-open' : ''} ${fieldsClassName}`.trim()}>
                {children}
            </div>
        </>
    );
}
