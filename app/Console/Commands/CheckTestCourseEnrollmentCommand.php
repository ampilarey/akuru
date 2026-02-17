<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use Illuminate\Console\Command;

class CheckTestCourseEnrollmentCommand extends Command
{
    protected $signature = 'enrollment:check-test-course';

    protected $description = 'Print latest Test Course enrollment and payment state (for debugging BML redirect)';

    public function handle(): int
    {
        $course = Course::where('slug', 'test-course-with-joining-fee')->first();
        if (! $course) {
            $this->line('Course test-course-with-joining-fee not found.');
            return self::FAILURE;
        }

        $e = CourseEnrollment::where('course_id', $course->id)->latest()->first();
        if (! $e) {
            $this->line('No enrollment found for this course.');
            return self::SUCCESS;
        }

        $this->line('enrollment_id: ' . $e->id);
        $this->line('student_id: ' . $e->student_id);
        $this->line('payment_status: ' . $e->payment_status);
        $this->line('payment_id: ' . ($e->payment_id ?? 'null'));

        if ($e->payment_id) {
            $p = Payment::find($e->payment_id);
            $this->line('payment_status: ' . ($p ? $p->status : 'not found'));
        }

        return self::SUCCESS;
    }
}
