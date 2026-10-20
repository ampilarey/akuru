import { useId, useRef } from 'react';
import { PT_TO_MM, clampElement, fillText, resolveColour, round, snap } from './design';

/**
 * NAME_TAGS_PLAN §7.1 (NT2): one tag drawn as SVG in millimetres — the
 * same model the server prints (`documents/print-sheet.blade.php`), so the
 * browser's picture and the printed sheet agree. Still (a thumbnail, a
 * preview) or interactive (the designer: tap selects, drag moves, the
 * handles resize, snapping to the tag's edges and centre). Pointer events,
 * no drawing library.
 *
 *   design    the design (design.js shape)
 *   fonts     {id: {family}} the font table
 *   images    {mediaId: url} the picture library
 *   row       {field: value} what the fields show (a sample, or a customer's row)
 *   scale     px per mm
 *   selected  the selected element's id (interactive only)
 *   onSelect(id | null), onChange(elements, commit)   interactive only
 *   shape     'rect' or 'circle' — the layout's (PP4: a round sticker is clipped to its circle, as it prints)
 */
const HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
const CURSORS = { nw: 'nwse-resize', se: 'nwse-resize', ne: 'nesw-resize', sw: 'nesw-resize', n: 'ns-resize', s: 'ns-resize', e: 'ew-resize', w: 'ew-resize' };

export default function TagCanvas({ design, fonts = {}, images = {}, row = {}, scale = 4, selected = null, onSelect, onChange, interactive = false, testId = 'tag-canvas', shape = 'rect' }) {
    const clipId = `tag-clip-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;
    const round_ = shape === 'circle';
    const svgRef = useRef(null);
    const drag = useRef(null);
    const { w, h } = design.tag;
    const background = resolveColour(design.background?.color, design) || '#FFFFFF';
    const hs = 10 / scale; // a handle is 10 px however the tag is zoomed

    const toMm = (e) => {
        const rect = svgRef.current.getBoundingClientRect();
        return { x: ((e.clientX - rect.left) / rect.width) * w, y: ((e.clientY - rect.top) / rect.height) * h };
    };

    const start = (e, el, mode, handle = null) => {
        if (!interactive) return;
        e.stopPropagation();
        onSelect?.(el.id);
        if (el.locked) return;
        const at = toMm(e);
        drag.current = { id: el.id, mode, handle, at, orig: { ...el }, moved: false };
        e.currentTarget.setPointerCapture?.(e.pointerId);
    };

    const move = (e) => {
        const d = drag.current;
        if (!d) return;
        const at = toMm(e);
        const dx = at.x - d.at.x;
        const dy = at.y - d.at.y;
        if (!d.moved && Math.abs(dx) < 0.3 && Math.abs(dy) < 0.3) return;
        d.moved = true;
        const o = d.orig;
        let next;
        if (d.mode === 'move') {
            const others = design.elements.filter((x) => x.id !== d.id);
            const xs = [0, w - o.w, (w - o.w) / 2, ...others.flatMap((x) => [x.x, x.x + x.w - o.w, x.x + x.w, x.x - o.w])];
            const ys = [0, h - o.h, (h - o.h) / 2, ...others.flatMap((x) => [x.y, x.y + x.h - o.h, x.y + x.h, x.y - o.h])];
            next = { ...o, x: e.altKey ? o.x + dx : snap(o.x + dx, xs), y: e.altKey ? o.y + dy : snap(o.y + dy, ys) };
        } else {
            let { x, y, w: ew, h: eh } = o;
            const hd = d.handle;
            if (hd.includes('e')) ew = o.w + dx;
            if (hd.includes('s')) eh = o.h + dy;
            if (hd.includes('w')) { ew = o.w - dx; x = o.x + dx; }
            if (hd.includes('n')) { eh = o.h - dy; y = o.y + dy; }
            if (ew < 0.5) { ew = 0.5; if (hd.includes('w')) x = o.x + o.w - 0.5; }
            if (eh < 0.5) { eh = 0.5; if (hd.includes('n')) y = o.y + o.h - 0.5; }
            next = { ...o, x, y, w: ew, h: eh };
        }
        next = clampElement(next, design.tag);
        onChange?.(design.elements.map((x) => (x.id === d.id ? next : x)), false);
    };

    const end = (e) => {
        const d = drag.current;
        drag.current = null;
        if (!d) return;
        e.currentTarget.releasePointerCapture?.(e.pointerId);
        if (d.moved) onChange?.(design.elements, true);
    };

    return (
        <svg
            ref={svgRef}
            viewBox={`0 0 ${w} ${h}`}
            width={round(w * scale, 1)}
            height={round(h * scale, 1)}
            className="block touch-none select-none"
            style={{ background: round_ ? 'transparent' : '#fff', boxShadow: round_ ? 'none' : '0 0 0 1px #d1d5db', overflow: 'visible' }}
            data-testid={testId}
            data-shape={shape}
            onPointerDown={() => interactive && onSelect?.(null)}
            onPointerMove={interactive ? move : undefined}
            onPointerUp={interactive ? end : undefined}
            onPointerCancel={interactive ? end : undefined}
        >
            {round_ && (
                <defs><clipPath id={clipId}><ellipse cx={w / 2} cy={h / 2} rx={w / 2} ry={h / 2} /></clipPath></defs>
            )}
            <g clipPath={round_ ? `url(#${clipId})` : undefined}>
                <rect x="0" y="0" width={w} height={h} fill={background} />
                {design.background?.image && images[design.background.image] && (
                    <image href={images[design.background.image]} x="0" y="0" width={w} height={h} preserveAspectRatio="xMidYMid slice" />
                )}
                {design.elements.map((el) => (
                    <Element key={el.id} el={el} design={design} fonts={fonts} images={images} row={row} interactive={interactive} selected={selected === el.id} hs={hs} start={start} />
                ))}
            </g>
            {round_ && <ellipse cx={w / 2} cy={h / 2} rx={w / 2} ry={h / 2} fill="none" stroke="#9ca3af" strokeWidth={0.3} strokeDasharray="1 1" style={{ pointerEvents: 'none' }} />}
        </svg>
    );
}

