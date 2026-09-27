<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Courses\Actions\CountEnrollmentsAction;
use App\Domains\Finance\Actions\SumPaidPaymentsAction;
use App\Domains\Identity\Actions\CountUsersAction;
use App\Domains\People\Actions\CountStudentsAction;
use App\Domains\People\Actions\CountTeachersAction;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Today's numbers for the top of a workspace's home (STATUS §5ia, §5id):
 * a strip of tiles above the parts, each tile a link to where the number
 * comes from, and one link to the full dashboard the person used to land
 * on. What a person sees is what their job needs:
 *
 *  - the Institute (the system admin): new accounts today, paid today;
 *  - the School's office (the educational admin, the dean): enrolments
 *    pending payment, enrolled today, paid today;
 *  - whoever may run registers or exams: unfilled registers and ungraded
 *    exams, from the staff overview;
 *  - the supervisor: students on the roll and teachers teaching.
 *
 * Every figure is asked of its owning domain's Action (rule 3). A workspace
 * with no numbers — a shop, a family — gets an empty strip and the page
 * shows none.
 *
 * @return array{tiles: list<array{key: string, label: string, value: string, href: ?string, hard: bool}>, more: ?array{label: string, href: string, hard: bool}}
 */
class ComposeAdminTodayAction
{
    public function execute(object $user, string $workspace = 'school'): array
    {
        if (! method_exists($user, 'hasAnyRole')) {
            return ['tiles' => [], 'more' => null];
        }
        $can = fn (string $ability): bool => $user instanceof Authorizable && $user->can($ability);
        $tiles = [];
        $more = null;

        if ($workspace === 'institute') {
            if ($user->hasRole('super_admin')) {
                $tiles[] = $this->tile('new_accounts', (string) app(CountUsersAction::class)->today(), '/admin/users', true);
                $tiles[] = $this->tile('paid_today', number_format(app(SumPaidPaymentsAction::class)->today(), 2), '/admin/enrollments/payments', false);
                $more = ['label' => __('admin.today_full_dashboard'), 'href' => '/dashboard/numbers', 'hard' => true];
            }

            return ['tiles' => $tiles, 'more' => $more];
        }

        if ($workspace !== 'school') {
            return ['tiles' => [], 'more' => null];
        }

        if ($user->hasAnyRole(['admin', 'headmaster'])) {
            $enrolments = app(CountEnrollmentsAction::class);
            $tiles[] = $this->tile('pending_payment', (string) $enrolments->pendingPayment(), '/admin/enrollments', false);
            $tiles[] = $this->tile('enrolled_today', (string) $enrolments->today(), '/admin/enrollments', false);
            $tiles[] = $this->tile('paid_today', number_format(app(SumPaidPaymentsAction::class)->today(), 2), '/admin/enrollments/payments', false);
        }

        if ($can('registers.manage') || $can('exams.manage')) {
            $overview = app(ComposeStaffOverviewAction::class)->execute();
            $tiles[] = $this->tile('unfilled_registers', (string) count($overview['unfilled']), '/academics/registers', false);
            $tiles[] = $this->tile('ungraded_exams', (string) count($overview['ungraded']), '/exams/schedule', false);
            $more = ['label' => __('admin.today_overview'), 'href' => '/portal/overview', 'hard' => false];
        }

        if ($user->hasRole('supervisor')) {
            $tiles[] = $this->tile('students_on_roll', (string) app(CountStudentsAction::class)->onTheRoll(), '/people/students', false);
            $tiles[] = $this->tile('teachers_teaching', (string) app(CountTeachersAction::class)->teaching(), '/people/staff', false);
            $more ??= ['label' => __('admin.today_full_dashboard'), 'href' => '/dashboard/supervisor', 'hard' => true];
        }

        return ['tiles' => $tiles, 'more' => $more];
    }

    /**
     * @return array{key: string, label: string, value: string, href: ?string, hard: bool}
     */
    private function tile(string $key, string $value, ?string $href, bool $hard): array
    {
        return ['key' => $key, 'label' => __('admin.today_'.$key), 'value' => $value, 'href' => $href, 'hard' => $hard];
    }
}
