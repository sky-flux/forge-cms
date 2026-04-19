import { Head, Link, useForm } from '@inertiajs/react';
import type { Post } from '@/types/post';
import { show as postShow } from '@/routes/posts';
import { show as pageShow } from '@/routes/pages';

interface PageSummary {
    uuid: string;
    title: string;
    slug: string;
    excerpt?: string | null;
}

interface Props {
    query: string | null;
    posts: { data: Post[] };
    pages: { data: PageSummary[] };
}

export default function Search({ query, posts, pages }: Props) {
    const form = useForm({ q: query ?? '' });

    return (
        <>
            <Head title={query ? `Search: ${query}` : 'Search'} />
            <main className="mx-auto max-w-3xl px-4 py-12">
                <h1 className="mb-6 text-3xl font-bold tracking-tight">Search</h1>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.get('/search');
                    }}
                    className="flex gap-2"
                >
                    <input
                        type="text"
                        name="q"
                        value={form.data.q}
                        onChange={(e) => form.setData('q', e.target.value)}
                        placeholder="Search posts and pages…"
                        className="flex-1 rounded border px-3 py-2"
                    />
                    <button
                        type="submit"
                        className="rounded bg-primary px-4 py-2 text-primary-foreground"
                    >
                        Go
                    </button>
                </form>

                {query === null ? (
                    <p className="mt-10 text-muted-foreground">Enter a query above.</p>
                ) : (
                    <div className="mt-10 space-y-10">
                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                Posts ({posts.data.length})
                            </h2>
                            {posts.data.length === 0 ? (
                                <p className="text-muted-foreground">No matching posts.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {posts.data.map((post) => (
                                        <li key={post.uuid}>
                                            <Link
                                                href={postShow(post.uuid).url}
                                                className="font-medium hover:underline"
                                            >
                                                {post.title}
                                            </Link>
                                            {post.excerpt && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {post.excerpt}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                Pages ({pages.data.length})
                            </h2>
                            {pages.data.length === 0 ? (
                                <p className="text-muted-foreground">No matching pages.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {pages.data.map((page) => (
                                        <li key={page.uuid}>
                                            <Link
                                                href={pageShow(page.slug).url}
                                                className="font-medium hover:underline"
                                            >
                                                {page.title}
                                            </Link>
                                            {page.excerpt && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {page.excerpt}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}
            </main>
        </>
    );
}
