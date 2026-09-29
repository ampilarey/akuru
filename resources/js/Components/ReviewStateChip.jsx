/**
 * RESEARCH_ARTICLES_PLAN R3: where a research item's peer review stands, in
 * words the writer and the office both read. `state` comes from the
 * server's AssertResearchReviewedAction::state().
 */
const TONES = {
    awaiting_reviewer: 'bg-gray-100 text-gray-700',
    with_reviewer: 'bg-blue-50 text-blue-800',
    revision_requested: 'bg-amber-50 text-amber-800',
    accepted_awaiting_publish: 'bg-green-50 text-green-800',
    rejected: 'bg-red-50 text-red-700',
};

export function reviewStateLabel(state, t = {}) {
    if (!state) return '';
    const fill = (text) => String(text).replace(':accepts', state.accepts).replace(':required', state.required).replace(':round', state.round);
    const labels = {
        awaiting_reviewer: t.review_state_awaiting_reviewer || 'Waiting for a peer reviewer',
        with_reviewer: t.review_state_with_reviewer || 'With peer reviewers — :accepts of :required accepts',
        revision_requested: t.review_state_revision_requested || 'A reviewer asked for revisions',
        accepted_awaiting_publish: t.review_state_accepted || 'Accepted by peer review — ready to publish',
        rejected: t.review_state_rejected || 'Not accepted',
    };
    const round = state.round > 1 ? ` · ${fill(t.review_state_round || 'round :round')}` : '';

    return fill(labels[state.state] || state.state) + round;
}

export default function ReviewStateChip({ state, t = {} }) {
    if (!state) return null;

    return (
        <span className={`inline-block rounded px-2 py-0.5 text-xs ${TONES[state.state] || 'bg-gray-100'}`} data-testid="review-state" data-state={state.state}>
            {reviewStateLabel(state, t)}
        </span>
    );
}
