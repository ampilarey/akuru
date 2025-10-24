<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\{
    PostController,
    CourseController,
    AdmissionController,
    GalleryController,
    EventController,
    PageController,
    ContactController,
    SitemapController
};

// Professional homepage with real content
Route::get("/", function() {
    return view('public.home', [
        'title' => 'Welcome to Akuru Institute',
        'description' => 'Learn Quran, Arabic, and Islamic Studies in the Maldives',
        'heroBanners' => collect([
            (object)['title' => 'Learn Quran with Expert Teachers', 'subtitle' => 'Master the Holy Quran with our qualified instructors', 'image' => 'hero-1.jpg'],
            (object)['title' => 'Arabic Language Courses', 'subtitle' => 'Learn Arabic from beginner to advanced levels', 'image' => 'hero-2.jpg'],
            (object)['title' => 'Islamic Studies Program', 'subtitle' => 'Comprehensive Islamic education for all ages', 'image' => 'hero-3.jpg']
        ]),
        'courses' => collect([
            (object)['name' => 'Quran Memorization (Hifz)', 'description' => 'Complete Quran memorization program', 'duration' => '2-3 years', 'image' => 'hifz.jpg'],
            (object)['name' => 'Arabic Language', 'description' => 'Learn Arabic from basics to fluency', 'duration' => '1-2 years', 'image' => 'arabic.jpg'],
            (object)['name' => 'Islamic Studies', 'description' => 'Comprehensive Islamic education', 'duration' => '1 year', 'image' => 'islamic.jpg']
        ]),
        'posts' => collect([
            (object)['title' => 'New Academic Year Starts', 'content' => 'Registration is now open for the new academic year...', 'date' => '2024-01-15'],
            (object)['title' => 'Quran Competition Results', 'content' => 'Congratulations to all participants...', 'date' => '2024-01-10']
        ]),
        'events' => collect([
            (object)['title' => 'Open House Day', 'date' => '2024-02-15', 'location' => 'Main Campus'],
            (object)['title' => 'Quran Recitation Competition', 'date' => '2024-03-01', 'location' => 'Auditorium']
        ])
    ]);
});

Route::get("/en", function() {
    app()->setLocale("en");
    return view('public.home', [
        'title' => 'Welcome to Akuru Institute',
        'description' => 'Learn Quran, Arabic, and Islamic Studies in the Maldives',
        'heroBanners' => collect([
            (object)['title' => 'Learn Quran with Expert Teachers', 'subtitle' => 'Master the Holy Quran with our qualified instructors', 'image' => 'hero-1.jpg'],
            (object)['title' => 'Arabic Language Courses', 'subtitle' => 'Learn Arabic from beginner to advanced levels', 'image' => 'hero-2.jpg'],
            (object)['title' => 'Islamic Studies Program', 'subtitle' => 'Comprehensive Islamic education for all ages', 'image' => 'hero-3.jpg']
        ]),
        'courses' => collect([
            (object)['name' => 'Quran Memorization (Hifz)', 'description' => 'Complete Quran memorization program', 'duration' => '2-3 years', 'image' => 'hifz.jpg'],
            (object)['name' => 'Arabic Language', 'description' => 'Learn Arabic from basics to fluency', 'duration' => '1-2 years', 'image' => 'arabic.jpg'],
            (object)['name' => 'Islamic Studies', 'description' => 'Comprehensive Islamic education', 'duration' => '1 year', 'image' => 'islamic.jpg']
        ]),
        'posts' => collect([
            (object)['title' => 'New Academic Year Starts', 'content' => 'Registration is now open for the new academic year...', 'date' => '2024-01-15'],
            (object)['title' => 'Quran Competition Results', 'content' => 'Congratulations to all participants...', 'date' => '2024-01-10']
        ]),
        'events' => collect([
            (object)['title' => 'Open House Day', 'date' => '2024-02-15', 'location' => 'Main Campus'],
            (object)['title' => 'Quran Recitation Competition', 'date' => '2024-03-01', 'location' => 'Auditorium']
        ])
    ]);
});

