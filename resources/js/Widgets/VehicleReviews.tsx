import { PageProps, Review } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface ReviewsData {
    vehicleId: number;
    averageRating: number;
    reviewCount: number;
    reviews: Review[];
}

function Stars({ rating }: { rating: number }) {
    return (
        <span className="text-primary" aria-label={`${rating} out of 5 stars`}>
            {'★'.repeat(rating)}
            {'☆'.repeat(5 - rating)}
        </span>
    );
}

export default function VehicleReviews({ vehicleId, reviewsData }: { vehicleId: number; reviewsData: ReviewsData }) {
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
                <ul className="mb-6 space-y-4 divide-y divide-border">
                    {reviewsData.reviews.map((review) => (
                        <li key={review.id} className="pt-4 first:pt-0">
                            <div className="mb-1 flex items-center justify-between">
                                <span className="text-sm font-medium text-text">{review.authorName}</span>
                                <Stars rating={review.rating} />
                            </div>
                            {review.title && <p className="mb-1 text-sm font-medium text-text">{review.title}</p>}
                            <p className="text-sm text-textMuted">{review.body}</p>
                            <div className="mt-1 flex items-center gap-2 text-xs text-textMuted">
                                <span>{review.createdAt}</span>
                                {review.isVerifiedRental && (
                                    <span className="rounded-pill bg-primary/10 px-2 py-0.5 text-primary">
                                        Verified rental
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {user && !submitted && (
                <form onSubmit={submit} className="space-y-3 border-t border-border pt-4">
                    <h3 className="text-sm font-medium text-text">Leave a review</h3>

                    <div>
                        <label className="mb-1 block text-sm text-textMuted">Rating</label>
                        <select
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
                        <label className="mb-1 block text-sm text-textMuted">Title (optional)</label>
                        <input
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                        />
                        {errors.title && <p className="mt-1 text-sm text-danger">{errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-textMuted">Review</label>
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            rows={3}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                        />
                        {errors.body && <p className="mt-1 text-sm text-danger">{errors.body}</p>}
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
