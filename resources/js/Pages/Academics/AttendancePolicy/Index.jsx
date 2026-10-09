import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ settings, t = {} }) {
    const form = useForm({ ...settings });
    // In the page's language (BACKLOG C21, slice OA1).
    const zeroOff = t.policy_zero_off || '0 turns this off.';

    return (
        <AppShell title={t.policy_title || 'Attendance policy'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.policy_intro || 'How the school counts attendance. These numbers move the reported figure for every pupil at once, so each one says plainly what it does — and each rule can be turned off by setting it to zero.'}
            </p>

            <form
                className="grid max-w-2xl gap-4 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); form.put('/academics/attendance-policy', { preserveScroll: true }); }}
            >
                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.policy_mode || 'How attendance is taken'}</span>
                    <select className="form-input w-full" value={form.data.mode}
                        onChange={(e) => form.setData('mode', e.target.value)}>
                        <option value="per_lesson">{t.policy_mode_per_lesson || 'Once per lesson'}</option>
                        <option value="daily">{t.policy_mode_daily || 'Once a day'}</option>
                    </select>
                    {form.errors.mode && <span className="mt-1 block text-xs text-red-600">{form.errors.mode}</span>}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.policy_notify || 'What families are told about'}</span>
                    <select className="form-input w-full" value={form.data.notify}
                        onChange={(e) => form.setData('notify', e.target.value)}>
                        <option value="absent_only">{t.policy_notify_absent_only || 'Absences only'}</option>
                        <option value="absent_and_late">{t.policy_notify_absent_and_late || 'Absences and late arrivals'}</option>
                    </select>
                    {form.errors.notify && <span className="mt-1 block text-xs text-red-600">{form.errors.notify}</span>}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.policy_chronic || 'Chronic absence starts at'}</span>
                    <input type="number" min="1" max="100" className="form-input w-32"
                        value={form.data.chronic_threshold}
                        onChange={(e) => form.setData('chronic_threshold', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.policy_chronic_hint || 'Days absent before a pupil appears on the chronic-absence list.'}
                    </span>
                    {form.errors.chronic_threshold && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.chronic_threshold}</span>
                    )}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.policy_tardies || 'Late marks counted as one absence'}</span>
                    <input type="number" min="0" max="20" className="form-input w-32"
                        value={form.data.tardies_per_absence}
                        onChange={(e) => form.setData('tardies_per_absence', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.policy_tardies_hint || 'Reported beside the absence figures, never folded into them.'}{' '}
                        <strong>{zeroOff}</strong>
                    </span>
                    {form.errors.tardies_per_absence && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.tardies_per_absence}</span>
                    )}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">
                        {t.policy_part_lesson || 'Minutes late before a lesson stops counting as attended'}
                    </span>
                    <input type="number" min="0" max="240" className="form-input w-32"
                        value={form.data.part_lesson_minutes}
                        onChange={(e) => form.setData('part_lesson_minutes', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.policy_part_lesson_hint || 'Without this, a pupil who arrives 35 minutes into a 40-minute lesson counts exactly like one who was on time. The register is not changed — the mark stays “late” with its minutes, and clearing this brings the old figures back.'}{' '}
                        <strong>{zeroOff}</strong>
                    </span>
                    {form.errors.part_lesson_minutes && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.part_lesson_minutes}</span>
                    )}
                </label>

                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                    {t.policy_save || 'Save policy'}
                </button>
            </form>
        </AppShell>
    );
}
