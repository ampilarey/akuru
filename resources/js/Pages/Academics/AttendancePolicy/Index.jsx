import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ settings }) {
    const form = useForm({ ...settings });

    return (
        <AppShell title="Attendance policy">
            <p className="mb-4 text-sm text-gray-600">
                How the school counts attendance. These numbers move the reported figure for
                every pupil at once, so each one says plainly what it does — and each rule can
                be turned off by setting it to zero.
            </p>

            <form
                className="grid max-w-2xl gap-4 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); form.put('/academics/attendance-policy', { preserveScroll: true }); }}
            >
                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">How attendance is taken</span>
                    <select className="form-input w-full" value={form.data.mode}
                        onChange={(e) => form.setData('mode', e.target.value)}>
                        <option value="per_lesson">Once per lesson</option>
                        <option value="daily">Once a day</option>
                    </select>
                    {form.errors.mode && <span className="mt-1 block text-xs text-red-600">{form.errors.mode}</span>}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">What families are told about</span>
                    <select className="form-input w-full" value={form.data.notify}
                        onChange={(e) => form.setData('notify', e.target.value)}>
                        <option value="absent_only">Absences only</option>
                        <option value="absent_and_late">Absences and late arrivals</option>
                    </select>
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">Chronic absence starts at</span>
                    <input type="number" min="1" max="100" className="form-input w-32"
                        value={form.data.chronic_threshold}
                        onChange={(e) => form.setData('chronic_threshold', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        Days absent before a pupil appears on the chronic-absence list.
                    </span>
                    {form.errors.chronic_threshold && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.chronic_threshold}</span>
                    )}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">Late marks counted as one absence</span>
                    <input type="number" min="0" max="20" className="form-input w-32"
                        value={form.data.tardies_per_absence}
                        onChange={(e) => form.setData('tardies_per_absence', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        Reported beside the absence figures, never folded into them.{' '}
                        <strong>0 turns this off.</strong>
                    </span>
                    {form.errors.tardies_per_absence && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.tardies_per_absence}</span>
                    )}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">
                        Minutes late before a lesson stops counting as attended
                    </span>
                    <input type="number" min="0" max="240" className="form-input w-32"
                        value={form.data.part_lesson_minutes}
                        onChange={(e) => form.setData('part_lesson_minutes', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        Without this, a pupil who arrives 35 minutes into a 40-minute lesson counts
                        exactly like one who was on time. The register is not changed — the mark
                        stays “late” with its minutes, and clearing this brings the old figures
                        back. <strong>0 turns this off.</strong>
                    </span>
                    {form.errors.part_lesson_minutes && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.part_lesson_minutes}</span>
                    )}
                </label>

                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                    Save policy
                </button>
            </form>
        </AppShell>
    );
}
