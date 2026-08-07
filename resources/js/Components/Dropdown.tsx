import { Transition } from '@headlessui/react';
import { Link, InertiaLinkProps } from '@inertiajs/react';
import {
    createContext,
    Dispatch,
    PropsWithChildren,
    SetStateAction,
    useContext,
    useState,
} from 'react';

interface DropdownContextValue {
    open: boolean;
    setOpen: Dispatch<SetStateAction<boolean>>;
    toggleOpen: () => void;
}

const DropDownContext = createContext<DropdownContextValue | null>(null);

function useDropdownContext(): DropdownContextValue {
    const context = useContext(DropDownContext);

    if (!context) {
        throw new Error(
            'Dropdown.Trigger/Content must be used within a Dropdown',
        );
    }

    return context;
}

function Dropdown({ children }: PropsWithChildren) {
    const [open, setOpen] = useState(false);

    const toggleOpen = () => {
        setOpen((previousState) => !previousState);
    };

    return (
        <DropDownContext.Provider value={{ open, setOpen, toggleOpen }}>
            <div className="relative">{children}</div>
        </DropDownContext.Provider>
    );
}

function Trigger({ children }: PropsWithChildren) {
    const { open, setOpen, toggleOpen } = useDropdownContext();

    return (
        <>
            <div onClick={toggleOpen}>{children}</div>

            {open && (
                <div
                    className="fixed inset-0 z-40"
                    onClick={() => setOpen(false)}
                ></div>
            )}
        </>
    );
}

interface ContentProps extends PropsWithChildren {
    align?: 'left' | 'right';
    width?: '48';
    contentClasses?: string;
}

function Content({
    align = 'right',
    width = '48',
    contentClasses = 'py-1 bg-surface',
    children,
}: ContentProps) {
    const { open, setOpen } = useDropdownContext();

    let alignmentClasses = 'origin-top';

    if (align === 'left') {
        alignmentClasses = 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (align === 'right') {
        alignmentClasses = 'ltr:origin-top-right rtl:origin-top-left end-0';
    }

    let widthClasses = '';

    if (width === '48') {
        widthClasses = 'w-48';
    }

    return (
        <>
            <Transition
                show={open}
                enter="transition ease-out duration-200"
                enterFrom="opacity-0 scale-95"
                enterTo="opacity-100 scale-100"
                leave="transition ease-in duration-75"
                leaveFrom="opacity-100 scale-100"
                leaveTo="opacity-0 scale-95"
            >
                <div
                    className={`absolute z-50 mt-2 rounded-interactive shadow-raised ${alignmentClasses} ${widthClasses}`}
                    onClick={() => setOpen(false)}
                >
                    <div
                        className={
                            `rounded-interactive ring-1 ring-black ring-opacity-5 ` +
                            contentClasses
                        }
                    >
                        {children}
                    </div>
                </div>
            </Transition>
        </>
    );
}

function DropdownLink({
    className = '',
    children,
    ...props
}: InertiaLinkProps) {
    return (
        <Link
            {...props}
            className={
                'block w-full px-4 py-2 text-start text-sm leading-5 text-text transition duration-150 ease-in-out hover:bg-background focus:bg-background focus:outline-none ' +
                className
            }
        >
            {children}
        </Link>
    );
}

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
