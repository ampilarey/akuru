<?php

namespace Database\Seeders;

use App\Domains\Website\Models\Page;
use Illuminate\Database\Seeder;

/**
 * BOOKSHOP_PLAN §4 (audit finding 14, decision 16): the Vendor Agreement a
 * vendor member accepts before the portal opens (slice B1a). B2 adds the
 * customer-facing Shop Terms and Delivery & Returns Policy here.
 *
 * Seeded as a CMS page so the office edits it in the page editor, not in
 * code. Idempotent on slug: a page that exists is never overwritten. The
 * text is a first draft stating what the platform does and what the owner
 * decided (2026-09-25), marked for the owner's review (BACKLOG A6's pattern).
 * Production, once:
 *
 *   php artisan db:seed --class=BookshopPolicyPagesSeeder --force
 */
class BookshopPolicyPagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => [$title, $excerpt, $body]) {
            if (Page::query()->where('slug', $slug)->exists()) {
                continue;
            }
            Page::query()->create([
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
                'is_published' => true,
                'published_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private function pages(): array
    {
        $updated = '<p><em>Last updated: 26 September 2026. First draft pending review by Akuru Institute.</em></p>';

        return [
            'vendor-agreement' => ['Vendor Agreement', 'The terms on which a shop sells in the Akuru Online Bookshop.', <<<HTML
<h2>Vendor Agreement</h2>
$updated
<p>This agreement is between Akuru Institute ("Akuru") and the business selling in the Akuru Online Bookshop (the "vendor"). Each person who acts for the vendor in the vendor portal accepts it before the portal opens, and the date of acceptance is recorded.</p>
<h3>1. Selling in the bookshop</h3>
<p>Vendors sell by invitation. Every product a vendor marks for sale appears in the one Akuru Online Bookshop — in search, categories and collections — with the vendor's name on it, and on the vendor's own page. The bookshop's header, cart, checkout, payment, receipts and policies stay Akuru's, and the vendor's page carries the line "at Akuru Online Bookshop".</p>
<h3>2. What the vendor promises</h3>
<ol>
<li>Products are described honestly, with true prices, photos the vendor may use, and correct stock.</li>
<li>The vendor holds the rights to sell what it lists; nothing unlawful, counterfeit or unsafe for children is listed.</li>
<li>Orders are packed and handed over or delivered within the handling time the vendor states.</li>
<li>Customers' names, phone numbers and addresses are used only to fulfil their orders, and are not kept or used afterwards.</li>
</ol>
<h3>3. Money</h3>
<p>Customers pay Akuru, by card through the bank, from their Akuru wallet, or by bank transfer confirmed against the slip. Akuru keeps a commission on the price of goods — 10% unless a different rate is agreed with the vendor in writing — and no commission on delivery fees. Akuru issues the vendor a monthly invoice for its commission. The rest is the vendor's, and becomes available for payout once the return window for the order has closed. Payouts go to the bank details the vendor enters in its own portal; Akuru never asks for them by message.</p>
<h3>4. Tax</h3>
<p>Prices are shown including any tax. A vendor registered for GST gives Akuru its TIN, and its customers' receipts show the tax; a vendor that is not registered sells without a tax line. Each vendor is responsible for its own tax filings.</p>
<h3>5. Returns and refunds</h3>
<p>Unless the vendor offers longer, a customer may return an unused item within 7 days of receiving it; the buyer pays return delivery unless the item was faulty or not as described. The vendor accepts or declines a return request in its portal. Refunds go back the way the customer paid, and the vendor's earning on a refunded item is reversed.</p>
<h3>6. What the office may do</h3>
<p>Akuru may hide or edit a product listing, require changes to a vendor's page, or suspend a vendor, where a listing breaks this agreement or the law, or on a credible complaint, and will tell the vendor why. Either side may end this agreement by written notice; orders already placed are completed or refunded.</p>
HTML],
        ];
    }
}
