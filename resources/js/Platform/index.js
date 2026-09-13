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
 * *later* responsibilities as well as present ones — only audio recording is
 * implemented, because it is the only browser-only API the app actually calls
 * today. The rest arrive here when something needs them, rather than as empty
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
