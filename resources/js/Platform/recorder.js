/**
 * SPEC §6.3 "Mobile App Path":
 *
 *   > Avoid browser-only APIs without fallbacks.
 *   > Keep web/native differences behind a single platform abstraction layer.
 *   > Do not scatter browser/native detection logic across components.
 *   > **Audio recording must use a replaceable recorder interface.**
 *   > MediaRecorder can be used on web.
 *   > **A Capacitor native audio plugin can replace it later behind the same
 *   > interface.**
 *
 * and SPEC §6.4 "Platform Abstraction Layer":
 *
 *   > Create a small frontend platform abstraction layer for environment-
 *   > specific features... **Do not spread platform-specific logic through
 *   > React components.**
 *
 * The layer did not exist. `resources/js/` held `Components/CustomFields.jsx`
 * and nothing else, and `navigator.mediaDevices.getUserMedia()` plus
 * `new MediaRecorder(stream)` were called straight from inside
 * `Pages/Pronunciation/Practice.jsx` — a page component. There was no
 * interface for a Capacitor plugin to replace.
 *
 * This is that interface. Its shape is what a native plugin has to satisfy:
 *
 *     const recorder = createRecorder();
 *     await recorder.start();
 *     const blob = await recorder.stop();   // or recorder.cancel()
 *
 * **The silent failure is the other half.** The old code was:
 *
 *     } catch {
 *         setRecording(false);
 *     }
 *
 * so a denied microphone, an insecure (non-HTTPS) context, or a browser
 * without `MediaRecorder` all produced a Record button that did **nothing at
 * all** — no message, nothing to act on. Indistinguishable from a broken page,
 * and the exact "avoid browser-only APIs without fallbacks" §6.3 asks for.
 * `describeRecordingFailure()` turns each case into something a student can
 * read.
 */

export const RECORDER_UNSUPPORTED = 'unsupported';
export const RECORDER_INSECURE = 'insecure';
export const RECORDER_DENIED = 'denied';
export const RECORDER_NO_DEVICE = 'no_device';
export const RECORDER_FAILED = 'failed';
/**
 * The site's own `Permissions-Policy` header forbids the microphone.
 *
 * Distinct from `RECORDER_DENIED` because the advice is the opposite. A denied
 * microphone is the student's to fix in their browser; a policy block is the
 * server's, and **no browser setting can override it** — so telling a student
 * to "allow microphone access for this site and try again" sends them to fiddle
 * with a permission that was never the problem. That is exactly what happened
 * while the header read `microphone=()` (STATUS §5ej).
 */
export const RECORDER_BLOCKED_BY_SITE = 'blocked_by_site';

/**
 * Can this environment record at all? Asked before the button is offered, so a
 * student is told why rather than pressing a control that cannot work.
 *
 * `getUserMedia` is unavailable in an insecure context, which is not an exotic
 * case: it is how this app is reached over a LAN or plain http.
 */
export function recordingSupport() {
    if (typeof window === 'undefined') {
        return { supported: false, reason: RECORDER_UNSUPPORTED };
    }
    if (typeof window.MediaRecorder === 'undefined') {
        return { supported: false, reason: RECORDER_UNSUPPORTED };
    }
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        // `isSecureContext` is what actually decides it, so it is what we say.
        const insecure = typeof window.isSecureContext === 'boolean' && !window.isSecureContext;
        return { supported: false, reason: insecure ? RECORDER_INSECURE : RECORDER_UNSUPPORTED };
    }

    // Asked before the button is offered, because a policy block throws the
    // same `NotAllowedError` a student's own refusal does — and the two need
    // opposite advice. `permissionsPolicy` is the current name and
    // `featurePolicy` the one Chrome shipped first; a browser with neither
    // tells us nothing, and silence is not a refusal.
    const policy = document?.permissionsPolicy ?? document?.featurePolicy;
    if (typeof policy?.allowsFeature === 'function' && !policy.allowsFeature('microphone')) {
        return { supported: false, reason: RECORDER_BLOCKED_BY_SITE };
    }

    return { supported: true, reason: null };
}

/**
 * Map a thrown error to one of the reasons above. Browsers use `name` for
 * these, which is more reliable than the message text.
 */
export function classifyRecorderError(error) {
    const name = error && typeof error === 'object' ? error.name : null;

    if (name === 'NotAllowedError' || name === 'SecurityError') {
        return RECORDER_DENIED;
    }
    if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        return RECORDER_NO_DEVICE;
    }

    return RECORDER_FAILED;
}

/**
 * A sentence a student can act on, for each reason.
 *
 * `translations` lets a caller pass the page's i18n bundle, so this stays
 * translatable without the platform layer reaching for a translator of its own.
 */
export function describeRecordingFailure(reason, translations = {}) {
    const fallbacks = {
        [RECORDER_UNSUPPORTED]: 'This browser cannot record audio. Try Chrome, Edge or Safari, or upload a file instead.',
        [RECORDER_INSECURE]: 'Recording needs a secure (https) connection. Open this page over https, or upload a file instead.',
        [RECORDER_DENIED]: 'The microphone was blocked. Allow microphone access for this site and try again.',
        [RECORDER_BLOCKED_BY_SITE]: 'This site is set up to block the microphone, so recording cannot start. This is not something you can change — please tell the school.',
        [RECORDER_NO_DEVICE]: 'No microphone was found. Connect one and try again.',
        [RECORDER_FAILED]: 'Recording could not start. Try again, or upload a file instead.',
    };

    return translations[`recorder_${reason}`] || fallbacks[reason] || fallbacks[RECORDER_FAILED];
}

/**
 * The web implementation. §6.3: "MediaRecorder can be used on web. A Capacitor
 * native audio plugin can replace it later behind the same interface."
 *
 * `createRecorder` is the seam — swapping in a native plugin means returning a
 * different object from here, and no page component changes.
 */
export function createRecorder() {
    let recorder = null;
    let stream = null;
    let chunks = [];

    const releaseStream = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    };

    return {
        async start() {
            const support = recordingSupport();
            if (!support.supported) {
                const error = new Error('Recording is not available.');
                error.reason = support.reason;
                throw error;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (error) {
                const wrapped = new Error('Could not open the microphone.');
                wrapped.reason = classifyRecorderError(error);
                throw wrapped;
            }

            chunks = [];
            recorder = new MediaRecorder(stream);
            recorder.ondataavailable = (event) => chunks.push(event.data);
            recorder.start();
        },

        /**
         * Resolves with the recorded audio. The stream is released either way —
         * a live microphone left open after a recording is both a privacy
         * problem and a recording light that never goes out.
         */
        stop() {
            return new Promise((resolve, reject) => {
                if (recorder === null) {
                    releaseStream();
                    reject(new Error('Nothing is being recorded.'));
                    return;
                }

                recorder.onstop = () => {
                    const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                    releaseStream();
                    recorder = null;
                    resolve(blob);
                };
                recorder.stop();
            });
        },

        cancel() {
            if (recorder !== null && recorder.state !== 'inactive') {
                recorder.onstop = null;
                recorder.stop();
            }
            recorder = null;
            chunks = [];
            releaseStream();
        },
    };
}
