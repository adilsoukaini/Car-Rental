import { LabelHTMLAttributes, PropsWithChildren } from 'react';

export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}: PropsWithChildren<LabelHTMLAttributes<HTMLLabelElement>> & {
    value?: string;
}) {
    return (
        <label
            {...props}
            className={`block text-sm font-medium text-textMuted ` + className}
        >
            {value ? value : children}
        </label>
    );
}
