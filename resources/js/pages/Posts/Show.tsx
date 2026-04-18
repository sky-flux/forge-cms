import { Head, Link } from '@inertiajs/react';
import type { Post } from '@/types/post';
import { index as postIndex } from '@/routes/posts';

interface Props {
    post: Post;
}

export default function PostsShow({ post }: Props) {
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
            </main>
        </>
    );
}
