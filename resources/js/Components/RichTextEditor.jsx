import { useEffect, useRef, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Node, mergeAttributes } from '@tiptap/core';
import { TextSelection } from '@tiptap/pm/state';

/**
 * B3 (LIBRARY_PLAN §36): a rich text editor for the article body.
 *
 * The body is stored as HTML with `<!-- pagebreak -->` between reader
 * pages. An editor cannot hold an HTML comment, so the marker travels as a
 * page-break node (`<hr data-pagebreak>`) inside the editor and is turned
 * back into the comment on the way out; the server accepts both. The
 * sanitiser's CMS profile allows everything this toolbar can produce.
 *
 * Loaded lazily by `BodyEditor` so the editor library is a chunk of its own,
 * fetched only on the two screens that write articles.
 */
export const PAGE_BREAK = '<!-- pagebreak -->';

export const toEditorHtml = (html) => (html || '').split(PAGE_BREAK).join('<hr data-pagebreak="true">');

export const fromEditorHtml = (html) => (html || '').replace(/<hr[^>]*data-pagebreak[^>]*>/gi, PAGE_BREAK);

const PageBreak = Node.create({
    name: 'pageBreak',
    group: 'block',
    atom: true,
    selectable: true,
    parseHTML: () => [{ tag: 'hr[data-pagebreak]' }],
    renderHTML: ({ HTMLAttributes }) => ['hr', mergeAttributes(HTMLAttributes, { 'data-pagebreak': 'true', class: 'akuru-pagebreak' })],
    addCommands() {
        return {
            // Insert the break and put the cursor in the paragraph after it —
            // a freshly inserted atom is selected, and typing into a selected
            // node replaces it, which is not what "page break, keep writing"
            // means. (What the horizontal rule extension does, in short.)
            setPageBreak: () => ({ chain }) => chain()
                .insertContent({ type: this.name })
                .command(({ tr, dispatch }) => {
                    if (dispatch) {
                        const { $to } = tr.selection;
                        const after = $to.nodeAfter;
                        if (after && after.isTextblock) {
                            tr.setSelection(TextSelection.create(tr.doc, $to.pos + 1));
                        } else {
                            const paragraph = $to.parent.type.contentMatch.defaultType?.create();
                            if (paragraph) {
                                const at = $to.end();
                                tr.insert(at, paragraph);
                                tr.setSelection(TextSelection.create(tr.doc, at + 1));
                            }
                        }
                        tr.scrollIntoView();
                    }
                    return true;
                })
                .run(),
        };
    },
});

function ToolbarButton({ active, onClick, title, children, testId }) {
    return (
        <button
            type="button"
            title={title}
            aria-label={title}
            aria-pressed={Boolean(active)}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className={`rounded px-2 py-1 text-sm ${active ? 'bg-[#7C2D37] text-white' : 'bg-[#F3EBE0] text-[#7C2D37] hover:bg-[#e9dcc9]'}`}
            data-testid={testId}
        >
            {children}
        </button>
    );
}

export default function RichTextEditor({ value, onChange, placeholder, labels = {}, testId = 'body-editor' }) {
    const [source, setSource] = useState(false);
    const t = (key, fallback) => labels[key] || fallback;

    // What the form holds, and what the editor last handed it. A difference
    // between the two is a change from outside the editor (a draft opened
    // for editing, a form cleared after a save, the source view edited) and
    // is pushed in; typing never fights itself. Nothing is read back from
    // the editor here, because it initialises after its first render.
    const latest = useRef(value || '');
    latest.current = value || '';
    const lastEmitted = useRef(value || '');

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                link: { openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto'] },
                horizontalRule: false,
            }),
            PageBreak,
        ],
        content: toEditorHtml(value),
        editorProps: {
            attributes: {
                class: 'prose max-w-none min-h-[12rem] rounded-b border border-t-0 p-3 focus:outline-none',
                dir: 'auto',
                'data-placeholder': placeholder || '',
            },
        },
        onCreate: ({ editor: created }) => {
            if (latest.current !== lastEmitted.current) {
                lastEmitted.current = latest.current;
                created.commands.setContent(toEditorHtml(latest.current), { emitUpdate: false });
            }
        },
        onUpdate: ({ editor: current }) => {
            const html = fromEditorHtml(current.getHTML());
            lastEmitted.current = html === '<p></p>' ? '' : html;
            onChange(lastEmitted.current);
        },
    });

    useEffect(() => {
        if (!editor || !editor.isInitialized) return;
        const outside = value || '';
        if (outside !== lastEmitted.current) {
            lastEmitted.current = outside;
            editor.commands.setContent(toEditorHtml(outside), { emitUpdate: false });
        }
    }, [editor, value]);

    const setLink = () => {
        if (!editor) return;
        const previous = editor.getAttributes('link').href || '';
        const url = window.prompt(t('link_prompt', 'Address (https://…)'), previous);
        if (url === null) return;
        if (url.trim() === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
    };

    return (
        <div data-testid={testId}>
            <div className="flex flex-wrap gap-1 rounded-t border bg-white p-1" role="toolbar" aria-label={t('toolbar', 'Formatting')}>
                <ToolbarButton title={t('bold', 'Bold')} active={editor?.isActive('bold')} onClick={() => editor?.chain().focus().toggleBold().run()}><strong>B</strong></ToolbarButton>
                <ToolbarButton title={t('italic', 'Italic')} active={editor?.isActive('italic')} onClick={() => editor?.chain().focus().toggleItalic().run()}><em>I</em></ToolbarButton>
                <ToolbarButton title={t('heading', 'Heading')} active={editor?.isActive('heading', { level: 2 })} onClick={() => editor?.chain().focus().toggleHeading({ level: 2 }).run()}>H2</ToolbarButton>
                <ToolbarButton title={t('subheading', 'Subheading')} active={editor?.isActive('heading', { level: 3 })} onClick={() => editor?.chain().focus().toggleHeading({ level: 3 }).run()}>H3</ToolbarButton>
                <ToolbarButton title={t('bullets', 'Bulleted list')} active={editor?.isActive('bulletList')} onClick={() => editor?.chain().focus().toggleBulletList().run()}>•</ToolbarButton>
                <ToolbarButton title={t('numbers', 'Numbered list')} active={editor?.isActive('orderedList')} onClick={() => editor?.chain().focus().toggleOrderedList().run()}>1.</ToolbarButton>
                <ToolbarButton title={t('quote', 'Quote')} active={editor?.isActive('blockquote')} onClick={() => editor?.chain().focus().toggleBlockquote().run()}>“ ”</ToolbarButton>
                <ToolbarButton title={t('link', 'Link')} active={editor?.isActive('link')} onClick={setLink}>🔗</ToolbarButton>
                <ToolbarButton title={t('page_break', 'Page break')} onClick={() => editor?.chain().focus().setPageBreak().run()} testId={`${testId}-pagebreak`}>⤓ {t('page_break', 'Page break')}</ToolbarButton>
                <span className="grow" />
                <ToolbarButton title={t('source', 'HTML source')} active={source} onClick={() => setSource(!source)} testId={`${testId}-source`}>{'</>'}</ToolbarButton>
            </div>
            {source ? (
                <textarea
                    className="form-input min-h-[12rem] w-full rounded-t-none font-mono text-xs"
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    dir="ltr"
                    data-testid={`${testId}-html`}
                />
            ) : (
                <EditorContent editor={editor} />
            )}
        </div>
    );
}
