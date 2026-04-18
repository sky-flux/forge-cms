import { Head } from '@inertiajs/react';
import type { Page } from '@/types/page';

interface Props {
    page: Page;
}

export default function PagesShow({ page }: Props) {
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
            </main>
        </>
    );
}
