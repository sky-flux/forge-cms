import { Head, Link } from '@inertiajs/react';
import { index as postsIndex } from '@/routes/posts';

export default function NotFound() {
    return (
        <>
            <Head title="404 — Not Found" />
            <main className="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center px-4 py-12 text-center">
                <h1 className="mb-4 text-6xl font-bold tracking-tight">404</h1>
                <p className="mb-8 text-lg text-muted-foreground">
                    The page you're looking for doesn't exist or has been moved.
                </p>
                <div className="flex gap-4 text-sm">
                    <Link href="/" className="rounded border px-4 py-2 hover:bg-muted">
                        Home
                    </Link>
                    <Link href={postsIndex().url} className="rounded border px-4 py-2 hover:bg-muted">
                        All posts
                    </Link>
                </div>
            </main>
        </>
    );
}
