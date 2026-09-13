import { useEffect, useRef, useState } from 'react';

/**
 * SPEC §51.6 "Writing" lists two ways to hand in handwriting:
 *
 *   > Handwriting canvas · Handwriting image upload
 *
 * and §51.23's acceptance criteria require "Handwriting/canvas submissions are
 * saved for teacher review".
 *
 * The **upload** half has worked since §36 gave teacher-marked activities a
 * `file` submission kind that accepts images. The **canvas** did not exist at
 * all — and it is the half that matters for a child practising Arabic
 * letterforms on a tablet, who has no image to upload because the writing has
 * not happened anywhere else yet.
 *
 * **It posts through §36's existing attempt-upload endpoint.** The canvas
 * exports a PNG and hands it to the same route every other attachment takes,
 * so the server keeps one upload path, one MIME allowlist and one
 * server-owned attachment list (rule 11). The only thing new here is where
 * the pixels come from.
 *
 * §51.6 is emphatic that Arabic skills "must be implemented using the general
 * platform activity system. Do not create a separate Arabic exercise engine."
 * This is a drawing surface, not an engine: nothing here knows what an Arabic
 * letter is.
 */
export default function HandwritingCanvas({ onExport, disabled = false, rtl = true }) {
    const canvasRef = useRef(null);
    const drawingRef = useRef(false);
    const dirtyRef = useRef(false);
    const [dirty, setDirty] = useState(false);

    // A canvas element's backing store is separate from its CSS size, so on a
    // high-DPI tablet — the device this is for — an unscaled canvas draws
    // blurry strokes at the wrong coordinates.
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }
        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.round(rect.width * ratio);
        canvas.height = Math.round(rect.height * ratio);

        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        // White rather than transparent: a PNG with an alpha background turns
        // black in some viewers, and a teacher opening the submission would
        // see a black square with black ink in it.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, rect.width, rect.height);
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.lineWidth = 3;
        context.strokeStyle = '#1f2937';
    }, []);

    const positionFrom = (event) => {
        const rect = canvasRef.current.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    const start = (event) => {
        if (disabled) {
            return;
        }
        // Pointer events cover mouse, stylus and finger in one path, so a
        // tablet needs no second code path.
        event.preventDefault();
        canvasRef.current.setPointerCapture?.(event.pointerId);
        const { x, y } = positionFrom(event);
        const context = canvasRef.current.getContext('2d');
        context.beginPath();
        context.moveTo(x, y);
        drawingRef.current = true;
    };

    const move = (event) => {
        if (!drawingRef.current || disabled) {
            return;
        }
        event.preventDefault();
        const { x, y } = positionFrom(event);
        const context = canvasRef.current.getContext('2d');
        context.lineTo(x, y);
        context.stroke();
        if (!dirtyRef.current) {
            dirtyRef.current = true;
            setDirty(true);
        }
    };

    const end = (event) => {
        if (!drawingRef.current) {
            return;
        }
        canvasRef.current.releasePointerCapture?.(event.pointerId);
        drawingRef.current = false;
    };

    const clear = () => {
        const canvas = canvasRef.current;
        const context = canvas.getContext('2d');
        const ratio = window.devicePixelRatio || 1;
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width / ratio, canvas.height / ratio);
        dirtyRef.current = false;
        setDirty(false);
    };

    const save = () => {
        // An empty canvas would upload a blank white PNG, which a teacher
        // cannot tell from a student who drew nothing on purpose.
        if (!dirtyRef.current) {
            return;
        }
        canvasRef.current.toBlob((blob) => {
            if (blob) {
                onExport(new File([blob], 'handwriting.png', { type: 'image/png' }));
            }
        }, 'image/png');
    };

    return (
        <div className="mb-4">
            <canvas
                ref={canvasRef}
                // `touch-none` stops the browser scrolling the page instead of
                // drawing when a finger moves across the canvas.
                className="h-56 w-full touch-none rounded-lg border bg-white"
                style={{ direction: rtl ? 'rtl' : 'ltr' }}
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerLeave={end}
                onPointerCancel={end}
                aria-label="Handwriting canvas"
            />
            <div className="mt-2 flex flex-wrap gap-3">
                <button type="button" className="btn-secondary" onClick={clear} disabled={disabled || !dirty}>
                    Clear
                </button>
                <button type="button" className="btn-primary" onClick={save} disabled={disabled || !dirty}>
                    Save handwriting
                </button>
                {!dirty && <span className="self-center text-xs text-gray-500">Write in the box above.</span>}
            </div>
        </div>
    );
}
