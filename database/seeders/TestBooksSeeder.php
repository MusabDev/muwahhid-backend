<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\DarsSeries;
use App\Services\BookImporter;
use App\Services\TranscriptImporter;
use Illuminate\Database\Seeder;

class TestBooksSeeder extends Seeder
{
    /**
     * Scholars used by the test books, keyed by slug.
     *
     * @var array<string, array{name_ar: string, name_bn: string, name_translit: string, death_year_ah: ?int}>
     */
    private const AUTHORS = [
        'muhammad-ibn-abdul-wahhab' => ['name_ar' => 'محمد بن عبد الوهاب', 'name_bn' => 'মুহাম্মাদ ইবনু আব্দিল ওয়াহহাব', 'name_translit' => 'Muhammad ibn Abdil Wahhab', 'death_year_ah' => 1206],
        'ibn-taymiyyah' => ['name_ar' => 'ابن تيمية', 'name_bn' => 'শাইখুল ইসলাম ইবনু তাইমিয়্যাহ', 'name_translit' => 'Ibn Taymiyyah', 'death_year_ah' => 728],
        'ibn-al-qayyim' => ['name_ar' => 'ابن قيم الجوزية', 'name_bn' => 'ইবনুল কাইয়্যিম আল-জাওযিয়্যাহ', 'name_translit' => 'Ibn al-Qayyim al-Jawziyyah', 'death_year_ah' => 751],
        'an-nawawi' => ['name_ar' => 'يحيى بن شرف النووي', 'name_bn' => 'ইমাম নববী', 'name_translit' => 'Imam an-Nawawi', 'death_year_ah' => 676],
        'ibn-rajab' => ['name_ar' => 'ابن رجب الحنبلي', 'name_bn' => 'ইবনু রজব আল-হাম্বলী', 'name_translit' => 'Ibn Rajab al-Hanbali', 'death_year_ah' => 795],
        'as-sadi' => ['name_ar' => 'عبد الرحمن بن ناصر السعدي', 'name_bn' => 'আব্দুর রহমান ইবনু নাসির আস-সা\'দী', 'name_translit' => 'Abdur-Rahman as-Sadi', 'death_year_ah' => 1376],
        'ibn-uthaymin' => ['name_ar' => 'محمد بن صالح العثيمين', 'name_bn' => 'মুহাম্মাদ ইবনু সালিহ আল-উসাইমীন', 'name_translit' => 'Muhammad ibn Salih al-Uthaymin', 'death_year_ah' => 1421],
        'salih-al-fawzan' => ['name_ar' => 'صالح بن فوزان الفوزان', 'name_bn' => 'সালিহ ইবনু ফাওযান আল-ফাওযান', 'name_translit' => 'Salih al-Fawzan', 'death_year_ah' => null],
        'ibn-baz' => ['name_ar' => 'عبد العزيز بن باز', 'name_bn' => 'আব্দুল আযীয ইবনু বায', 'name_translit' => 'Abdul Aziz ibn Baz', 'death_year_ah' => 1420],
        'al-albani' => ['name_ar' => 'محمد ناصر الدين الألباني', 'name_bn' => 'নাসিরুদ্দীন আল-আলবানী', 'name_translit' => 'Nasir ad-Din al-Albani', 'death_year_ah' => 1420],
        'ibn-kathir' => ['name_ar' => 'إسماعيل بن كثير', 'name_bn' => 'ইমাম ইবনু কাসীর', 'name_translit' => 'Ibn Kathir', 'death_year_ah' => 774],
        'adh-dhahabi' => ['name_ar' => 'شمس الدين الذهبي', 'name_bn' => 'ইমাম যাহাবী', 'name_translit' => 'Imam adh-Dhahabi', 'death_year_ah' => 748],
        'abdur-razzaq-al-badr' => ['name_ar' => 'عبد الرزاق البدر', 'name_bn' => 'আব্দুর রাযযাক আল-বাদর', 'name_translit' => 'Abdur-Razzaq al-Badr', 'death_year_ah' => null],
        'salih-al-usaymi' => ['name_ar' => 'صالح بن عبد الله العصيمي', 'name_bn' => 'সালিহ আল-উসাইমী', 'name_translit' => 'Salih al-Usaymi', 'death_year_ah' => null],
        'rabi-al-madkhali' => ['name_ar' => 'ربيع بن هادي المدخلي', 'name_bn' => 'রাবী ইবনু হাদী আল-মাদখালী', 'name_translit' => "Rabi' al-Madkhali", 'death_year_ah' => 1447],
    ];

