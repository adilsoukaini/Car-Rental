import {
    forwardRef,
    InputHTMLAttributes,
    useEffect,
    useImperativeHandle,
    useRef,
} from 'react';

export interface TextInputRef {
    focus: () => void;
}

export default forwardRef<TextInputRef, InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean }>(
    function TextInput(
        { type = 'text', className = '', isFocused = false, ...props },
        ref,
    ) {
        const localRef = useRef<HTMLInputElement>(null);

        useImperativeHandle(ref, () => ({
            focus: () => localRef.current?.focus(),
        }));

        useEffect(() => {
            if (isFocused) {
                localRef.current?.focus();
            }
        }, [isFocused]);

        return (
            <input
                {...props}
                type={type}
                className={
                    'rounded-interactive border-border bg-surface text-text shadow-sm focus:border-primary focus:ring-focusRing ' +
                    className
                }
                ref={localRef}
            />
        );
    },
);
