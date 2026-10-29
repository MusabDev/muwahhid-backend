<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\DarsSeries;
use App\Services\BookImporter;
use App\Services\TranscriptImporter;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    /**
     * Seed one author with a short book and a dars, for local development.
     */
    public function run(BookImporter $bookImporter, TranscriptImporter $transcriptImporter): void
    {
        $author = Author::updateOrCreate(['slug' => 'muhammad-ibn-abdul-wahhab'], [
            'name_ar' => 'محمد بن عبد الوهاب',
            'name_bn' => 'মুহাম্মাদ ইবনু আব্দিল ওয়াহহাব',
            'name_translit' => 'Muhammad ibn Abdil Wahhab',
            'death_year_ah' => 1206,
            'about_md' => 'শাইখুল ইসলাম মুহাম্মাদ ইবনু আব্দিল ওয়াহহাব (রহিমাহুল্লাহ)।',
        ]);

        $aqidah = Category::where('slug', 'aqidah')->value('id');

        $book = Book::updateOrCreate(['slug' => 'al-usul-ath-thalathah'], [
            'author_id' => $author->id,
            'category_id' => $aqidah,
            'title_ar' => 'الأصول الثلاثة',
            'title_bn' => 'তিনটি মূলনীতি',
            'title_translit' => 'Al-Usul ath-Thalathah',
            'about_md' => 'প্রত্যেক মুসলিমের জন্য জানা আবশ্যক তিনটি মূলনীতি।',
            'is_published' => true,
        ]);

        $bookImporter->importJson($book, [
            'pages' => [
                [
                    'number' => 1,
                    'sentences' => [
                        ['ar' => 'بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ', 'bn' => 'পরম করুণাময় অসীম দয়ালু আল্লাহর নামে।', 'block_type' => 'heading'],
                        ['ar' => 'اعْلَمْ **رَحِمَكَ اللَّهُ** أَنَّهُ يَجِبُ عَلَيْنَا تَعَلُّمُ أَرْبَعِ مَسَائِلَ:[^1]', 'bn' => 'জেনে রাখো, **আল্লাহ তোমার প্রতি রহম করুন**, আমাদের উপর চারটি বিষয় শেখা আবশ্যক:[^1]', 'paragraph_break' => true],
                        ['ar' => 'الْأُولَى: الْعِلْمُ، وَهُوَ مَعْرِفَةُ اللَّهِ، وَمَعْرِفَةُ نَبِيِّهِ، وَمَعْرِفَةُ دِينِ الْإِسْلَامِ بِالْأَدِلَّةِ.', 'bn' => 'প্রথম: ইলম, আর তা হলো দলিলসহ আল্লাহকে, তাঁর নবীকে এবং ইসলাম ধর্মকে জানা।', 'block_type' => 'list_item'],
                        ['ar' => 'الثَّانِيَةُ: الْعَمَلُ بِهِ.', 'bn' => 'দ্বিতীয়: সে অনুযায়ী আমল করা।', 'block_type' => 'list_item'],
                        ['ar' => 'الثَّالِثَةُ: الدَّعْوَةُ إِلَيْهِ.', 'bn' => 'তৃতীয়: সেদিকে দাওয়াত দেওয়া।', 'block_type' => 'list_item'],
                        ['ar' => 'الرَّابِعَةُ: الصَّبْرُ عَلَى الْأَذَى فِيهِ.', 'bn' => 'চতুর্থ: এ পথে আসা কষ্টে ধৈর্য ধারণ করা।', 'block_type' => 'list_item'],
                    ],
                    'footnotes' => [
                        'ar' => [['marker' => '1', 'text' => 'أي: يجب على كل مكلف.']],
                        'bn' => [['marker' => '1', 'text' => 'অর্থাৎ প্রত্যেক দায়িত্বপ্রাপ্ত ব্যক্তির উপর।']],
                    ],
                ],
                [
                    'number' => 2,
                    'sentences' => [
                        ['ar' => 'وَالدَّلِيلُ قَوْلُهُ تَعَالَى:', 'bn' => 'এর দলিল আল্লাহ তাআলার বাণী:', 'paragraph_break' => true],
                        ['ar' => 'وَالْعَصْرِ ۝ إِنَّ الْإِنسَانَ لَفِي خُسْرٍ', 'bn' => 'কালের শপথ, নিশ্চয়ই মানুষ ক্ষতির মধ্যে রয়েছে।', 'block_type' => 'quote'],
                    ],
                ],
            ],
            'toc' => [
                ['title_ar' => 'المسائل الأربع', 'title_bn' => 'চারটি বিষয়', 'page' => 1],
                ['title_ar' => 'الدليل', 'title_bn' => 'দলিল', 'page' => 2],
            ],
        ], replace: true);

        $series = DarsSeries::updateOrCreate(['slug' => 'sharh-al-usul-ath-thalathah'], [
            'author_id' => $author->id,
            'category_id' => $aqidah,
            'book_id' => $book->id,
            'title_ar' => 'شرح الأصول الثلاثة',
            'title_bn' => 'তিনটি মূলনীতির ব্যাখ্যা',
            'title_translit' => 'Sharh al-Usul ath-Thalathah',
            'is_published' => true,
        ]);

        $dars = $series->durus()->updateOrCreate(['number' => 1], [
            'title_bn' => 'ভূমিকা',
            'media_type' => 'video',
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $transcriptImporter->importJson($dars, [
            ['start' => '00:00', 'end' => '00:04.5', 'ar' => 'الحمد لله رب العالمين', 'bn' => 'সমস্ত প্রশংসা আল্লাহর জন্য, যিনি জগতসমূহের রব।'],
            ['start' => '00:04.5', 'end' => '00:09', 'ar' => 'اعلم رحمك الله', 'bn' => 'জেনে রাখো, আল্লাহ তোমার প্রতি রহম করুন।'],
        ]);
    }
}
