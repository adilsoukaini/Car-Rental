import { Vehicle } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Calendar, Check, Lock, MapPin } from 'lucide-react';
import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import {
    formatDateTime,
    tintPrimary,
    tintSecondary,
} from '@/Pages/Bookings/checkoutShared';

export interface PriceBreakdown {
    days: number;
    dailyRate: number;
    discountPercent: number;
    totalPrice: number;
    depositAmount: number;
    promoDiscount: number;
}

/**
 * The shared checkout price summary card — vehicle image + name, trip dates,
 * price breakdown, and the total + "Confirmer et payer" CTA. Both checkout
 * layout variants render this same card: the sidebar-flow variant places it
 * in a sticky right column (and shows a separate mobile fixed bar), the
 * vertical-stack variant stacks it below the form in the centered column.
 *
 * The CTA submits `#main-checkout-form` via the HTML `form` attribute, so it
 * works regardless of where this card is positioned on the page.
 */
interface CheckoutSummaryProps {
    vehicle: Vehicle;
    pickupAt: string;
    returnAt: string;
    priceBreakdown: PriceBreakdown;
    processing: boolean;
}

export default function CheckoutSummary({
    vehicle,
    pickupAt,
    returnAt,
    priceBreakdown,
    processing,
}: CheckoutSummaryProps) {
    const { t } = useTranslation();
    return (
        <div className="overflow-hidden rounded-container border border-border bg-surface shadow-raised">
            {/* Vehicle image + name */}
            <div className="border-b border-border p-6">
                <div className="h-40 w-full overflow-hidden rounded-interactive bg-background">
                    {vehicle.primary_image ? (
                        <img
                            src={vehicle.primary_image.url}
                            alt={vehicle.primary_image.alt_text ?? `${vehicle.make} ${vehicle.model}`}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <VehiclePlaceholderIcon className="h-full w-full p-8" />
                    )}
                </div>
                <p className="mt-3 text-xs font-medium uppercase tracking-wider text-textMuted">
                    {vehicle.category}
                </p>
                <h2 className="font-display text-lg font-semibold text-text">
                    {vehicle.make} {vehicle.model}
                </h2>
            </div>

            {/* Trip details */}
            <div className="space-y-4 border-b border-border p-6">
                <div className="flex items-start gap-3">
                    <div
                        className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                        style={tintSecondary}
                    >
                        <MapPin className="h-4 w-4 text-secondary" aria-hidden="true" />
                    </div>
                    <div>
                        <p className="text-sm font-medium text-text">
                            {vehicle.location?.name ?? t('Pickup')}
                        </p>
                        <p className="text-xs text-textMuted">{formatDateTime(pickupAt)}</p>
                    </div>
                </div>
                <div className="flex items-start gap-3">
                    <div
                        className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                        style={tintPrimary}
                    >
                        <Calendar className="h-4 w-4 text-primary" aria-hidden="true" />
                    </div>
                    <div>
                        <p className="text-sm font-medium text-text">{t('Return')}</p>
                        <p className="text-xs text-textMuted">{formatDateTime(returnAt)}</p>
                        {vehicle.location && (
                            <p className="text-sm text-textMuted">
                                {vehicle.location.name}, {vehicle.location.city}
                            </p>
                        )}
                    </div>
                </div>
                <div className="flex justify-between border-t border-dashed border-border pt-2">
                    <span className="text-sm text-textMuted">{t('Duration')}</span>
                    <span className="text-sm font-medium text-text">
                        {priceBreakdown.days} {priceBreakdown.days > 1 ? t('days') : t('day')}
                    </span>
                </div>
            </div>

            {/* Price breakdown */}
            <div className="space-y-2 bg-background p-6">
                <h3 className="mb-2 text-sm font-semibold text-text">{t('Price details')}</h3>
                <div className="flex justify-between text-sm">
                    <span className="text-textMuted">
                        {priceBreakdown.dailyRate.toFixed(0)} DH × {priceBreakdown.days} {priceBreakdown.days > 1 ? t('days') : t('day')}
                    </span>
                    <span className="text-text">
                        {(priceBreakdown.dailyRate * priceBreakdown.days).toFixed(0)} DH
                    </span>
                </div>
                {priceBreakdown.discountPercent > 0 && (
                    <div className="flex justify-between text-sm text-success">
                        <span>{t('Discount')} ({priceBreakdown.discountPercent}%)</span>
                        <span>
                            -
                            {(
                                (priceBreakdown.dailyRate *
                                    priceBreakdown.days *
                                    priceBreakdown.discountPercent) /
                                100
                            ).toFixed(0)}{' '}
                            DH
                        </span>
                    </div>
                )}
                {priceBreakdown.promoDiscount > 0 && (
                    <div className="flex justify-between text-sm text-success">
                        <span>{t('Promo code')}</span>
                        <span>-{priceBreakdown.promoDiscount.toFixed(0)} DH</span>
                    </div>
                )}
                <div className="flex justify-between text-sm">
                    <span className="text-textMuted">{t('Insurance included')}</span>
                    <span className="flex items-center gap-1 text-success">
                        <Check className="h-3.5 w-3.5" aria-hidden="true" />
                        {t('Included')}
                    </span>
                </div>
                <div className="flex justify-between text-sm">
                    <span className="text-textMuted">{t('Deposit')}</span>
                    <span className="text-text">{priceBreakdown.depositAmount.toFixed(0)} DH</span>
                </div>
            </div>

            {/* Total + CTA */}
            <div className="mt-auto border-t border-border bg-surface p-6">
                <div className="flex items-baseline justify-between">
                    <span className="text-sm text-textMuted">{t('Total')}</span>
                    <span className="font-display text-3xl font-bold text-text">
                        {priceBreakdown.totalPrice.toFixed(0)} DH
                    </span>
                </div>
                <p className="mt-1 text-xs text-textMuted">{t('Taxes included')}</p>
                <button
                    type="submit"
                    form="main-checkout-form"
                    disabled={processing}
                    className="mt-4 flex w-full items-center justify-center gap-2 rounded-interactive bg-primary py-4 font-semibold text-onPrimary transition-colors hover:bg-primaryHover disabled:opacity-50"
                >
                    {processing ? (
                        t('Processing...')
                    ) : (
                        <>
                            <Lock className="h-4 w-4" aria-hidden="true" />
                            {t('Confirm and pay')}
                        </>
                    )}
                </button>
                <p className="mt-2 text-center text-xs text-textMuted">
                    {t('No payment required now')}
                </p>
                <p className="mt-1 text-center text-xs text-textMuted">
                    {t('By confirming, you accept the terms and conditions')}
                </p>
            </div>
        </div>
    );
}