    /**
     * Test dars series: [slug, title_ar, title_bn, title_translit, teacher slug, book slug].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ?string}>
     */
    private const SERIES = [
        ['dars-kitab-at-tawhid', 'شرح كتاب التوحيد', 'কিতাবুত তাওহীদের দারস', 'Sharh Kitab at-Tawhid', 'salih-al-fawzan', 'kitab-at-tawhid'],
        ['dars-al-qawaid-al-arba', 'شرح القواعد الأربع', 'চারটি মূলনীতির দারস', "Sharh al-Qawa'id al-Arba'", 'salih-al-usaymi', 'al-qawaid-al-arba'],
        ['dars-kashf-ash-shubuhat', 'شرح كشف الشبهات', 'সংশয় নিরসনের দারস', 'Sharh Kashf ash-Shubuhat', 'ibn-uthaymin', 'kashf-ash-shubuhat'],
        ['dars-nawaqid-al-islam', 'شرح نواقض الإسلام', 'ইসলাম বিনষ্টকারী বিষয়সমূহের দারস', 'Sharh Nawaqid al-Islam', 'ibn-baz', 'nawaqid-al-islam'],
        ['dars-al-wasitiyyah', 'شرح العقيدة الواسطية', 'আল-ওয়াসিতিয়্যাহর দারস', 'Sharh al-Wasitiyyah', 'ibn-uthaymin', 'al-aqidah-al-wasitiyyah'],
        ['dars-al-arbain', 'شرح الأربعين النووية', 'চল্লিশ হাদীসের দারস', "Sharh al-Arba'in", 'abdur-razzaq-al-badr', 'al-arbain-an-nawawiyyah'],
        ['dars-riyad-as-salihin', 'شرح رياض الصالحين', 'রিয়াদুস সালিহীনের দারস', 'Sharh Riyad as-Salihin', 'ibn-uthaymin', 'riyad-as-salihin'],
        ['dars-ad-da-wa-ad-dawa', 'دروس الداء والدواء', 'রোগ ও প্রতিকারের দারস', "Durus ad-Da' wa ad-Dawa'", 'abdur-razzaq-al-badr', 'ad-da-wa-ad-dawa'],
        ['dars-zad-al-maad', 'دروس زاد المعاد', 'যাদুল মা\'আদের দারস', "Durus Zad al-Ma'ad", 'salih-al-usaymi', 'zad-al-maad'],
        ['dars-tafsir-ibn-kathir', 'دروس في تفسير ابن كثير', 'তাফসীর ইবনু কাসীরের দারস', 'Durus Tafsir Ibn Kathir', 'ibn-uthaymin', null],
        ['dars-usul-as-sunnah', 'شرح أصول السنة', 'উসূলুস সুন্নাহর দারস', 'Sharh Usul as-Sunnah', 'rabi-al-madkhali', null],
    ];

