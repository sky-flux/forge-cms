import { useState } from 'react';
import type { Comment } from '@/types/comment';
import { CommentForm } from '@/components/CommentForm';

interface Props {
    comments: Comment[];
    action: string;
    authenticated: boolean;
    depth?: number;
}

const MAX_DEPTH = 3;

export function CommentThread({ comments, action, authenticated, depth = 0 }: Props) {
    if (comments.length === 0 && depth === 0) {
        return <p className="text-muted-foreground">No comments yet.</p>;
    }

    return (
        <ul className={depth === 0 ? 'space-y-6' : 'mt-4 space-y-4 border-l pl-4'}>
            {comments.map((comment) => (
                <li key={comment.uuid}>
                    <CommentNode
                        comment={comment}
                        action={action}
                        authenticated={authenticated}
                        depth={depth}
                    />
                </li>
            ))}
        </ul>
    );
}

interface NodeProps {
    comment: Comment;
    action: string;
    authenticated: boolean;
    depth: number;
}

function CommentNode({ comment, action, authenticated, depth }: NodeProps) {
    const [replying, setReplying] = useState(false);
    const canReply = depth + 1 < MAX_DEPTH;

    return (
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
                bodyHtml is produced server-side by the CommentObserver via
                `nl2br(e($body))` on every save — user input is HTML-escaped
                before being stored. Rendering via dangerouslySetInnerHTML is
                safe here because the string is sanitised at write time, NOT
                because the author is trusted. Matches the idiom used in
                resources/js/pages/Posts/Show.tsx and Pages/Show.tsx for
                body_html rendering.
            */}
            <div
                className="prose prose-sm max-w-none dark:prose-invert"
                dangerouslySetInnerHTML={{ __html: comment.bodyHtml }}
            />

            {canReply && (
                <div className="mt-2">
                    <button
                        type="button"
                        onClick={() => setReplying((open) => !open)}
                        className="text-sm text-primary underline-offset-2 hover:underline"
                    >
                        {replying ? 'Cancel' : 'Reply'}
                    </button>
                    {replying && (
                        <div className="mt-3">
                            <CommentForm
                                action={action}
                                authenticated={authenticated}
                                parentId={comment.id}
                                onSubmitted={() => setReplying(false)}
                            />
                        </div>
                    )}
                </div>
            )}

            {comment.children.length > 0 && depth + 1 < MAX_DEPTH && (
                <CommentThread
                    comments={comment.children}
                    action={action}
                    authenticated={authenticated}
                    depth={depth + 1}
                />
            )}
        </article>
    );
}
