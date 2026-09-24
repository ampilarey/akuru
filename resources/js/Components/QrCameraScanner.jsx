import jsQR from 'jsqr';
import { useEffect, useRef, useState } from 'react';
import { CAMERA_DENIED, closeCamera, openCamera } from '../Platform';

/**
 * Reads QR codes from the device camera (E18 gate cards).
 *
 * `jsQR` rather than the browser's `BarcodeDetector`, because Safari on iPads
 * and iPhones has none, and a gate tablet is as likely to be one of those as
 * anything. The rear camera is asked for first. Each distinct code is handed
 * to `onCode` once; the same card held up for a few seconds is not a second
 * scan (the server also ignores a repeat inside two minutes).
 */
export default function QrCameraScanner({ onCode, onClose }) {
    const video = useRef(null);
    const canvas = useRef(null);
    const last = useRef({ code: '', at: 0 });
    const [error, setError] = useState('');

    useEffect(() => {
        let stream = null;
        let frame = 0;
        let stopped = false;

        const tick = () => {
            if (stopped) return;
            const v = video.current;
            const c = canvas.current;
            if (v && c && v.readyState >= 2 && v.videoWidth > 0) {
                c.width = v.videoWidth;
                c.height = v.videoHeight;
                const context = c.getContext('2d', { willReadFrequently: true });
                context.drawImage(v, 0, 0, c.width, c.height);
                const image = context.getImageData(0, 0, c.width, c.height);
                const found = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
                const now = Date.now();
                if (found?.data && (found.data !== last.current.code || now - last.current.at > 5000)) {
                    last.current = { code: found.data, at: now };
                    onCode(found.data);
                }
            }
            frame = requestAnimationFrame(tick);
        };

        openCamera()
            .then((media) => {
                if (stopped) {
                    closeCamera(media);
                    return;
                }
                stream = media;
                video.current.srcObject = media;
                video.current.play().catch(() => {});
                frame = requestAnimationFrame(tick);
            })
            .catch((reason) => setError(reason === CAMERA_DENIED
                ? 'The camera could not be opened. Allow camera access for this site, or use a handheld scanner, or type the code.'
                : 'This device has no camera the page can use. Use a handheld scanner, or type the code.'));

        return () => {
            stopped = true;
            cancelAnimationFrame(frame);
            closeCamera(stream);
        };
    }, [onCode]);

    return (
        <div className="mt-3 rounded-lg border bg-black/90 p-2">
            {error ? (
                <p className="p-3 text-sm text-white">{error}</p>
            ) : (
                <video ref={video} muted playsInline className="mx-auto max-h-72 w-full rounded object-contain" data-testid="gate-camera" />
            )}
            <canvas ref={canvas} className="hidden" />
            <div className="mt-2 flex justify-between px-1 text-xs text-gray-300">
                <span>Hold the card inside the picture.</span>
                <button type="button" className="text-white underline" onClick={onClose}>Close camera</button>
            </div>
        </div>
    );
}
