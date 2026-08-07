import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type ToastType = 'success' | 'error' | 'info';

interface FlashMessage {
    message: string;
    type: ToastType;
}

const typeClasses: Record<ToastType, string> = {
    success: 'bg-success/10 border-success text-success',
    error: 'bg-danger/10 border-danger text-danger',
    info: 'bg-primary/10 border-primary text-primary',
};

/**
 * Reads `usePage().props.flash` ({ message, type } | null — shared by
 * HandleInertiaRequests from the session) and shows a fixed-position toast
 * at the bottom-right that auto-dismisses after 5 seconds. Mounted once in
 * the root layout (app.tsx).
 */
export default function ToastContainer() {
    const { flash } = usePage<{ flash?: FlashMessage | null }>().props;
    const [visible, setVisible] = useState<boolean>(false);

    useEffect(() => {
        if (!flash?.message) {
            return;
        }

        setVisible(true);

        const timer = setTimeout(() => setVisible(false), 5000);

        return () => clearTimeout(timer);
    }, [flash]);

    if (!visible || !flash?.message) {
        return null;
    }

    return (
        <div
            role="status"
            className={`fixed bottom-4 right-4 z-50 max-w-sm rounded-interactive border px-4 py-3 shadow-raised ${
                typeClasses[flash.type] ?? typeClasses.info
            }`}
        >
            {flash.message}
        </div>
    );
}
