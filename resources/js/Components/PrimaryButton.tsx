import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    type = 'submit',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            type={type}
            {...props}
            className={
                `inline-flex items-center rounded-interactive border border-transparent bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-onPrimary transition duration-150 ease-in-out hover:bg-primaryHover focus:outline-none focus:ring-2 focus:ring-focusRing focus:ring-offset-2 active:bg-primaryHover ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
