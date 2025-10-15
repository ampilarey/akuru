<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TeacherAbsence;
use App\Models\SubstitutionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\AbsenceNote;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Subject;
use App\Models\ClassRoom;
use App\Models\Period;
use App\Models\User;
use Carbon\Carbon;

class FeaturePackDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create academic year
        $academicYear = AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
            'is_current' => true,
            'description' => 'Academic Year 2024-2025',
        ]);

        // Create fee items
        $feeItems = [
            ['name' => 'Tuition Fee', 'description' => 'Monthly tuition fee', 'default_amount' => 1500.00, 'type' => 'tuition', 'frequency' => 'monthly'],
            ['name' => 'Registration Fee', 'description' => 'One-time registration fee', 'default_amount' => 500.00, 'type' => 'registration', 'frequency' => 'one_time'],
            ['name' => 'Examination Fee', 'description' => 'Semester examination fee', 'default_amount' => 200.00, 'type' => 'examination', 'frequency' => 'semester'],
            ['name' => 'Activity Fee', 'description' => 'Extra-curricular activities', 'default_amount' => 300.00, 'type' => 'activity', 'frequency' => 'semester'],
        ];

        foreach ($feeItems as $item) {
            FeeItem::create($item);
        }

        // Get existing data
        $teachers = Teacher::with('user')->take(5)->get();
        $students = Student::take(10)->get();
        $subjects = Subject::take(3)->get();
        $classrooms = ClassRoom::take(3)->get();
        $periods = Period::take(6)->get();

        if ($teachers->isEmpty() || $students->isEmpty()) {
            $this->command->info('Please run the main seeders first to create teachers and students.');
            return;
        }

        // Create teacher absences
        $this->command->info('Creating teacher absences...');
        foreach ($teachers->take(3) as $teacher) {
            TeacherAbsence::create([
                'teacher_id' => $teacher->id,
                'from_date' => Carbon::now()->addDays(rand(1, 7)),
                'to_date' => Carbon::now()->addDays(rand(8, 14)),
                'reason' => 'Medical appointment',
                'status' => 'approved',
                'created_by' => $teacher->user->id,
                'approved_by' => User::role('admin')->first()?->id ?? 1,
                'approved_at' => now(),
            ]);
        }

        // Create substitution requests
        $this->command->info('Creating substitution requests...');
        if ($subjects->isNotEmpty() && $classrooms->isNotEmpty() && $periods->isNotEmpty()) {
            foreach ($teachers->take(2) as $teacher) {
                SubstitutionRequest::create([
                    'date' => Carbon::now()->addDays(rand(1, 5)),
                    'absent_teacher_id' => $teacher->id,
                    'subject_id' => $subjects->random()->id,
                    'classroom_id' => $classrooms->random()->id,
                    'period_id' => $periods->random()->id,
                    'status' => 'open',
                    'notes' => 'Please cover Chapter 3 of the textbook.',
                ]);
            }
        }

        // Create quizzes
        $this->command->info('Creating quizzes...');
        if ($subjects->isNotEmpty() && $classrooms->isNotEmpty() && $teachers->isNotEmpty()) {
            foreach ($subjects->take(2) as $subject) {
                $quiz = Quiz::create([
                    'title' => "Quiz: {$subject->name} - Chapter 1",
                    'description' => "Test your knowledge of {$subject->name} fundamentals",
                    'subject_id' => $subject->id,
                    'classroom_id' => $classrooms->random()->id,
                    'teacher_id' => $teachers->random()->id,
                    'time_limit_min' => 30,
                    'starts_at' => Carbon::now()->addDays(1),
                    'ends_at' => Carbon::now()->addDays(7),
                    'max_attempts' => 2,
                    'passing_score' => 70.00,
                    'status' => 'published',
                ]);

                // Create quiz questions
                $questions = [
                    [
                        'type' => 'mcq',
                        'body' => 'What is the capital of Maldives?',
                        'options' => ['Malé', 'Addu', 'Fuvahmulah', 'Kulhudhuffushi'],
                        'answer' => [0], // Malé
                        'points' => 10,
                    ],
                    [
                        'type' => 'truefalse',
                        'body' => 'The Maldives consists of 1,192 coral islands.',
                        'options' => ['True', 'False'],
                        'answer' => [0], // True
                        'points' => 5,
                    ],
                    [
                        'type' => 'short',
                        'body' => 'Name the official language of Maldives.',
                        'answer' => ['Dhivehi'],
                        'points' => 15,
                    ],
                ];

                foreach ($questions as $index => $questionData) {
                    QuizQuestion::create([
                        'quiz_id' => $quiz->id,
                        'order' => $index + 1,
                        'type' => $questionData['type'],
                        'body' => $questionData['body'],
                        'options' => $questionData['options'] ?? null,
                        'answer' => $questionData['answer'],
                        'points' => $questionData['points'],
                    ]);
                }

                // Create quiz attempts
                foreach ($students->take(5) as $student) {
                    QuizAttempt::create([
                        'quiz_id' => $quiz->id,
                        'student_id' => $student->id,
                        'attempt_number' => 1,
                        'started_at' => Carbon::now()->subDays(rand(1, 3)),
                        'finished_at' => Carbon::now()->subDays(rand(1, 3))->addMinutes(rand(15, 25)),
                        'score' => rand(60, 95),
                        'points_earned' => rand(18, 28),
                        'total_points' => 30,
                        'answers' => [
                            '1' => [0], // First question answer
                            '2' => [0], // Second question answer
                            '3' => ['Dhivehi'], // Third question answer
                        ],
                        'status' => 'completed',
                        'time_spent_seconds' => rand(900, 1500), // 15-25 minutes
                    ]);
                }
            }
        }

        // Create absence notes
        $this->command->info('Creating absence notes...');
        foreach ($students->take(4) as $student) {
            $statuses = ['submitted', 'approved', 'approved', 'rejected'];
            AbsenceNote::create([
                'student_id' => $student->id,
                'created_by' => $student->user_id ?? User::role('guardian')->first()?->id ?? 1,
                'date' => Carbon::now()->subDays(rand(1, 7)),
                'reason' => 'Fever and flu symptoms',
                'type' => 'illness',
                'status' => $statuses[array_rand($statuses)],
                'reviewed_by' => $teachers->random()->user->id,
                'reviewed_at' => Carbon::now()->subDays(rand(0, 3)),
                'affects_attendance' => true,
            ]);
        }

        // Create invoices
        $this->command->info('Creating invoices...');
        foreach ($students->take(3) as $student) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'student_id' => $student->id,
                'issue_date' => Carbon::now()->subDays(rand(1, 30)),
                'due_date' => Carbon::now()->addDays(rand(15, 45)),
                'status' => ['draft', 'sent', 'paid'][array_rand(['draft', 'sent', 'paid'])],
                'subtotal' => 1500.00,
                'total_amount' => 1500.00,
                'created_by' => User::role('admin')->first()?->id ?? 1,
            ]);

            // Create invoice lines
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'fee_item_id' => FeeItem::where('type', 'tuition')->first()?->id,
                'description' => 'Monthly Tuition Fee - September 2024',
                'quantity' => 1,
                'unit_price' => 1500.00,
                'line_total' => 1500.00,
            ]);
        }

        $this->command->info('Feature pack demo data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . TeacherAbsence::count() . ' teacher absences');
        $this->command->info('- ' . SubstitutionRequest::count() . ' substitution requests');
        $this->command->info('- ' . Quiz::count() . ' quizzes');
        $this->command->info('- ' . QuizQuestion::count() . ' quiz questions');
        $this->command->info('- ' . QuizAttempt::count() . ' quiz attempts');
        $this->command->info('- ' . AbsenceNote::count() . ' absence notes');
        $this->command->info('- ' . FeeItem::count() . ' fee items');
        $this->command->info('- ' . Invoice::count() . ' invoices');
    }
}