<?php

namespace Database\Seeders;

use App\Domains\Bookshop\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * BOOKSHOP_PLAN §7: the shared categories a first vendor files products
 * under, so the product form is not empty on day one. The office edits and
 * adds to them at `/admin/bookshop`. Idempotent on slug; an existing
 * category is never overwritten. DV/AR first pass pending native review.
 * Production, once:
 *
 *   php artisan db:seed --class=BookshopCatalogueSeeder --force
 */
class BookshopCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $order => [$slug, $name, $dv, $ar]) {
            if (ProductCategory::query()->where('slug', $slug)->exists()) {
                continue;
            }
            ProductCategory::query()->create([
                'slug' => $slug,
                'name' => $name,
                'name_dv' => $dv,
                'name_ar' => $ar,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function categories(): array
    {
        return [
            ['books', 'Books', 'ފޮތް', 'كتب'],
            ['workbooks', 'Workbooks and activity books', 'ވޯކްބުކް އަދި އެކްޓިވިޓީ ފޮތް', 'كتب التمارين والأنشطة'],
            ['quran-and-tajweed', 'Qur’an and tajweed', 'ޤުރުއާން އަދި ތަޖްވީދު', 'القرآن والتجويد'],
            ['islamic-studies', 'Islamic studies', 'އިސްލާމް', 'التربية الإسلامية'],
            ['arabic-learning', 'Arabic learning', 'ޢަރަބި ދަސްކުރުން', 'تعلم العربية'],
            ['educational-toys', 'Educational toys and games', 'ތަޢުލީމީ ކުޅިވަރު', 'ألعاب تعليمية'],
            ['stationery', 'Stationery', 'ލިޔަންކިޔަން ތަކެތި', 'قرطاسية'],
            ['school-supplies', 'School supplies', 'ސްކޫލް ތަކެތި', 'مستلزمات مدرسية'],
            ['art-and-craft', 'Art and craft', 'އާޓް އަދި ކްރާފްޓް', 'فنون وأشغال يدوية'],
        ];
    }
}
