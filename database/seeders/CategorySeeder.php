<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The starting set of subject categories, in display order: slug => [name_bn, name_ar].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CATEGORIES = [
        'aqidah' => ['আকীদা', 'العقيدة'],
        'tafsir' => ['তাফসীর', 'التفسير'],
        'hadith' => ['হাদীস', 'الحديث'],
        'fiqh' => ['ফিকহ', 'الفقه'],
        'sirah' => ['সীরাহ', 'السيرة'],
        'tazkiyah' => ['তাযকিয়া', 'التزكية'],
        'adab' => ['আদব ও আখলাক', 'الآداب والأخلاق'],
        'arabic' => ['আরবি ভাষা', 'اللغة العربية'],
    ];

    /**
     * Seed the categories (existing ones keep their slug and are updated).
     */
    public function run(): void
    {
        foreach (array_keys(self::CATEGORIES) as $position => $slug) {
            [$nameBn, $nameAr] = self::CATEGORIES[$slug];
            Category::updateOrCreate(['slug' => $slug], ['name_bn' => $nameBn, 'name_ar' => $nameAr, 'position' => $position]);
        }
    }
}
