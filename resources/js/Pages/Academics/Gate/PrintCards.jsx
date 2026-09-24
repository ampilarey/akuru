import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

/**
 * A printable sheet of gate cards (E18), eight to an A4 page: the logo, the
 * pupil's name and class, the QR, and the code in fours for when a scan fails.
 * No shell — this page is for the printer.
 *
 * The QR is drawn here, as SVG, so it prints sharp and the host needs no
 * image library. Error correction "M" survives a scuffed or folded card.
 */
function Qr({ code }) {
    const [svg, setSvg] = useState('');

    useEffect(() => {
        let live = true;
        QRCode.toString(code, { type: 'svg', errorCorrectionLevel: 'M', margin: 1 })
            .then((markup) => live && setSvg(markup))
            .catch(() => live && setSvg(''));
        return () => {
            live = false;
        };
    }, [code]);

    return <div className="h-32 w-32 shrink-0" data-qr={svg ? 'drawn' : 'pending'} dangerouslySetInnerHTML={{ __html: svg }} />;
}

export default function PrintCards({ class_name: className = '', pupils = [] }) {
    return (
        <div className="min-h-screen bg-white p-6 text-gray-900" dir="ltr">
            <style>{'@page { size: A4; margin: 10mm } @media print { .no-print { display: none } }'}</style>
            <div className="no-print mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-600">{pupils.length} cards · {className}</p>
                <button type="button" className="btn-primary" onClick={() => window.print()}>Print</button>
            </div>
            <div className="grid grid-cols-2 gap-4">
                {pupils.map((p) => (
                    <div
                        key={p.student_id}
                        data-gate-card={p.card.code}
                        className="flex break-inside-avoid items-center gap-4 rounded-xl border-2 border-[#7C2D37] p-4"
                        style={{ pageBreakInside: 'avoid' }}
                    >
                        <Qr code={p.card.code} />
                        <div className="min-w-0">
                            <img src="/images/logos/akuru-logo.svg?v=3" alt="Akuru Institute" className="mb-2 h-8" />
                            <p className="truncate text-lg font-bold">{p.name}</p>
                            <p className="text-sm text-gray-600">{className}{p.student_number ? ` · ${p.student_number}` : ''}</p>
                            <p className="mt-2 font-mono text-xs tracking-wider text-gray-700">{p.card.readable}</p>
                        </div>
                    </div>
                ))}
            </div>
            {pupils.length === 0 && <p className="text-sm text-gray-600">No cards issued for this class yet.</p>}
        </div>
    );
}
