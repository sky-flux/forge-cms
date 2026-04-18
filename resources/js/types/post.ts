// DTO shape matches App\Http\Resources\PostResource output.
// JsonResource::withoutWrapping() (AppServiceProvider) strips the top-level
// `{data: ...}` on single-resource responses, so `props.post` is flat.
// PaginatedCollection still ships as `{data: [...], links, meta}` per Laravel's paginator contract.

export interface PostAuthor {
    name?: string;
}

export interface Post {
    uuid: string;
    title: string;
    slug: string;
    excerpt: string | null;
    bodyHtml?: string; // present only on posts.show route
    status: 'draft' | 'published' | 'scheduled';
    publishedAt: string | null;
    viewCount: number;
    author: PostAuthor;
    seoTitle: string | null;
    seoDescription: string | null;
}

export interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
