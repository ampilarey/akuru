/**
 * How a teacher marked work with a rubric (Moodle parity slice M2, STATUS
 * §5oi): each criterion with the level chosen, as it read when marked.
 */
export default function RubricResult({ scores, t = {} }) {
    if (!scores || !Array.isArray(scores.criteria) || scores.criteria.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 overflow-x-auto rounded-lg border bg-white" data-testid="rubric-result">
            <p className="bg-[#F3EBE0] px-3 py-2 text-sm font-medium">{t.rubric_result || 'How it was marked'} · {scores.title}</p>
            <table className="min-w-full text-sm">
                <tbody>
                    {scores.criteria.map((criterion) => (
                        <tr key={criterion.id} className="border-t">
                            <th className="px-3 py-2 text-start font-medium">{criterion.title}</th>
                            <td className="px-3 py-2">{criterion.level}</td>
                            <td className="px-3 py-2 text-end text-gray-600">{criterion.points} / {criterion.max_points}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="border-t px-3 py-2 text-sm">
                {(t.rubric_mark_total || 'Rubric: :points of :max points, so :score out of :out_of')
                    .replace(':points', scores.points).replace(':max', scores.max_points).replace(':score', scores.score).replace(':out_of', scores.out_of)}
            </p>
        </div>
    );
}
