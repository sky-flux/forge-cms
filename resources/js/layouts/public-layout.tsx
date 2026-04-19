import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { home } from '@/routes';
import { index as postsIndex } from '@/routes/posts';

const appName = import.meta.env.VITE_APP_NAME || 'forge-cms';

export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b bg-background">
                <nav
                    aria-label="Primary"
                    className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4"
                >
                    <Link href={home().url} className="text-lg font-semibold tracking-tight">
                        {appName}
                    </Link>
                    <div className="flex gap-4 text-sm text-muted-foreground">
                        <Link href={postsIndex().url} className="hover:text-foreground">
                            Posts
                        </Link>
                    </div>
                </nav>
            </header>
            <div className="flex-1">{children}</div>
            <footer className="border-t py-6 text-center text-sm text-muted-foreground">
                &copy; {new Date().getFullYear()} {appName}
            </footer>
        </div>
    );
}
