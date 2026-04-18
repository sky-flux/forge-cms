import { Head, Link, usePage } from '@inertiajs/react';
import type { Post } from '@/types/post';
import type { Comment } from '@/types/comment';
import { index as postIndex } from '@/routes/posts';
import { store as postCommentsStore } from '@/routes/posts/comments';
import { CommentThread } from '@/components/CommentThread';
import { CommentForm } from '@/components/CommentForm';

interface Props {
    post: Post & {
        comments: Comment[];
    };
}

export default function PostsShow({ post }: Props) {
    const page = usePage<{ auth?: { user: { id: number } | null } }>();
    const authenticated = !!page.props.auth?.user;

    return (
        <>
            <Head>
                <title>{post.seoTitle || post.title}</title>
                {post.seoDescription && (
                    <meta name="description" content={post.seoDescription} />
                )}
                <meta property="og:title" content={post.title} />
                {post.excerpt && <meta property="og:description" content={post.excerpt} />}
            </Head>
            <main className="mx-auto max-w-3xl px-4 py-12">
                <nav className="mb-8">
                    <Link href={postIndex().url} className="text-sm text-muted-foreground hover:underline">
                        &larr; All posts
                    </Link>
                </nav>

                <article>
                    <header className="mb-8">
                        <h1 className="mb-4 text-4xl font-bold tracking-tight">{post.title}</h1>
                        <div className="flex gap-3 text-sm text-muted-foreground">
                            {post.author.name && <span>By {post.author.name}</span>}
                            {post.publishedAt && (
                                <time dateTime={post.publishedAt}>
                                    {new Date(post.publishedAt).toLocaleDateString()}
                                </time>
                            )}
                            <span>· {post.viewCount} views</span>
                        </div>
                    </header>

                    {post.bodyHtml && (
                        // body_html is authored through Filament's TipTap RichEditor (admin-trusted input);
                        // render as-is. If this surface ever accepts user-submitted HTML,
                        // swap to DOMPurify.sanitize() before rendering.
                        <div
                            className="prose prose-neutral max-w-none dark:prose-invert"
                            dangerouslySetInnerHTML={{ __html: post.bodyHtml }}
                        />
                    )}
                </article>

                <section className="mt-16 border-t pt-12">
                    <h2 className="mb-6 text-2xl font-bold tracking-tight">Comments</h2>

                    {post.isCommentsEnabled ? (
                        <>
                            <CommentThread comments={post.comments} />

                            <div className="mt-12 border-t pt-6">
                                <h3 className="mb-4 text-lg font-semibold">Leave a comment</h3>
                                <CommentForm
                                    action={postCommentsStore(post.uuid).url}
                                    authenticated={authenticated}
                                />
                            </div>
                        </>
                    ) : (
                        <p className="text-muted-foreground">Comments are disabled on this post.</p>
                    )}
                </section>
            </main>
        </>
    );
}
