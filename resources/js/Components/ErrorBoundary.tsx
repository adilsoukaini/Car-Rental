import { Component, ErrorInfo, ReactNode } from 'react';
import EmptyState from '@/Components/EmptyState';

interface ErrorBoundaryProps {
    children: ReactNode;
}

interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

/**
 * React error boundary. On error, renders EmptyState ("Something went wrong")
 * with the thrown message and a "Try again" button that resets local state so
 * the subtree re-renders.
 */
export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    override state: ErrorBoundaryState = { hasError: false, error: null };

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    override componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('ErrorBoundary caught an error:', error, info);
    }

    private handleRetry = (): void => {
        this.setState({ hasError: false, error: null });
    };

    override render(): ReactNode {
        if (this.state.hasError) {
            return (
                <EmptyState
                    title="Something went wrong"
                    description={this.state.error?.message}
                    action={
                        <button
                            type="button"
                            onClick={this.handleRetry}
                            className="rounded-interactive bg-primary px-4 py-2 text-sm font-semibold text-onPrimary transition-colors hover:bg-primaryHover"
                        >
                            Try again
                        </button>
                    }
                />
            );
        }

        return this.props.children;
    }
}
