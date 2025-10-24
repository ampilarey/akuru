<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Post, PostCategory, User};
use Carbon\Carbon;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $newsCategory = PostCategory::where('slug', 'news-announcements')->first();
        $academicCategory = PostCategory::where('slug', 'academic-updates')->first();
        $quranCategory = PostCategory::where('slug', 'quran-islamic-studies')->first();
        $eventsCategory = PostCategory::where('slug', 'events-activities')->first();
        $achievementsCategory = PostCategory::where('slug', 'student-achievements')->first();
        $communityCategory = PostCategory::where('slug', 'community-news')->first();
        $resourcesCategory = PostCategory::where('slug', 'educational-resources')->first();
        $generalCategory = PostCategory::where('slug', 'general')->first();

        // Get a user as author (create one if none exists)
        $author = User::first();
        if (!$author) {
            $author = User::create([
                'name' => 'Akuru Institute',
                'email' => 'admin@akuru.edu.mv',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        $posts = [
            [
                'post_category_id' => $newsCategory->id,
                'title' => 'Welcome to the New Academic Year 2024-2025',
                'slug' => 'welcome-new-academic-year-2024-2025',
                'summary' => 'We are excited to welcome all students and families to the new academic year. This year brings new opportunities for learning and growth in Islamic education.',
                'body' => '<p>Dear Students, Parents, and Community Members,</p>

<p>As we begin the new academic year 2024-2025, we are filled with excitement and gratitude for the opportunity to continue serving our community through quality Islamic education.</p>

<h3>What\'s New This Year</h3>
<ul>
<li>Enhanced Quran memorization program with new teaching methods</li>
<li>Expanded Arabic language courses for all levels</li>
<li>New Islamic studies curriculum incorporating contemporary issues</li>
<li>Improved facilities and learning resources</li>
<li>New extracurricular activities and clubs</li>
</ul>

<h3>Important Dates</h3>
<ul>
<li>First Day of Classes: September 15, 2024</li>
<li>Parent Orientation: September 10, 2024</li>
<li>Quran Competition: December 20, 2024</li>
<li>Annual Graduation: June 15, 2025</li>
</ul>

<p>We look forward to another successful year of learning and growth together. May Allah bless our efforts and guide us in the path of knowledge.</p>

<p>Best regards,<br>
The Akuru Institute Team</p>',
                'cover_image' => 'posts/academic-year-2024.jpg',
                'is_published' => true,
                'published_at' => Carbon::now()->subDays(5),
                'author_id' => $author->id,
                'is_featured' => true,
                'is_pinned' => true,
                'view_count' => 245,
                'like_count' => 18,
                'share_count' => 12,
                'tags' => ['academic year', 'welcome', 'announcement', 'education'],
                'meta_description' => 'Welcome to the new academic year 2024-2025 at Akuru Institute. Discover new programs, important dates, and exciting opportunities for Islamic education.',
                'meta_keywords' => 'academic year, Islamic education, Quran, Arabic, new year, Akuru Institute',
            ],
            [
                'post_category_id' => $quranCategory->id,
                'title' => 'The Importance of Tajweed in Quran Recitation',
                'slug' => 'importance-tajweed-quran-recitation',
                'summary' => 'Understanding the significance of proper Tajweed in Quran recitation and how it enhances our connection with the Holy Book.',
                'body' => '<p>Tajweed is the science of reciting the Quran correctly, following the rules of pronunciation and articulation that were revealed to Prophet Muhammad (PBUH). It is not just about beautiful recitation, but about preserving the original message and meaning of the Quran.</p>

<h3>Why Tajweed Matters</h3>
<p>Tajweed ensures that we recite the Quran exactly as it was revealed, preserving its divine message. When we recite with proper Tajweed, we:</p>
<ul>
<li>Show respect for the words of Allah</li>
<li>Preserve the original pronunciation</li>
<li>Enhance our understanding of the text</li>
<li>Experience the beauty and rhythm of the Quran</li>
<li>Follow the Sunnah of the Prophet (PBUH)</li>
</ul>

<h3>Basic Tajweed Rules</h3>
<p>Here are some fundamental Tajweed rules that every Muslim should know:</p>

<h4>1. Makharij (Articulation Points)</h4>
<p>Each Arabic letter has a specific point of articulation in the mouth or throat. Learning these points helps in correct pronunciation.</p>

<h4>2. Sifaat (Characteristics)</h4>
<p>Letters have specific characteristics like heaviness, lightness, and other qualities that affect their pronunciation.</p>

<h4>3. Rules of Noon and Meem</h4>
<p>Special rules apply when Noon (ن) or Meem (م) appears in certain positions.</p>

<h3>Learning Tajweed at Akuru Institute</h3>
<p>Our experienced teachers use proven methods to help students master Tajweed. We offer:</p>
<ul>
<li>Individual attention and correction</li>
<li>Progressive learning from basic to advanced</li>
<li>Regular practice sessions</li>
<li>Audio recordings for home practice</li>
<li>Certification upon completion</li>
</ul>

<p>Remember, learning Tajweed is a journey, not a destination. Start with the basics and gradually improve with consistent practice and guidance.</p>',
                'cover_image' => 'posts/tajweed-importance.jpg',
                'is_published' => true,
                'published_at' => Carbon::now()->subDays(3),
                'author_id' => $author->id,
                'is_featured' => true,
                'is_pinned' => false,
                'view_count' => 189,
                'like_count' => 25,
                'share_count' => 8,
                'tags' => ['tajweed', 'quran', 'recitation', 'islamic education', 'pronunciation'],
                'meta_description' => 'Learn about the importance of Tajweed in Quran recitation and how proper pronunciation enhances our connection with the Holy Book.',
                'meta_keywords' => 'tajweed, quran recitation, pronunciation, Islamic education, Arabic',
            ],
            [
                'post_category_id' => $achievementsCategory->id,
                'title' => 'Student Spotlight: Ahmed Completes Quran Memorization',
                'slug' => 'student-spotlight-ahmed-completes-quran-memorization',
                'summary' => 'Congratulations to Ahmed Ibrahim, who has successfully completed memorizing the entire Quran at the age of 16.',
                'body' => '<p>We are thrilled to announce that Ahmed Ibrahim, a dedicated student at Akuru Institute, has successfully completed memorizing the entire Quran at the remarkable age of 16.</p>

<h3>Ahmed\'s Journey</h3>
<p>Ahmed started his Quran memorization journey at Akuru Institute when he was 12 years old. His dedication, discipline, and love for the Quran have been truly inspiring to both his teachers and fellow students.</p>

<p>"I remember the first day I started memorizing Surah Al-Fatiha," Ahmed recalls. "It seemed like such a small step, but I knew that every great journey begins with a single step."</p>

<h3>Daily Routine</h3>
<p>Ahmed maintained a strict daily routine that included:</p>
<ul>
<li>Early morning recitation and memorization</li>
<li>Regular revision of previously memorized portions</li>
<li>Evening practice with his teacher</li>
<li>Weekend intensive sessions</li>
</ul>

<h3>Challenges and Perseverance</h3>
<p>Like any significant achievement, Ahmed faced challenges along the way. "There were days when I felt overwhelmed, especially with longer Surahs," he shares. "But my teachers and family always encouraged me to keep going."</p>

<h3>Advice for Other Students</h3>
<p>Ahmed offers this advice to other students pursuing Quran memorization:</p>
<ul>
<li>Consistency is key - even 30 minutes daily is better than hours once a week</li>
<li>Find a quiet, dedicated space for memorization</li>
<li>Don\'t rush - focus on quality over quantity</li>
<li>Seek help from teachers when you need it</li>
<li>Make dua for Allah\'s help and guidance</li>
</ul>

<h3>Recognition and Celebration</h3>
<p>Ahmed will be honored at our upcoming graduation ceremony, where he will receive his Hifz certificate and perform a special recitation for the community.</p>

<p>We are incredibly proud of Ahmed\'s achievement and grateful to his teachers, family, and the entire Akuru Institute community for their support.</p>

<p>May Allah bless Ahmed and all our students in their pursuit of Islamic knowledge.</p>',
                'cover_image' => 'posts/ahmed-hifz-achievement.jpg',
                'is_published' => true,
                'published_at' => Carbon::now()->subDays(1),
                'author_id' => $author->id,
                'is_featured' => true,
                'is_pinned' => false,
                'view_count' => 156,
                'like_count' => 32,
                'share_count' => 15,
                'tags' => ['student achievement', 'quran memorization', 'hifz', 'success story', 'inspiration'],
                'meta_description' => 'Celebrate Ahmed Ibrahim\'s achievement of completing Quran memorization at age 16. Read his inspiring journey and advice for other students.',
                'meta_keywords' => 'quran memorization, hifz, student achievement, success story, Islamic education',
            ],
            [
                'post_category_id' => $eventsCategory->id,
                'title' => 'Annual Quran Competition 2024 - Registration Now Open',
                'slug' => 'annual-quran-competition-2024-registration-open',
                'summary' => 'Join us for our prestigious annual Quran recitation competition. Registration is now open for students of all levels.',
                'body' => '<p>We are excited to announce that registration for our Annual Quran Competition 2024 is now open! This prestigious event showcases the beautiful recitation skills of our students and celebrates their dedication to learning the Holy Quran.</p>

<h3>Competition Details</h3>
<ul>
<li><strong>Date:</strong> December 20, 2024</li>
<li><strong>Time:</strong> 9:00 AM - 5:00 PM</li>
<li><strong>Location:</strong> Akuru Institute Main Hall</li>
<li><strong>Registration Fee:</strong> Free</li>
</ul>

<h3>Categories</h3>
<p>The competition is divided into three age categories:</p>

<h4>Kids Category (Ages 6-12)</h4>
<ul>
<li>Recite 3-5 short Surahs from memory</li>
<li>Basic Tajweed rules will be evaluated</li>
<li>Duration: 3-5 minutes</li>
</ul>

<h4>Youth Category (Ages 13-18)</h4>
<ul>
<li>Recite 5-8 Surahs from memory</li>
<li>Advanced Tajweed rules will be evaluated</li>
<li>Duration: 5-8 minutes</li>
</ul>

<h4>Adult Category (Ages 19+)</h4>
<ul>
<li>Recite 8-10 Surahs from memory</li>
<li>Mastery of Tajweed rules required</li>
<li>Duration: 8-10 minutes</li>
</ul>

<h3>Prizes and Recognition</h3>
<p>Winners in each category will receive:</p>
<ul>
<li>First Place: Trophy + Certificate + MVR 1,000</li>
<li>Second Place: Medal + Certificate + MVR 500</li>
<li>Third Place: Medal + Certificate + MVR 250</li>
<li>All participants receive participation certificates</li>
</ul>

<h3>How to Register</h3>
<p>Registration is simple and free:</p>
<ol>
<li>Visit our main office or call +960 797 2434</li>
<li>Provide student name, age, and contact information</li>
<li>Specify which Surahs you will be reciting</li>
<li>Receive confirmation and competition schedule</li>
</ol>

<h3>Judges</h3>
<p>Our panel of expert judges includes:</p>
<ul>
<li>Sheikh Ahmed Ibrahim - Chief Judge</li>
<li>Dr. Aisha Mohamed - Tajweed Specialist</li>
<li>Qari Hassan Ali - Quran Recitation Expert</li>
</ul>

<h3>Important Dates</h3>
<ul>
<li>Registration Deadline: December 10, 2024</li>
<li>Practice Sessions: December 15-19, 2024</li>
<li>Competition Day: December 20, 2024</li>
<li>Award Ceremony: December 20, 2024 (5:00 PM)</li>
</ul>

<p>Don\'t miss this opportunity to showcase your Quran recitation skills and be part of our vibrant Islamic community. Register today!</p>',
                'cover_image' => 'posts/quran-competition-2024.jpg',
                'is_published' => true,
                'published_at' => Carbon::now()->subDays(2),
                'author_id' => $author->id,
                'is_featured' => false,
                'is_pinned' => false,
                'view_count' => 98,
                'like_count' => 12,
                'share_count' => 6,
                'tags' => ['quran competition', 'event', 'registration', 'recitation', 'competition'],
                'meta_description' => 'Register for the Annual Quran Competition 2024 at Akuru Institute. Open to all ages with prizes and recognition for participants.',
                'meta_keywords' => 'quran competition, recitation, event, registration, Islamic competition',
            ],
            [
                'post_category_id' => $academicCategory->id,
                'title' => 'New Arabic Language Program Launched',
                'slug' => 'new-arabic-language-program-launched',
                'summary' => 'We are excited to announce the launch of our comprehensive Arabic language program designed for all skill levels.',
                'body' => '<p>Akuru Institute is proud to announce the launch of our comprehensive Arabic language program, designed to help students of all ages and skill levels master this beautiful and important language.</p>

<h3>Program Overview</h3>
<p>Our new Arabic language program offers structured learning paths for:</p>
<ul>
<li>Complete beginners with no prior Arabic knowledge</li>
<li>Students with basic Arabic skills looking to improve</li>
<li>Advanced learners seeking fluency</li>
<li>Adults wanting to learn Arabic for religious or professional purposes</li>
</ul>

<h3>Curriculum Highlights</h3>
<p>The program covers all essential aspects of Arabic language learning:</p>

<h4>1. Reading and Writing</h4>
<ul>
<li>Arabic alphabet and letter recognition</li>
<li>Proper letter formation and handwriting</li>
<li>Reading comprehension exercises</li>
<li>Vocabulary building through reading</li>
</ul>

<h4>2. Speaking and Listening</h4>
<ul>
<li>Pronunciation and phonetics</li>
<li>Conversational Arabic</li>
<li>Listening comprehension</li>
<li>Speaking practice with native speakers</li>
</ul>

<h4>3. Grammar and Structure</h4>
<ul>
<li>Basic to advanced grammar rules</li>
<li>Sentence structure and syntax</li>
<li>Verb conjugations and tenses</li>
<li>Noun and adjective agreements</li>
</ul>

<h4>4. Cultural Context</h4>
<ul>
<li>Arabic culture and traditions</li>
<li>Islamic terminology and phrases</li>
<li>Quranic Arabic vocabulary</li>
<li>Contemporary Arabic usage</li>
</ul>

<h3>Teaching Methods</h3>
<p>Our experienced teachers use modern, interactive teaching methods:</p>
<ul>
<li>Small class sizes for individual attention</li>
<li>Multimedia learning resources</li>
<li>Interactive exercises and games</li>
<li>Regular assessments and progress tracking</li>
<li>Cultural immersion activities</li>
</ul>

<h3>Course Levels</h3>
<p>Students are placed in appropriate levels based on their current skills:</p>

<h4>Beginner Level (A1-A2)</h4>
<p>For students with no prior Arabic knowledge. Covers basic alphabet, simple vocabulary, and fundamental grammar.</p>

<h4>Intermediate Level (B1-B2)</h4>
<p>For students with basic Arabic skills. Focuses on conversation, reading comprehension, and intermediate grammar.</p>

<h4>Advanced Level (C1-C2)</h4>
<p>For students seeking fluency. Covers complex grammar, literature, and professional Arabic usage.</p>

<h3>Schedule and Fees</h3>
<p>Classes are available in flexible schedules:</p>
<ul>
<li>Weekday Evening Classes: 6:00 PM - 8:00 PM</li>
<li>Weekend Morning Classes: 9:00 AM - 11:00 AM</li>
<li>Weekend Afternoon Classes: 2:00 PM - 4:00 PM</li>
<li>Monthly Fee: MVR 300 (includes materials)</li>
</ul>

<h3>Certification</h3>
<p>Students who complete each level will receive:</p>
<ul>
<li>Official completion certificate</li>
<li>Detailed progress report</li>
<li>Recommendation for next level</li>
<li>Portfolio of completed work</li>
</ul>

<h3>How to Enroll</h3>
<p>Enrollment is now open for the upcoming semester:</p>
<ol>
<li>Visit our main office or call +960 797 2434</li>
<li>Take a placement test to determine your level</li>
<li>Choose your preferred schedule</li>
<li>Complete registration and payment</li>
<li>Start your Arabic learning journey!</li>
</ul>

<p>Don\'t miss this opportunity to learn one of the world\'s most important languages. Arabic opens doors to understanding the Quran, Islamic culture, and connects you with millions of Arabic speakers worldwide.</p>',
                'cover_image' => 'posts/arabic-program-launch.jpg',
                'is_published' => true,
                'published_at' => Carbon::now()->subDays(4),
                'author_id' => $author->id,
                'is_featured' => false,
                'is_pinned' => false,
                'view_count' => 134,
                'like_count' => 19,
                'share_count' => 9,
                'tags' => ['arabic language', 'new program', 'language learning', 'education', 'curriculum'],
                'meta_description' => 'Discover our new comprehensive Arabic language program at Akuru Institute. Learn Arabic from beginner to advanced levels with experienced teachers.',
                'meta_keywords' => 'Arabic language, language learning, education, curriculum, Islamic education',
            ],
            [
                'post_category_id' => $communityCategory->id,
                'title' => 'Community Iftar Gathering - A Night of Unity and Blessings',
                'slug' => 'community-iftar-gathering-night-unity-blessings',
                'summary' => 'Our annual community Iftar gathering brought together over 200 families for a beautiful evening of unity, prayer, and fellowship.',
                'body' => '<p>Last Friday evening, Akuru Institute hosted its annual community Iftar gathering, bringing together over 200 families from our local community for a beautiful evening of unity, prayer, and fellowship.</p>

<h3>A Night to Remember</h3>
<p>The event, held in our main hall, was a resounding success with families from diverse backgrounds coming together to break their fast and share in the blessings of Ramadan.</p>

<p>"It was truly heartwarming to see so many families gathered together," said Imam Abdullah, who led the Maghrib prayer. "This is what community is all about - coming together in faith and fellowship."</p>

<h3>Event Highlights</h3>
<p>The evening featured several special moments:</p>

<h4>Maghrib Prayer</h4>
<p>Over 200 community members joined together for the Maghrib prayer, creating a powerful sense of unity and devotion.</p>

<h4>Iftar Meal</h4>
<p>A delicious traditional Iftar meal was served, featuring:</p>
<ul>
<li>Fresh dates and water to break the fast</li>
<li>Traditional Maldivian dishes</li>
<li>International cuisine representing our diverse community</li>
<li>Fresh fruits and desserts</li>
</ul>

<h4>Community Activities</h4>
<p>After Iftar, families enjoyed various activities:</p>
<ul>
<li>Children\'s games and activities</li>
<li>Quran recitation by students</li>
<li>Community discussions and networking</li>
<li>Photography sessions for families</li>
</ul>

<h3>Student Performances</h3>
<p>Several of our students showcased their talents:</p>
<ul>
<li>Quran recitation by Ahmed Ibrahim (Grade 10)</li>
<li>Nasheed performance by the student choir</li>
<li>Arabic poetry recitation by Fatima Hassan (Grade 8)</li>
<li>Islamic quiz competition for children</li>
</ul>

<h3>Community Impact</h3>
<p>The event had a profound impact on our community:</p>
<ul>
<li>Strengthened bonds between families</li>
<li>Created opportunities for new friendships</li>
<li>Fostered a sense of belonging and unity</li>
<li>Provided a platform for students to showcase their skills</li>
</ul>

<h3>Volunteer Appreciation</h3>
<p>We extend our heartfelt gratitude to all the volunteers who made this event possible:</p>
<ul>
<li>Event coordination team</li>
<li>Food preparation volunteers</li>
<li>Setup and cleanup crew</li>
<li>Student helpers and guides</li>
</ul>

<h3>Looking Forward</h3>
<p>Based on the success of this year\'s event, we are already planning for next year\'s community Iftar gathering. We hope to:</p>
<ul>
<li>Expand the event to accommodate more families</li>
<li>Add more cultural performances</li>
<li>Include educational workshops</li>
<li>Create more opportunities for community engagement</li>
</ul>

<h3>Community Feedback</h3>
<p>Here\'s what some community members had to say:</p>

<blockquote>
<p>"This was such a beautiful event. It really brought our community together and reminded us of what\'s important - faith, family, and fellowship." - Aisha Mohamed, Parent</p>
</blockquote>

<blockquote>
<p>"I loved seeing all the children playing together and the students performing. It shows how strong our community is." - Hassan Ali, Community Member</p>
</blockquote>

<p>We are grateful to Allah for blessing us with such a wonderful community and the opportunity to serve them through events like this.</p>

<p>May Allah continue to bless our community and guide us in serving Him and our fellow Muslims.</p>',
                'cover_image' => 'posts/community-iftar-2024.jpg',
                'is_published' => true,
                'published_at' => Carbon::now()->subDays(6),
                'author_id' => $author->id,
                'is_featured' => false,
                'is_pinned' => false,
                'view_count' => 167,
                'like_count' => 28,
                'share_count' => 11,
                'tags' => ['community', 'iftar', 'ramadan', 'unity', 'fellowship', 'event'],
                'meta_description' => 'Read about our successful community Iftar gathering that brought together over 200 families for a night of unity, prayer, and fellowship.',
                'meta_keywords' => 'community iftar, ramadan, unity, fellowship, Islamic community, event',
            ],
        ];

        foreach ($posts as $post) {
            Post::create($post);
        }
    }
}