function Element({ el, design, fonts, images, row, interactive, selected, hs, start }) {
    const cx = el.x + el.w / 2;
    const cy = el.y + el.h / 2;
    const transform = el.rotate ? `rotate(${el.rotate} ${cx} ${cy})` : undefined;
    let body = null;
    switch (el.type) {
        case 'text': {
            const family = fonts[el.font]?.family || 'sans-serif';
            const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[el.align] || 'center';
            const textAlign = { left: 'start', center: 'center', right: 'end' }[el.align] || 'center';
            body = (
                <foreignObject x={el.x} y={el.y} width={el.w} height={el.h} style={{ pointerEvents: 'none', overflow: 'hidden' }}>
                    <div
                        xmlns="http://www.w3.org/1999/xhtml"
                        dir={el.dir || 'auto'}
                        style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: justify, textAlign, fontFamily: `'${family}', 'Figtree', 'Noto Sans Thaana', 'Cairo', sans-serif`, fontSize: `${round((el.size || 12) * PT_TO_MM, 3)}px`, fontWeight: el.weight || 400, color: resolveColour(el.color, design) || '#111111', lineHeight: 1.15, whiteSpace: 'pre-wrap', overflow: 'hidden', boxSizing: 'border-box' }}
                    >
                        {fillText(el, row)}
                    </div>
                </foreignObject>
            );
            break;
        }
        case 'image': {
            const src = el.media ? images[el.media] : null;
            body = src
                ? <image href={src} x={el.x} y={el.y} width={el.w} height={el.h} preserveAspectRatio={el.fit === 'cover' ? 'xMidYMid slice' : 'xMidYMid meet'} style={{ pointerEvents: 'none' }} />
                : <rect x={el.x} y={el.y} width={el.w} height={el.h} fill="#f3f4f6" stroke="#9ca3af" strokeWidth={0.3} strokeDasharray="1 1" style={{ pointerEvents: 'none' }} />;
            break;
        }
        case 'rect':
            body = <rect x={el.x} y={el.y} width={el.w} height={el.h} rx={el.radius || 0} fill={resolveColour(el.fill, design) || 'transparent'} stroke={resolveColour(el.stroke, design) || 'none'} strokeWidth={el.stroke ? el.stroke_mm || 0.3 : 0} style={{ pointerEvents: 'none' }} />;
            break;
        case 'ellipse':
            body = <ellipse cx={cx} cy={cy} rx={el.w / 2} ry={el.h / 2} fill={resolveColour(el.fill, design) || 'transparent'} stroke={resolveColour(el.stroke, design) || 'none'} strokeWidth={el.stroke ? el.stroke_mm || 0.3 : 0} style={{ pointerEvents: 'none' }} />;
            break;
        case 'line':
        default:
            body = <rect x={el.x} y={el.y} width={el.w} height={el.h} fill={resolveColour(el.stroke, design) || '#111111'} style={{ pointerEvents: 'none' }} />;
    }

    return (
        <g transform={transform} data-testid={`tag-el-${el.id}`} data-type={el.type}>
            {body}
            {interactive && (
                <rect
                    x={el.x} y={el.y} width={Math.max(el.w, 2)} height={Math.max(el.h, 2)} fill="transparent"
                    stroke={selected ? '#2563eb' : 'transparent'} strokeWidth={selected ? 0.3 : 0} strokeDasharray={el.locked ? '1 0.6' : undefined}
                    style={{ cursor: el.locked ? 'not-allowed' : 'move' }}
                    onPointerDown={(e) => start(e, el, 'move')}
                />
            )}
            {interactive && selected && !el.locked && HANDLES.map((hd) => {
                const hx = hd.includes('w') ? el.x : hd.includes('e') ? el.x + el.w : el.x + el.w / 2;
                const hy = hd.includes('n') ? el.y : hd.includes('s') ? el.y + el.h : el.y + el.h / 2;
                return <rect key={hd} x={hx - hs / 2} y={hy - hs / 2} width={hs} height={hs} fill="#fff" stroke="#2563eb" strokeWidth={0.25} style={{ cursor: CURSORS[hd] }} onPointerDown={(e) => start(e, el, 'resize', hd)} data-handle={hd} />;
            })}
        </g>
    );
}
