/**
 * SPEC §6.4 "Platform Abstraction Layer".
 *
 *   > Create a small frontend platform abstraction layer for environment-
 *   > specific features.
 *   >
 *   > Example responsibilities: **Audio recording** · File picking · Download
 *   > handling · Sharing later · Push notifications later · Device capability
 *   > detection · Storage/cache helpers later
 *   >
 *   > **Do not spread platform-specific logic through React components.**
 *
 * This is the single import point. §6.4's list is deliberately a list of
 * *later* responsibilities as well as present ones — audio recording, the
 * camera and small stored preferences are implemented, because they are the
 * browser-only APIs the app actually calls. The rest arrive here when something needs them, rather than as empty
 * modules that would have to be guessed at now.
 *
 * `WriteRoutesAreGuardedTest`'s sibling for the frontend,
 * `PlatformApisStayInLayerTest`, is what keeps that true: it fails if a
 * browser-only API appears anywhere under `resources/js/` except here.
 */
export {
    classifyRecorderError,
    createRecorder,
    describeRecordingFailure,
    RECORDER_DENIED,
    RECORDER_FAILED,
    RECORDER_INSECURE,
    RECORDER_NO_DEVICE,
    RECORDER_UNSUPPORTED,
    recordingSupport,
} from './recorder';
// E18 gate cards (STATUS §5ge): the camera for the QR scanner, and the
// gate's remembered direction.
export { CAMERA_DENIED, CAMERA_FAILED, CAMERA_UNSUPPORTED, cameraSupported, closeCamera, openCamera } from './camera';
export { readPreference, writePreference } from './storage';
