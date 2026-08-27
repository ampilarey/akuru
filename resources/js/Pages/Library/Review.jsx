import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

function AssignmentCard({ assignment }) {
    const [comment, setComment] = useState('');
    const item = assignment.item;

    const submit = (recommendation) =>
        router.post(`/review/${assignment.id}`, { recommendation, comment: comment || undefined }, { preserveScroll: true });

    return (
        <div className="mb-4 rounded-lg border bg-white p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">{item?.title}</h2>
                    <p className="text-xs text-gray-500">
                        Assigned {assignment.assigned_at} · item {item?.status?.replaceAll('_', ' ')}
                        {assignment.recommendation ? ` · your recommendation: ${assignment.recommendation}` : ''}
                    </p>
                </div>
            </div>
            {item?.abstract && <p className="mb-2 text-sm text-gray-700">{item.abstract}</p>}
            {item?.body && (
                <div className="mb-2 max-h-96 overflow-y-auto rounded border bg-gray-50 p-3 text-sm" dangerouslySetInnerHTML={{ __html: item.body }} />
            )}
            {item?.citations && (
                <div className="mb-2 text-xs text-gray-600">
                    <p className="font-semibold">Citations</p>
                    <pre className="whitespace-pre-wrap font-sans">{item.citations}</pre>
                </div>
            )}
            <textarea
                className="form-input mb-2 w-full"
                rows="3"
                placeholder="Review comments for the writer"
                value={comment}
                onChange={(e) => setComment(e.target.value)}
            />
            <div className="flex flex-wrap gap-2">
                <button type="button" className="btn-primary" onClick={() => submit('accept')}>Recommend accept</button>
                <button type="button" className="btn-secondary" onClick={() => submit('revise')}>Needs revision</button>
                <button type="button" className="text-sm text-red-600" onClick={() => submit('reject')}>Recommend reject</button>
            </div>
        </div>
    );
}

export default function Review({ assignments }) {
    const flash = usePage().props.flash || {};

    return (
        <AppShell title="Peer review">
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}
            {assignments.length === 0 && <p className="text-gray-500">No review assignments.</p>}
            {assignments.map((assignment) => <AssignmentCard key={assignment.id} assignment={assignment} />)}
        </AppShell>
    );
}
