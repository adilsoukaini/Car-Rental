import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { ReviewsData } from '@/Widgets/VehicleReviewsCardList';

function Stars({ rating }: { rating: number }) {
    return (
        <span className="text-primary" aria-label={`${rating} out of 5 stars`}>
            {'★'.repeat(rating)}
            {'☆'.repeat(5 - rating)}
        </span>
    );
}

/**
 * Compact review display (the `compact` `reviewDisplay` layout variant) —
 * each review is a single inline row: stars + one-line body. No author
 * names, no titles, no dates — deliberately minimal. The header (average
 * rating) and the "leave a review" form are shared with the card-list
 * variant so switching the variant never drops the ability to post a
 * review.
 */
export default function VehicleReviewsCompact({ vehicleId, reviewsData }: { vehicleId: number; reviewsData: ReviewsData }) {
    const user = usePage<PageProps>().props.auth.user;
    const [submitted, setSubmitted] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<{
        rating: number;
        title: string;
        body: string;
    }>({
        rating: 5,
        title: '',
        body: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('reviews.store', vehicleId), {
            onSuccess: () => {
                reset();
                setSubmitted(true);
            },
        });
    };

    return (
        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-display text-lg font-medium text-text">Reviews</h2>
                {reviewsData.reviewCount > 0 && (
                    <div className="text-sm text-textMuted">
                        <Stars rating={Math.round(reviewsData.averageRating)} />{' '}
                        {reviewsData.averageRating.toFixed(1)} ({reviewsData.reviewCount})
                    </div>
                )}
            </div>

            {reviewsData.reviews.length === 0 ? (
                <p className="text-sm text-textMuted">No reviews yet.</p>
            ) : (
                <ul className="mb-6 space-y-2">
                    {reviewsData.reviews.map((review) => (
                        <li
                            key={review.id}
                            className="flex items-center gap-2 rounded-interactive bg-background px-3 py-2"
                        >
                            <Stars rating={review.rating} />
                            <span className="truncate text-sm text-text">{review.body}</span>
                        </li>
                    ))}
                </ul>
            )}

            {user && !submitted && (
                <form onSubmit={submit} className="space-y-3 border-t border-border pt-4">
                    <h3 className="text-sm font-medium text-text">Leave a review</h3>

                    <div>
                        <label htmlFor="review-rating" className="mb-1 block text-sm text-textMuted">Rating</label>
                        <select
                            id="review-rating"
                            value={data.rating}
                            onChange={(e) => setData('rating', Number(e.target.value))}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                        >
                            {[5, 4, 3, 2, 1].map((n) => (
                                <option key={n} value={n}>
                                    {n} star{n === 1 ? '' : 's'}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="review-title" className="mb-1 block text-sm text-textMuted">Title (optional)</label>
                        <input
                            id="review-title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            aria-invalid={errors.title ? 'true' : 'false'}
                            aria-describedby={errors.title ? 'review-title-error' : undefined}
                        />
                        {errors.title && <p id="review-title-error" className="mt-1 text-sm text-danger">{errors.title}</p>}
                    </div>

                    <div>
                        <label htmlFor="review-body" className="mb-1 block text-sm text-textMuted">Review</label>
                        <textarea
                            id="review-body"
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            rows={3}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                            aria-required="true"
                            aria-invalid={errors.body ? 'true' : 'false'}
                            aria-describedby={errors.body ? 'review-body-error' : undefined}
                        />
                        {errors.body && <p id="review-body-error" className="mt-1 text-sm text-danger">{errors.body}</p>}
                    </div>

                    {(errors as Record<string, string>).review && (
                        <p className="text-sm text-danger">{(errors as Record<string, string>).review}</p>
                    )}

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-interactive bg-primary px-4 py-2 font-body text-onPrimary shadow-resting hover:bg-primaryHover disabled:opacity-50"
                    >
                        Submit review
                    </button>
                </form>
            )}

            {submitted && (
                <p className="border-t border-border pt-4 text-sm text-textMuted">
                    Thanks — your review has been submitted and is pending approval.
                </p>
            )}
        </div>
    );
}
