import { Link, Head } from '@inertiajs/react';
import type { Paginated, Post } from '@/types/post';
import { show as postShow } from '@/routes/posts';

interface Props {
    posts: Paginated<Post>;
    canonical: string;
    ogImage: string | null;
}

export default function PostsIndex({ posts, canonical, ogImage }: Props) {
    const seoTitle = 'Posts';

    return (
        <>
            <Head title={seoTitle}>
                <meta property="og:title" content={seoTitle} />
                <meta property="og:url" content={canonical} />
                {ogImage && <meta property="og:image" content={ogImage} />}
                <link rel="canonical" href={canonical} />
            </Head>
            <main className="mx-auto max-w-4xl px-4 py-12">
                <h1 className="mb-8 text-3xl font-bold tracking-tight">Posts</h1>

                {posts.data.length === 0 ? (
                    <p className="text-muted-foreground">No published posts yet.</p>
                ) : (
                    <ul className="space-y-6">
                        {posts.data.map((post) => (
                            <li key={post.uuid} className="border-b pb-6 last:border-b-0">
                                <article>
                                    <h2 className="mb-2 text-xl font-semibold">
                                        <Link href={postShow(post.uuid).url} className="hover:underline">
                                            {post.title}
                                        </Link>
                                    </h2>
                                    {post.excerpt && (
                                        <p className="mb-3 text-muted-foreground">{post.excerpt}</p>
                                    )}
                                    <footer className="flex gap-3 text-sm text-muted-foreground">
                                        {post.author.name && <span>By {post.author.name}</span>}
                                        {post.publishedAt && (
                                            <time dateTime={post.publishedAt}>
                                                {new Date(post.publishedAt).toLocaleDateString()}
                                            </time>
                                        )}
                                    </footer>
                                </article>
                            </li>
                        ))}
                    </ul>
                )}

                {posts.meta.last_page > 1 && (
                    <nav className="mt-10 flex justify-center gap-2">
                        {posts.links.map((link, idx) =>
                            link.url ? (
                                <Link
                                    key={idx}
                                    href={link.url}
                                    className={
                                        link.active
                                            ? 'rounded bg-primary px-3 py-1 text-sm text-primary-foreground'
                                            : 'rounded border px-3 py-1 text-sm'
                                    }
                                    // Paginator labels are Laravel-generated (e.g. "&laquo; Previous", page numbers).
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={idx}
                                    className="rounded px-3 py-1 text-sm text-muted-foreground"
                                    // Paginator labels are Laravel-generated (e.g. "&laquo; Previous", page numbers).
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}