Route::get("/ar", function() {
    app()->setLocale("ar");
    return view('public.home', [
        'title' => 'مرحباً بكم في معهد أكورو',
        'description' => 'تعلم القرآن الكريم واللغة العربية والدراسات الإسلامية في المالديف',
        'heroBanners' => collect([
            (object)['title' => 'تعلم القرآن مع المعلمين الخبراء', 'subtitle' => 'أتقن القرآن الكريم مع معلمينا المؤهلين', 'image' => 'hero-1.jpg'],
            (object)['title' => 'دورات اللغة العربية', 'subtitle' => 'تعلم العربية من المبتدئ إلى المتقدم', 'image' => 'hero-2.jpg'],
            (object)['title' => 'برنامج الدراسات الإسلامية', 'subtitle' => 'التعليم الإسلامي الشامل لجميع الأعمار', 'image' => 'hero-3.jpg']
        ]),
        'courses' => collect([
            (object)['name' => 'حفظ القرآن الكريم', 'description' => 'برنامج حفظ القرآن الكريم الكامل', 'duration' => '2-3 سنوات', 'image' => 'hifz.jpg'],
            (object)['name' => 'اللغة العربية', 'description' => 'تعلم العربية من الأساسيات إلى الطلاقة', 'duration' => '1-2 سنة', 'image' => 'arabic.jpg'],
            (object)['name' => 'الدراسات الإسلامية', 'description' => 'التعليم الإسلامي الشامل', 'duration' => 'سنة واحدة', 'image' => 'islamic.jpg']
        ]),
        'posts' => collect([
            (object)['title' => 'بداية العام الدراسي الجديد', 'content' => 'التسجيل مفتوح الآن للعام الدراسي الجديد...', 'date' => '2024-01-15'],
            (object)['title' => 'نتائج مسابقة القرآن', 'content' => 'تهانينا لجميع المشاركين...', 'date' => '2024-01-10']
        ]),
        'events' => collect([
            (object)['title' => 'يوم الباب المفتوح', 'date' => '2024-02-15', 'location' => 'الحرم الرئيسي'],
            (object)['title' => 'مسابقة تلاوة القرآن', 'date' => '2024-03-01', 'location' => 'القاعة الكبرى']
        ])
    ]);
});

Route::get("/dv", function() {
    app()->setLocale("dv");
    return view('public.home', [
        'title' => 'އެކުރު އިންސްޓިޓިއުޓުގައި ރައްކާ',
        'description' => 'ދިވެހިރާއްޖެ ގައި ޤުރުން، ޢަރަބި ބަހުން އަދި އިސްލާމީ ދަސްކަމުގެ ދެނެވިފައި',
        'heroBanners' => collect([
            (object)['title' => 'ހެކުރު އުސްތާދުގެ އަދި ޤުރުން ދެނެވިފައި', 'subtitle' => 'ހެކުރު އުސްތާދުގެ އަދި ޤުރުން ހުންނަ ހެކުރު އުސްތާދުގެ އަދި', 'image' => 'hero-1.jpg'],
            (object)['title' => 'ޢަރަބި ބަހުން ދެނެވިފައި', 'subtitle' => 'ޢަރަބި ބަހުން ދެނެވިފައި ހުންނަ ހެކުރު އުސްތާދުގެ އަދި', 'image' => 'hero-2.jpg'],
            (object)['title' => 'އިސްލާމީ ދަސްކަމުގެ ހުންނަ', 'subtitle' => 'އިސްލާމީ ދަސްކަމުގެ ހުންނަ ހެކުރު އުސްތާދުގެ އަދި', 'image' => 'hero-3.jpg']
        ]),
        'courses' => collect([
            (object)['name' => 'ޤުރުން ހުންނަ (ހިފްސް)', 'description' => 'ޤުރުން ހުންނަ ހުންނަ ހެކުރު އުސްތާދުގެ އަދި', 'duration' => '2-3 އަހަރު', 'image' => 'hifz.jpg'],
            (object)['name' => 'ޢަރަބި ބަހުން', 'description' => 'ޢަރަބި ބަހުން ދެނެވިފައި ހުންނަ ހެކުރު އުސްތާދުގެ އަދި', 'duration' => '1-2 އަހަރު', 'image' => 'arabic.jpg'],
            (object)['name' => 'އިސްލާމީ ދަސްކަމުގެ', 'description' => 'އިސްލާމީ ދަސްކަމުގެ ހުންނަ ހެކުރު އުސްތާދުގެ އަދި', 'duration' => '1 އަހަރު', 'image' => 'islamic.jpg']
        ]),
        'posts' => collect([
            (object)['title' => 'އަހަރު ދެނެވިފައި ހުންނަ', 'content' => 'އަހަރު ދެނެވިފައި ހުންނަ ހެކުރު އުސްތާދުގެ އަދި...', 'date' => '2024-01-15'],
            (object)['title' => 'ޤުރުން ހުންނަ ހުންނަ', 'content' => 'ޤުރުން ހުންނަ ހުންނަ ހެކުރު އުސްތާދުގެ އަދި...', 'date' => '2024-01-10']
        ]),
        'events' => collect([
            (object)['title' => 'އަހަރު ދެނެވިފައި ހުންނަ', 'date' => '2024-02-15', 'location' => 'އަހަރު ދެނެވިފައި ހުންނަ'],
            (object)['title' => 'ޤުރުން ހުންނަ ހުންނަ', 'date' => '2024-03-01', 'location' => 'ޤުރުން ހުންނަ ހުންނަ']
        ])
    ]);
});

