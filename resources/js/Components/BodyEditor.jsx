import { Suspense, lazy } from 'react';

// The editor library is its own chunk: only the writer portal and the
// Library office screen pay for it, and only once the screen is open.
const RichTextEditor = lazy(() => import('./RichTextEditor'));

/**
 * B3: the article body, as a rich text editor with the plain textarea as
 * the fallback while the editor chunk loads (and, through the editor's own
 * `</>` toggle, for anyone who would rather paste HTML).
 */
export default function BodyEditor({ value, onChange, placeholder, labels, className = '', testId = 'body-editor' }) {
    return (
        <div className={className}>
            <Suspense
                fallback={
                    <textarea className="form-input w-full" rows="6" placeholder={placeholder} value={value || ''} onChange={(e) => onChange(e.target.value)} data-testid={`${testId}-loading`} />
                }
            >
                <RichTextEditor value={value} onChange={onChange} placeholder={placeholder} labels={labels} testId={testId} />
            </Suspense>
        </div>
    );
}
