import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-interactive border border-border bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-text shadow-resting transition duration-150 ease-in-out hover:bg-background focus:outline-none focus:ring-2 focus:ring-focusRing focus:ring-offset-2 disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