// Other routes
Route::get("about", [PageController::class, "show"])->name("public.about");
Route::get("courses", [CourseController::class, "index"])->name("public.courses.index");
Route::get("courses/{course}", [CourseController::class, "show"])->name("public.courses.show");
Route::get("news", [PostController::class, "index"])->name("public.news.index");
Route::get("news/{post}", [PostController::class, "show"])->name("public.news.show");
Route::get("events", [EventController::class, "index"])->name("public.events.index");
Route::get("events/{event}", [EventController::class, "show"])->name("public.events.show");
Route::get("gallery", function() {
    try {
        $albums = \App\Models\GalleryAlbum::published()->public()->with(['items' => function($q) {
            $q->public()->ordered()->limit(4);
        }])->get();
        return view('public.gallery.index', compact('albums'));
    } catch (\Exception $e) {
        return response('Gallery error: ' . $e->getMessage(), 500);
    }
})->name("public.gallery.index");
Route::get("gallery/{gallery}", [GalleryController::class, "show"])->name("public.gallery.show");
Route::get("admissions", [AdmissionController::class, "create"])->name("public.admissions.create");
Route::post("admissions", [AdmissionController::class, "store"])->name("public.admissions.store");
Route::get("admissions/thanks", [AdmissionController::class, "thanks"])->name("public.admissions.thanks");
Route::get("contact", [ContactController::class, "create"])->name("public.contact.create");
Route::post("contact", [ContactController::class, "store"])->name("public.contact.store");
Route::get("page/{slug}", [PageController::class, "show"])->name("public.page.show");

// SEO routes
Route::get("sitemap.xml", [SitemapController::class, "index"])->name("public.sitemap");
Route::get("robots.txt", function() {
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= "Disallow: /admin/\n";
    $content .= "Disallow: /login\n";
    $content .= "Disallow: /register\n";
    $content .= "Disallow: /password/\n";
    $content .= "Disallow: /email/\n";
    $content .= "Disallow: /dashboard\n";
    $content .= "Disallow: /students/\n";
    $content .= "Disallow: /teachers/\n";
    $content .= "Disallow: /quran-progress/\n";
    $content .= "Disallow: /announcements/\n";
    $content .= "Disallow: /e-learning/\n";
    $content .= "Disallow: /substitutions/\n";
    $content .= "Disallow: /requests/\n";
    $content .= "Disallow: /absences/\n";
    $content .= "Disallow: /otp-login\n";
    $content .= "Disallow: /otp-verify\n";
    $content .= "Disallow: /otp-password/\n";
    $content .= "Disallow: /test\n";
    $content .= "Disallow: /lang-test\n\n";
    $content .= "Sitemap: " . url("/sitemap.xml") . "\n";
    
    return response($content, 200, ["Content-Type" => "text/plain"]);
})->name("public.robots");