import { ButtonHTMLAttributes } from 'react';

export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-interactive border border-transparent bg-danger px-4 py-2 text-xs font-semibold uppercase tracking-widest text-onPrimary transition duration-150 ease-in-out hover:bg-danger/90 focus:outline-none focus:ring-2 focus:ring-focusRing focus:ring-offset-2 active:bg-danger/80 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
