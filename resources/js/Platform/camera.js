/**
 * The camera, behind the platform layer (SPEC §6.4). A native shell can
 * replace this module without touching the pages that scan.
 *
 * Used by the E18 gate card scanner. The rear camera is asked for first,
 * because a gate tablet is held facing the card.
 */

export const CAMERA_UNSUPPORTED = 'unsupported';
export const CAMERA_DENIED = 'denied';
export const CAMERA_FAILED = 'failed';

export function cameraSupported() {
    return typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia);
}

/** Resolves to a MediaStream; rejects with one of the CAMERA_* reasons. */
export async function openCamera() {
    if (!cameraSupported()) {
        throw CAMERA_UNSUPPORTED;
    }
    try {
        return await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    } catch (error) {
        throw error?.name === 'NotAllowedError' || error?.name === 'SecurityError' ? CAMERA_DENIED : CAMERA_FAILED;
    }
}

export function closeCamera(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}
