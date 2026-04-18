import { Head, Link } from '@inertiajs/react';
import type { Category } from '@/types/category';
import type { Paginated, Post } from '@/types/post';
import { show as postShow } from '@/routes/posts';

interface Props {
    category: Category;
    posts: Paginated<Post>;
}

export default function CategoriesShow({ category, posts }: Props) {
    return (
        <>
            <Head title={`Category: ${category.name}`} />
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
