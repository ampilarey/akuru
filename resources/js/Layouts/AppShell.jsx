import { Link, usePage } from '@inertiajs/react';

export default function AppShell({ title, children }) {
    const { locale, locales = ['en', 'dv', 'ar'], locale_urls = {}, rtl, auth, flash, i18n } = usePage().props;
    const user = auth?.user;
    const t = i18n?.learn || {};

    return (
        <div dir={rtl ? 'rtl' : 'ltr'} className="min-h-screen bg-[#F9F4EE] text-gray-900">
            <header className="border-b border-[#E6D9C8] bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-[#7C2D37]">Akuru</p>
                        <h1 className="text-xl font-semibold">{title}</h1>
                    </div>
                    <nav className="flex flex-wrap items-center gap-3 text-sm">
                        <Link href="/learn" className="text-[#7C2D37] hover:underline">
                            {i18n?.learn?.nav || 'Learn'}
                        </Link>
                        <Link href="/learn/schedule" className="text-[#7C2D37] hover:underline">
                            {i18n?.learn?.schedule || 'Schedule'}
                        </Link>
                        <Link href="/teach/schedule" className="text-[#7C2D37] hover:underline">
                            Teach
                        </Link>
                        <Link href="/portal/learning" className="text-[#7C2D37] hover:underline">
                            {i18n?.learn?.children_learning || 'Children'}
                        </Link>
                        <Link href="/catalog/courses" className="text-[#7C2D37] hover:underline">
                            Catalog
                        </Link>
                        <Link href="/catalog/offerings" className="text-[#7C2D37] hover:underline">
                            Offerings
                        </Link>
                        <Link href="/catalog/questions" className="text-[#7C2D37] hover:underline">
                            Questions
                        </Link>
                        <Link href="/catalog/reviews" className="text-[#7C2D37] hover:underline">
                            Reviews
                        </Link>
                        <Link href="/catalog/arabic" className="text-[#7C2D37] hover:underline">
                            Arabic
                        </Link>
                        <Link href="/catalog/arabic/reports" className="text-[#7C2D37] hover:underline">
                            Arabic report
                        </Link>
                        <Link href="/catalog/quran" className="text-[#7C2D37] hover:underline">
                            Qur’an
                        </Link>
                        <Link href="/catalog/subjects" className="text-[#7C2D37] hover:underline">
                            Subjects
                        </Link>
                        <Link href="/catalog/audiences" className="text-[#7C2D37] hover:underline">
                            Audiences
                        </Link>
                        <Link href="/catalog/levels" className="text-[#7C2D37] hover:underline">
                            Levels
                        </Link>
                        <Link href="/people/students" className="text-[#7C2D37] hover:underline">
                            Students
                        </Link>
                        <Link href="/people/custom-fields" className="text-[#7C2D37] hover:underline">
                            Custom fields
                        </Link>
                        <Link href="/people/staff" className="text-[#7C2D37] hover:underline">
                            Staff
                        </Link>
                        <Link href="/academics/years" className="text-[#7C2D37] hover:underline">
                            Years
                        </Link>
                        <Link href="/academics/rooms" className="text-[#7C2D37] hover:underline">
                            Rooms
                        </Link>
                        <Link href="/academics/timetable" className="text-[#7C2D37] hover:underline">
                            Timetable
                        </Link>
                        <Link href="/academics/bookings" className="text-[#7C2D37] hover:underline">
                            Bookings
                        </Link>
                        <Link href="/academics/calendar" className="text-[#7C2D37] hover:underline">
                            Calendar
                        </Link>
                        <Link href="/portal/holidays" className="text-[#7C2D37] hover:underline">
                            Holidays
                        </Link>
                        <Link href="/academics/registers/today" className="text-[#7C2D37] hover:underline">
                            Today
                        </Link>
                        <Link href="/academics/registers" className="text-[#7C2D37] hover:underline">
                            Registers
                        </Link>
                        <Link href="/academics/plans" className="text-[#7C2D37] hover:underline">
                            Plans
                        </Link>
                        <Link href="/academics/attendance" className="text-[#7C2D37] hover:underline">
                            Attendance
                        </Link>
                        <Link href="/portal/attendance" className="text-[#7C2D37] hover:underline">
                            My attendance
                        </Link>
                        <Link href="/portal/absence-notes" className="text-[#7C2D37] hover:underline">
                            Absence notes
                        </Link>
                        <Link href="/academics/absence-notes" className="text-[#7C2D37] hover:underline">
                            Review notes
                        </Link>
                        <Link href="/academics/behavior" className="text-[#7C2D37] hover:underline">
                            Behavior
                        </Link>
                        <Link href="/portal/behavior" className="text-[#7C2D37] hover:underline">
                            My behavior
                        </Link>
                        <Link href="/academics/requests" className="text-[#7C2D37] hover:underline">
                            Requests
                        </Link>
                        <Link href="/exams/scales" className="text-[#7C2D37] hover:underline">
                            Scales
                        </Link>
                        <Link href="/exams/types" className="text-[#7C2D37] hover:underline">
                            Exam types
                        </Link>
                        <Link href="/exams/weights" className="text-[#7C2D37] hover:underline">
                            Weights
                        </Link>
                        <Link href="/exams/schedule" className="text-[#7C2D37] hover:underline">
                            Exams
                        </Link>
                        <Link href="/exams/gradebook" className="text-[#7C2D37] hover:underline">
                            Gradebook
                        </Link>
                        <Link href="/exams/competencies" className="text-[#7C2D37] hover:underline">
                            Competencies
                        </Link>
                        <Link href="/exams/standards" className="text-[#7C2D37] hover:underline">
                            Standards
                        </Link>
                        <Link href="/exams/report-templates" className="text-[#7C2D37] hover:underline">
                            Report templates
                        </Link>
                        <Link href="/exams/report-cards" className="text-[#7C2D37] hover:underline">
                            Report cards
                        </Link>
                        <Link href="/exams/awards" className="text-[#7C2D37] hover:underline">
                            Awards
                        </Link>
                        <Link href="/portal/exams" className="text-[#7C2D37] hover:underline">
                            Results
                        </Link>
                        <Link href="/portal/report-cards" className="text-[#7C2D37] hover:underline">
                            Report cards
                        </Link>
                        <Link href="/portal/awards" className="text-[#7C2D37] hover:underline">
                            Awards
                        </Link>
                        <Link href="/finance/fee-items" className="text-[#7C2D37] hover:underline">
                            Fee items
                        </Link>
                        <Link href="/finance/fee-structures" className="text-[#7C2D37] hover:underline">
                            Fee structures
                        </Link>
                        <Link href="/finance/invoices" className="text-[#7C2D37] hover:underline">
                            Invoices
                        </Link>
                        <Link href="/finance/arrears" className="text-[#7C2D37] hover:underline">
                            Arrears
                        </Link>
                        <Link href="/finance/payment-plans" className="text-[#7C2D37] hover:underline">
                            Payment plans
                        </Link>
                        <Link href="/finance/adjustments" className="text-[#7C2D37] hover:underline">
                            Adjustments
                        </Link>
                        <Link href="/finance/receipts/manual" className="text-[#7C2D37] hover:underline">
                            Manual receipt
                        </Link>
                        <Link href="/finance/collections" className="text-[#7C2D37] hover:underline">
                            Collections
                        </Link>
                        <Link href="/finance/reconciliation" className="text-[#7C2D37] hover:underline">
                            Reconciliation
                        </Link>
                        <Link href="/portal/invoices" className="text-[#7C2D37] hover:underline">
                            Fees
                        </Link>
                        <Link href="/hr/attendance" className="text-[#7C2D37] hover:underline">
                            Staff attendance
                        </Link>
                        <Link href="/hr/attendance/reports" className="text-[#7C2D37] hover:underline">
                            Staff reports
                        </Link>
                        <Link href="/portal/staff-check-in" className="text-[#7C2D37] hover:underline">
                            Check in
                        </Link>
                        <Link href="/hr/leave-types" className="text-[#7C2D37] hover:underline">
                            Leave types
                        </Link>
                        <Link href="/hr/leave-balances" className="text-[#7C2D37] hover:underline">
                            Leave balances
                        </Link>
                        <Link href="/portal/leave" className="text-[#7C2D37] hover:underline">
                            My leave
                        </Link>
                        <Link href="/hr/contracts" className="text-[#7C2D37] hover:underline">
                            Contracts
                        </Link>
                        <Link href="/hr/compliance" className="text-[#7C2D37] hover:underline">
                            Compliance
                        </Link>
                        <Link href="/hr/postings" className="text-[#7C2D37] hover:underline">
                            Jobs
                        </Link>
                        <Link href="/hr/applications" className="text-[#7C2D37] hover:underline">
                            Applications
                        </Link>
                        <Link href="/hr/onboarding" className="text-[#7C2D37] hover:underline">
                            Onboarding
                        </Link>
                        <Link href="/hr/appraisals" className="text-[#7C2D37] hover:underline">
                            Appraisals
                        </Link>
                        <Link href="/hr/observations" className="text-[#7C2D37] hover:underline">
                            Observations
                        </Link>
                        <Link href="/hr/cpd" className="text-[#7C2D37] hover:underline">
                            CPD
                        </Link>
                        <Link href="/portal/appraisals" className="text-[#7C2D37] hover:underline">
                            My performance
                        </Link>
                        <Link href="/hr/payroll" className="text-[#7C2D37] hover:underline">
                            Payroll
                        </Link>
                        <Link href="/portal/payslips" className="text-[#7C2D37] hover:underline">
                            Payslips
                        </Link>
                        <span className="text-gray-500">{user?.name}</span>
                        <span className="flex items-center gap-1 rounded bg-[#F3EBE0] px-2 py-0.5 text-xs uppercase">
                            {locales.map((code) => (
                                <a
                                    key={code}
                                    href={locale_urls[code] || `/${code}`}
                                    className={code === locale ? 'font-semibold text-[#7C2D37]' : 'text-gray-600 hover:underline'}
                                    hrefLang={code}
                                >
                                    {t[`locale_${code}`] || code}
                                </a>
                            ))}
                        </span>
                    </nav>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-6 py-6">
                {flash?.success && (
                    <div className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
