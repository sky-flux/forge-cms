import { Head, usePage } from '@inertiajs/react';
import type { Page } from '@/types/page';
import type { Comment } from '@/types/comment';
import { store as pageCommentsStore } from '@/routes/pages/comments';
import { CommentThread } from '@/components/CommentThread';
import { CommentForm } from '@/components/CommentForm';

interface Props {
    page: Page & {
        comments: Comment[];
    };
}

export default function PagesShow({ page }: Props) {
    const inertiaPage = usePage<{ auth?: { user: { id: number } | null } }>();
    const authenticated = !!inertiaPage.props.auth?.user;

    return (
        <>
            <Head>
                <title>{page.seoTitle || page.title}</title>
                {page.seoDescription && (
                    <meta name="description" content={page.seoDescription} />
                )}
                <meta property="og:title" content={page.title} />
                {page.excerpt && <meta property="og:description" content={page.excerpt} />}
            </Head>
            <main className="mx-auto max-w-3xl px-4 py-12">
                <article>
                    <header className="mb-8">
                        <h1 className="mb-4 text-4xl font-bold tracking-tight">{page.title}</h1>
                        {page.author.name && (
                            <div className="text-sm text-muted-foreground">
                                By {page.author.name}
                            </div>
                        )}
                    </header>

                    {/* body_html is authored through Filament's TipTap RichEditor (admin-trusted input);
                        render as-is. If this surface ever accepts user-submitted HTML,
                        swap to DOMPurify.sanitize() before rendering. */}
                    <div
                        className="prose prose-neutral max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{ __html: page.bodyHtml }}
                    />
                </article>

                <section className="mt-16 border-t pt-12">
                    <h2 className="mb-6 text-2xl font-bold tracking-tight">Comments</h2>

                    {page.isCommentsEnabled ? (
                        <>
                            <CommentThread comments={page.comments} />

                            <div className="mt-12 border-t pt-6">
                                <h3 className="mb-4 text-lg font-semibold">Leave a comment</h3>
                                <CommentForm
                                    action={pageCommentsStore(page.slug).url}
                                    authenticated={authenticated}
                                />
                            </div>
                        </>
                    ) : (
                        <p className="text-muted-foreground">Comments are disabled on this page.</p>
                    )}
                </section>
            </main>
        </>
    );
}
