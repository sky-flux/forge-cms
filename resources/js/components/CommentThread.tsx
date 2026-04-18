import type { Comment } from '@/types/comment';

interface Props {
    comments: Comment[];
    depth?: number;
}

const MAX_DEPTH = 3;

export function CommentThread({ comments, depth = 0 }: Props) {
    if (comments.length === 0) {
        return <p className="text-muted-foreground">No comments yet.</p>;
    }

    return (
        <ul className={depth === 0 ? 'space-y-6' : 'mt-4 space-y-4 border-l pl-4'}>
            {comments.map((comment) => (
                <li key={comment.uuid}>
                    <article>
                        <header className="mb-2 text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">
                                {comment.authorName ?? '(anonymous)'}
                            </span>
                            {comment.isGuest && <span className="ml-2 text-xs">(guest)</span>}
                            <time className="ml-2" dateTime={comment.createdAt}>
                                {new Date(comment.createdAt).toLocaleDateString()}
                            </time>
                        </header>
                        {/*
                            bodyHtml is produced server-side by `nl2br(e($body))` in the
                            comment controller (Task 48) — user input is HTML-escaped before
                            being stored. Rendering via dangerouslySetInnerHTML is safe here
                            because the string is sanitised at write time, NOT because the
                            author is trusted.
                        */}
                        <div
                            className="prose prose-sm max-w-none dark:prose-invert"
                            dangerouslySetInnerHTML={{ __html: comment.bodyHtml }}
                        />
                    </article>
                    {comment.children.length > 0 && depth + 1 < MAX_DEPTH && (
                        <CommentThread comments={comment.children} depth={depth + 1} />
                    )}
                </li>
            ))}
        </ul>
    );
}