    /**
     * Test books: [slug, title_ar, title_bn, title_translit, author slug, explainer slug (for a sharh)].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ?string}>
     */
    private const BOOKS = [
        ['kitab-at-tawhid', 'كتاب التوحيد', 'কিতাবুত তাওহীদ', 'Kitab at-Tawhid', 'muhammad-ibn-abdul-wahhab', null],
        ['al-qawaid-al-arba', 'القواعد الأربع', 'চারটি মূলনীতি', "Al-Qawa'id al-Arba'", 'muhammad-ibn-abdul-wahhab', null],
        ['kashf-ash-shubuhat', 'كشف الشبهات', 'সংশয় নিরসন', 'Kashf ash-Shubuhat', 'muhammad-ibn-abdul-wahhab', null],
        ['nawaqid-al-islam', 'نواقض الإسلام', 'ইসলাম বিনষ্টকারী বিষয়সমূহ', 'Nawaqid al-Islam', 'muhammad-ibn-abdul-wahhab', null],
        ['al-aqidah-al-wasitiyyah', 'العقيدة الواسطية', 'আল-আকীদাহ আল-ওয়াসিতিয়্যাহ', 'Al-Aqidah al-Wasitiyyah', 'ibn-taymiyyah', null],
        ['al-arbain-an-nawawiyyah', 'الأربعون النووية', 'চল্লিশ হাদীস', "Al-Arba'in an-Nawawiyyah", 'an-nawawi', null],
        ['riyad-as-salihin', 'رياض الصالحين', 'রিয়াদুস সালিহীন', 'Riyad as-Salihin', 'an-nawawi', null],
        ['ad-da-wa-ad-dawa', 'الداء والدواء', 'রোগ ও প্রতিকার', "Ad-Da' wa ad-Dawa'", 'ibn-al-qayyim', null],
        ['zad-al-maad', 'زاد المعاد في هدي خير العباد', 'যাদুল মা\'আদ', "Zad al-Ma'ad", 'ibn-al-qayyim', null],
        // Shuruh: the author wrote the original text, the explainer wrote the sharh
        ['sharh-al-usul-ath-thalathah-uthaymin', 'شرح الأصول الثلاثة', 'তিনটি মূলনীতির ব্যাখ্যা', 'Sharh al-Usul ath-Thalathah', 'muhammad-ibn-abdul-wahhab', 'ibn-uthaymin'],
        ['al-qawl-al-mufid', 'القول المفيد على كتاب التوحيد', 'কিতাবুত তাওহীদের ব্যাখ্যা: আল-কাওলুল মুফীদ', 'Al-Qawl al-Mufid ala Kitab at-Tawhid', 'muhammad-ibn-abdul-wahhab', 'ibn-uthaymin'],
        ['sharh-kashf-ash-shubuhat', 'شرح كشف الشبهات', 'সংশয় নিরসনের ব্যাখ্যা', 'Sharh Kashf ash-Shubuhat', 'muhammad-ibn-abdul-wahhab', 'salih-al-fawzan'],
        ['sharh-al-aqidah-al-wasitiyyah', 'شرح العقيدة الواسطية', 'আল-ওয়াসিতিয়্যাহর ব্যাখ্যা', 'Sharh al-Aqidah al-Wasitiyyah', 'ibn-taymiyyah', 'salih-al-fawzan'],
        ['jami-al-ulum-wal-hikam', 'جامع العلوم والحكم', 'জামিউল উলূম ওয়াল হিকাম', "Jami' al-Ulum wal-Hikam", 'an-nawawi', 'ibn-rajab'],
        ['sharh-nawaqid-al-islam', 'شرح نواقض الإسلام', 'ইসলাম বিনষ্টকারী বিষয়সমূহের ব্যাখ্যা', 'Sharh Nawaqid al-Islam', 'muhammad-ibn-abdul-wahhab', 'ibn-baz'],
    ];

