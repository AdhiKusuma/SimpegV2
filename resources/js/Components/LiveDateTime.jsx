import { useEffect, useState } from 'react';

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});

export default function LiveDateTime() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const intervalId = window.setInterval(() => setNow(new Date()), 60 * 1000);

        return () => window.clearInterval(intervalId);
    }, []);

    const date = dateFormatter.format(now).replace(/\./g, '');

    return (
        <div
            className="simpeg-live-datetime simpeg-border hidden min-w-[10.5rem] items-center border-l pl-3 xl:flex"
            aria-label={`Tanggal saat ini ${date}`}
        >
            <span className="simpeg-muted whitespace-nowrap text-xs">{date}</span>
        </div>
    );
}
