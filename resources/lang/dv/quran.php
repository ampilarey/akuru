<?php

/**
 * The Qur'an component's codes, named (C19 slice CT5a, STATUS §5or): an
 * assignment's type, the status of an assignment, a recitation or a
 * milestone, a mistake and its severity, a halaqa sheet's results and
 * attendance, a milestone's type. One book, so the teacher's screens and the
 * learner's name a code the same way. Each key is a family prefix and the
 * enum's value.
 */
return [

    // Assignments, recitations and milestones (slice CT5a, STATUS §5or).
    'all' => 'ހުރިހާ',
    'assignment_type_letter_haraka_practice' => 'އަކުރާއި ހަރަކާތުގެ ތަމްރީނު',
    'assignment_type_new_memorization' => 'އާ ހިފްޒު',
    'assignment_type_revision' => 'މުރާޖަޢާ',
    'assignment_type_correction_repeat' => 'ރަނގަޅުކުރުމަށް ތަކުރާރު',
    'assignment_type_assessment' => 'އިމްތިޙާން',
    'status_assigned' => 'ހަވާލުކުރެވިފައި',
    'status_in_progress' => 'ކުރިއަށް ދަނީ',
    'status_submitted' => 'ހުށަހަޅާފައި',
    'status_needs_repeat' => 'އަލުން ކުރަން ޖެހޭ',
    'status_passed' => 'ފާސް',
    'status_failed' => 'ފެއިލް',
    'status_cancelled' => 'ކެންސަލްކުރެވިފައި',
    'status_ai_checked' => 'އޭއައި ބަލާފައި',
    'status_teacher_reviewed' => 'ޓީޗަރު ބަލާފައި',
    'status_supervisor_reviewed' => 'ސުޕަވައިޒަރު ބަލާފައި',
    'status_dean_reviewed' => 'ޑީން ބަލާފައި',
    'status_ai_processed_later' => 'އޭއައި ފަހުން ބަލާނެ',
    'status_pending' => 'މަޑުކުރަނީ',
    'status_approved' => 'ފާސްކުރެވިފައި',
    'status_rejected' => 'ރިޖެކްޓްކުރެވިފައި',
    'milestone_type_surah_completed' => 'ސޫރަތް ނިންމާފައި',
    'milestone_type_juz_completed' => 'ޖުޒު ނިންމާފައި',
    'milestone_type_page_completed' => 'ސަފްޙާ ނިންމާފައި',
    'milestone_type_quran_completed' => 'ޤުރުއާން ނިންމާފައި',
    'milestone_type_custom' => 'ޚާއްޞަ',
    'mistake_wrong_letter' => 'ގޯސް އަކުރު',
    'mistake_wrong_haraka' => 'ގޯސް ހަރަކާތް',
    'mistake_missed_word' => 'ދޫކޮށްލި ބަސް',
    'mistake_added_word' => 'އިތުރުކުރި ބަސް',
    'mistake_repeated_word' => 'ތަކުރާރުކުރި ބަސް',
    'mistake_wrong_word' => 'ގޯސް ބަސް',
    'mistake_pronunciation_issue' => 'ކިޔުމުގެ މައްސަލަ',
    'mistake_waqf_issue' => 'ވަޤްފުގެ މައްސަލަ',
    'mistake_madd_issue' => 'މައްދުގެ މައްސަލަ',
    'mistake_ghunnah_issue' => 'ޣުންނާގެ މައްސަލަ',
    'mistake_tajweed_issue' => 'ތަޖްވީދުގެ މައްސަލަ',
    'mistake_other' => 'އެހެނިހެން',
    'severity_minor' => 'ކުޑަ',
    'severity_medium' => 'މެދު',
    'severity_major' => 'ބޮޑު',
    'result_pass' => 'ފާސް',
    'result_pass_with_notes' => 'ނޯޓުތަކާއެކު ފާސް',
    'result_repeat' => 'ތަކުރާރު',
    'result_not_prepared' => 'ތައްޔާރުނުވޭ',
    'result_not_done' => 'ނުކުރޭ',
    'overall_excellent' => 'ވަރަށް ރަނގަޅު',
    'overall_good' => 'ރަނގަޅު',
    'overall_needs_revision' => 'މުރާޖަޢާ ކުރަން ޖެހޭ',
    'overall_weak' => 'ބަލި',
    'overall_absent' => 'ހާޒިރުނުވި',
    'attendance_present' => 'ހާޒިރު',
    'attendance_late' => 'ލަސް',
    'attendance_absent' => 'ހާޒިރުނުވި',
    'attendance_excused' => 'ހުއްދަ ލިބިފައި',
];
