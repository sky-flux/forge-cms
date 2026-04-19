import { Head, Link } from '@inertiajs/react';
import type { Category } from '@/types/category';
import type { Paginated, Post } from '@/types/post';
import { show as postShow } from '@/routes/posts';

interface Props {
    category: Category;
    posts: Paginated<Post>;
    canonical: string;
    ogImage: string | null;
}

export default function CategoriesShow({ category, posts, canonical, ogImage }: Props) {
    const seoTitle = `Category: ${category.name}`;
    const seoDescription = category.description ?? null;

    return (
        <>
            <Head title={seoTitle}>
                {seoDescription && <meta name="description" content={seoDescription} />}
                <meta property="og:title" content={seoTitle} />
                {seoDescription && <meta property="og:description" content={seoDescription} />}
                <meta property="og:url" content={canonical} />
                {ogImage && <meta property="og:image" content={ogImage} />}
                <link rel="canonical" href={canonical} />
            </Head>
            <main className="mx-auto max-w-4xl px-4 py-12">
                <header className="mb-10">
                    <p className="mb-2 text-sm uppercase tracking-wide text-muted-foreground">Category</p>
                    <h1 className="mb-3 text-3xl font-bold tracking-tight">{category.name}</h1>
                    {category.description && (
                        <p className="text-muted-foreground">{category.description}</p>
                    )}
                </header>

                {posts.data.length === 0 ? (
                    <p className="text-muted-foreground">No posts in this category yet.</p>
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
            </main>
        </>
    );
}
