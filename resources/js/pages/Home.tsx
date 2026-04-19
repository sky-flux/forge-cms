import { Head, Link } from '@inertiajs/react';
import type { Page } from '@/types/page';
import type { Post } from '@/types/post';
import { show as postShow, index as postsIndex } from '@/routes/posts';

interface Props {
    homepage: Page | null;
    latestPosts: Post[];
}

export default function HomePage({ homepage, latestPosts }: Props) {
    return (
        <>
            <Head title={homepage?.seoTitle ?? homepage?.title ?? 'Home'}>
                {homepage?.seoDescription && (
                    <meta name="description" content={homepage.seoDescription} />
                )}
            </Head>
            <main className="mx-auto max-w-4xl px-4 py-12">
                {homepage ? (
                    <article className="mb-16 border-b pb-12">
                        <h1 className="mb-4 text-4xl font-bold tracking-tight">{homepage.title}</h1>
                        {/* body is admin-trusted Filament RichEditor HTML — safe to render raw (same convention as Pages/Show.tsx and Posts/Show.tsx) */}
                        <div
                            className="prose prose-neutral max-w-none dark:prose-invert"
                            dangerouslySetInnerHTML={{ __html: homepage.bodyHtml }}
                        />
                    </article>
                ) : (
                    <header className="mb-12">
                        <h1 className="text-4xl font-bold tracking-tight">ForgeCMS</h1>
                    </header>
                )}

                <section>
                    <div className="mb-6 flex items-baseline justify-between">
                        <h2 className="text-2xl font-bold tracking-tight">Latest posts</h2>
                        <Link href={postsIndex().url} className="text-sm text-muted-foreground hover:underline">
                            View all &rarr;
                        </Link>
                    </div>

                    {latestPosts.length === 0 ? (
                        <p className="text-muted-foreground">No posts yet.</p>
                    ) : (
                        <ul className="space-y-6">
                            {latestPosts.map((post) => (
                                <li key={post.uuid} className="border-b pb-6 last:border-b-0">
                                    <article>
                                        <h3 className="mb-2 text-xl font-semibold">
                                            <Link href={postShow(post.uuid).url} className="hover:underline">
                                                {post.title}
                                            </Link>
                                        </h3>
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
                </section>
            </main>
        </>
    );
}
