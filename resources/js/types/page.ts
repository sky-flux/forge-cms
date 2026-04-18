// Matches App\Http\Resources\PageResource output (with JsonResource::withoutWrapping()).
export interface PageAuthor {
    name?: string;
}

export interface Page {
    uuid: string;
    title: string;
    slug: string;
    excerpt: string | null;
    bodyHtml: string;
    status: 'draft' | 'published' | 'scheduled';
    publishedAt: string | null;
    sortOrder: number;
    isHomepage: boolean;
    isCommentsEnabled: boolean;
    author: PageAuthor;
    seoTitle: string | null;
    seoDescription: string | null;
}
