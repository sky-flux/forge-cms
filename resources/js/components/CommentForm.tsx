import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    action: string;
    authenticated: boolean;
}

export function CommentForm({ action, authenticated }: Props) {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        body: '',
        guest_name: '',
        guest_email: '',
    });

    function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(action, {
            onSuccess: () => reset(),
            preserveScroll: true,
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {!authenticated && (
                <>
                    <div>
                        <label htmlFor="guest_name" className="mb-1 block text-sm font-medium">
                            Name
                        </label>
                        <input
                            type="text"
                            id="guest_name"
                            value={data.guest_name}
                            onChange={(e) => setData('guest_name', e.target.value)}
                            required
                            maxLength={100}
                            className="w-full rounded border px-3 py-2"
                        />
                        {errors.guest_name && (
                            <p className="mt-1 text-sm text-destructive">{errors.guest_name}</p>
                        )}
                    </div>
                    <div>
                        <label htmlFor="guest_email" className="mb-1 block text-sm font-medium">
                            Email (not published)
                        </label>
                        <input
                            type="email"
                            id="guest_email"
                            value={data.guest_email}
                            onChange={(e) => setData('guest_email', e.target.value)}
                            required
                            maxLength={255}
                            className="w-full rounded border px-3 py-2"
                        />
                        {errors.guest_email && (
                            <p className="mt-1 text-sm text-destructive">{errors.guest_email}</p>
                        )}
                    </div>
                </>
            )}

            <div>
                <label htmlFor="body" className="mb-1 block text-sm font-medium">
                    Comment
                </label>
                <textarea
                    id="body"
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    required
                    minLength={2}
                    maxLength={5000}
                    rows={5}
                    className="w-full rounded border px-3 py-2"
                />
                {errors.body && <p className="mt-1 text-sm text-destructive">{errors.body}</p>}
            </div>

            <button
                type="submit"
                disabled={processing}
                className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
            >
                {processing ? 'Submitting...' : 'Submit comment'}
            </button>

            {wasSuccessful && (
                <p className="text-sm text-green-700">
                    Thanks — your comment is awaiting moderation.
                </p>
            )}
        </form>
    );
}
