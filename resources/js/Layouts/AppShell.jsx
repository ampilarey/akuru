import { Link, usePage } from '@inertiajs/react';

export default function AppShell({ title, children }) {
    const { locale, rtl, auth, flash } = usePage().props;
    const user = auth?.user;

    return (
        <div dir={rtl ? 'rtl' : 'ltr'} className="min-h-screen bg-[#F9F4EE] text-gray-900">
            <header className="border-b border-[#E6D9C8] bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-[#7C2D37]">Akuru</p>
                        <h1 className="text-xl font-semibold">{title}</h1>
                    </div>
                    <nav className="flex flex-wrap items-center gap-3 text-sm">
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
                        <span className="text-gray-500">{user?.name}</span>
                        <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs uppercase">{locale}</span>
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