    /**
     * Category slug for each test book and series.
     *
     * @var array<string, string>
     */
    private const CATEGORY_OF = [
        'kitab-at-tawhid' => 'aqidah',
        'al-qawaid-al-arba' => 'aqidah',
        'kashf-ash-shubuhat' => 'aqidah',
        'nawaqid-al-islam' => 'aqidah',
        'al-aqidah-al-wasitiyyah' => 'aqidah',
        'al-arbain-an-nawawiyyah' => 'hadith',
        'riyad-as-salihin' => 'hadith',
        'ad-da-wa-ad-dawa' => 'tazkiyah',
        'zad-al-maad' => 'sirah',
        'sharh-al-usul-ath-thalathah-uthaymin' => 'aqidah',
        'al-qawl-al-mufid' => 'aqidah',
        'sharh-kashf-ash-shubuhat' => 'aqidah',
        'sharh-al-aqidah-al-wasitiyyah' => 'aqidah',
        'jami-al-ulum-wal-hikam' => 'hadith',
        'sharh-nawaqid-al-islam' => 'aqidah',
        'dars-kitab-at-tawhid' => 'aqidah',
        'dars-al-qawaid-al-arba' => 'aqidah',
        'dars-kashf-ash-shubuhat' => 'aqidah',
        'dars-nawaqid-al-islam' => 'aqidah',
        'dars-al-wasitiyyah' => 'aqidah',
        'dars-al-arbain' => 'hadith',
        'dars-riyad-as-salihin' => 'hadith',
        'dars-ad-da-wa-ad-dawa' => 'tazkiyah',
        'dars-zad-al-maad' => 'sirah',
        'dars-tafsir-ibn-kathir' => 'tafsir',
        'dars-usul-as-sunnah' => 'aqidah',
    ];

    /**
     * Seed 15 published test books (6 of them shuruh), each with one page of placeholder text,
     * plus 11 dars series with one short dars each.
     */
    public function run(BookImporter $importer, TranscriptImporter $transcripts): void
    {
        $this->call(CategorySeeder::class);
        $categories = Category::pluck('id', 'slug');
        $authors = [];

        foreach (self::AUTHORS as $slug => $names) {
            $authors[$slug] = Author::updateOrCreate(['slug' => $slug], $names);
        }

        foreach (self::BOOKS as [$slug, $titleAr, $titleBn, $translit, $authorSlug, $explainerSlug]) {
            $book = Book::updateOrCreate(['slug' => $slug], [
                'author_id' => $authors[$authorSlug]->id,
                'category_id' => $categories[self::CATEGORY_OF[$slug]] ?? null,
                'is_sharh' => $explainerSlug !== null,
                'explainer_id' => $explainerSlug ? $authors[$explainerSlug]->id : null,
                'title_ar' => $titleAr,
                'title_bn' => $titleBn,
                'title_translit' => $translit,
                'about_md' => "**{$titleBn}** — পরীক্ষার জন্য যুক্ত করা কিতাব।",
                'is_published' => true,
            ]);

            $importer->importJson($book, [
                'pages' => [[
                    'number' => 1,
                    'sentences' => [
                        ['ar' => 'بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ، هَذَا نَصٌّ تَجْرِيبِيٌّ.', 'bn' => 'পরম করুণাময় অসীম দয়ালু আল্লাহর নামে, এটি একটি পরীক্ষামূলক লেখা।'],
                    ],
                ]],
                'toc' => [['title_ar' => 'المقدمة', 'title_bn' => 'ভূমিকা', 'page' => 1]],
            ], replace: true);
        }

        foreach (self::SERIES as [$slug, $titleAr, $titleBn, $translit, $teacherSlug, $bookSlug]) {
            $series = DarsSeries::updateOrCreate(['slug' => $slug], [
                'author_id' => $authors[$teacherSlug]->id,
                'category_id' => $categories[self::CATEGORY_OF[$slug]] ?? null,
                'book_id' => $bookSlug ? Book::where('slug', $bookSlug)->value('id') : null,
                'title_ar' => $titleAr,
                'title_bn' => $titleBn,
                'title_translit' => $translit,
                'about_md' => "**{$titleBn}** — পরীক্ষার জন্য যুক্ত করা দারস সিরিজ।",
                'is_published' => true,
            ]);

            $dars = $series->durus()->updateOrCreate(['number' => 1], [
                'title_bn' => 'প্রথম দারস',
                'media_type' => 'video',
                'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

            $transcripts->importJson($dars, [
                ['start' => '00:00', 'end' => '00:05', 'ar' => 'الحمد لله رب العالمين', 'bn' => 'সমস্ত প্রশংসা আল্লাহর জন্য, যিনি জগতসমূহের রব।'],
                ['start' => '00:05', 'end' => '00:10', 'ar' => 'هذا نص تجريبي', 'bn' => 'এটি একটি পরীক্ষামূলক লেখা।'],
            ]);
        }
    }
}
