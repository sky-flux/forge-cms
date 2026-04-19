// Matches App\Http\Resources\CommentResource output (with JsonResource::withoutWrapping()).
export interface Comment {
    id: number;
    uuid: string;
    parentId: number | null;
    bodyHtml: string;
    authorName: string | null;
    isGuest: boolean;
    createdAt: string;
    children: Comment[];
}
