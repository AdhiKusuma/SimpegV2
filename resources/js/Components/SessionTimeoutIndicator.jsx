import { useEffect, useState } from 'react';
import { Timer } from 'lucide-react';

function formatTime(totalSeconds) {
    const safeSeconds = Math.max(0, totalSeconds);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;
    const twoDigits = (value) => String(value).padStart(2, '0');

    return hours > 0
        ? `${twoDigits(hours)}:${twoDigits(minutes)}:${twoDigits(seconds)}`
        : `${twoDigits(minutes)}:${twoDigits(seconds)}`;
}

export default function SessionTimeoutIndicator({ timeoutSeconds, expiresAt }) {
    const lifetime = Math.max(1, Number(timeoutSeconds) || 3600);
    const initialDeadline = Number(expiresAt) > 0
        ? Number(expiresAt) * 1000
        : Date.now() + lifetime * 1000;
    const [remainingSeconds, setRemainingSeconds] = useState(() => Math.max(0, Math.ceil((initialDeadline - Date.now()) / 1000)));

    useEffect(() => {
        let deadline = Number(expiresAt) > 0 ? Number(expiresAt) * 1000 : Date.now() + lifetime * 1000;
        let lastHeartbeat = Date.now() - 30_000;
        let requestPending = false;
        let expired = false;

        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const updateRemaining = () => {
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            setRemainingSeconds(remaining);

            if (remaining === 0 && !expired) {
                expired = true;
                fetch('/logout', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).then((response) => {
                    window.location.assign(response.url || '/login');
                }).catch(() => {
                    window.location.assign('/login');
                });
            }
        };

        const sendActivity = async () => {
            const now = Date.now();
            if (requestPending || document.hidden || now - lastHeartbeat < 30_000) {
                return;
            }

            requestPending = true;
            lastHeartbeat = now;
            try {
                const response = await fetch('/session/activity', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.redirected) {
                    window.location.assign(response.url);
                    return;
                }

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (Number(payload.expires_at) > 0) {
                    deadline = Number(payload.expires_at) * 1000;
                    updateRemaining();
                }
            } catch {
                // Keep the local countdown running if a temporary network error occurs.
            } finally {
                requestPending = false;
            }
        };

        const onActivity = () => {
            if (expired) {
                return;
            }

            deadline = Date.now() + lifetime * 1000;
            updateRemaining();
            sendActivity();
        };
        const activityEvents = ['pointerdown', 'pointermove', 'keydown', 'touchstart', 'scroll', 'click'];
        activityEvents.forEach((eventName) => window.addEventListener(eventName, onActivity, { passive: true, capture: true }));

        const clockId = window.setInterval(updateRemaining, 1000);
        const onVisibilityChange = () => {
            if (!document.hidden) {
                updateRemaining();
            }
        };
        document.addEventListener('visibilitychange', onVisibilityChange);

        return () => {
            window.clearInterval(clockId);
            activityEvents.forEach((eventName) => window.removeEventListener(eventName, onActivity, true));
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    }, [lifetime, expiresAt]);

    return (
        <div className="simpeg-session-indicator" role="timer" aria-label={`Sesi berakhir dalam ${formatTime(remainingSeconds)}`} title="Sisa waktu sesi. Diperpanjang saat ada aktivitas.">
            <Timer className="h-4 w-4 shrink-0" strokeWidth={1.8} aria-hidden="true" />
            <span className="simpeg-session-label">Sesi</span>
            <span className="simpeg-session-time" aria-live="off">{formatTime(remainingSeconds)}</span>
        </div>
    );
}
