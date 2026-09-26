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
            $existing = Page::query()->where('slug', $slug)->first();
            if ($existing !== null) {
                $this->renameInUntouchedDraft($existing);

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
     * 2026-09-26: the shop's name settled on **Akuru Bookstore**, after two
     * earlier names the same day (BOOKSHOP_PLAN header). A host seeded under
     * either earlier name holds it. The
     * office's edits always win, so only a page still carrying the seeded
     * "first draft" line is renamed.
     */
    private function renameInUntouchedDraft(Page $page): void
    {
        $body = (string) $page->body;
        $excerpt = (string) $page->excerpt;
        $old = ['Akuru Online Bookshop', 'Akuru Online Store'];
        if (! str_contains($body, 'First draft pending review by Akuru Institute')
            || ! (str_contains($body.$excerpt, $old[0]) || str_contains($body.$excerpt, $old[1]))) {
            return;
        }

        $rename = static fn (string $text): string => str_replace(
            [...$old, 'Selling in the bookshop', 'Selling in the store', "The bookshop's", "The store's"],
            ['Akuru Bookstore', 'Akuru Bookstore', 'Selling in the bookstore', 'Selling in the bookstore', "The bookstore's", "The bookstore's"],
            $text,
        );

        $page->forceFill(['body' => $rename($body), 'excerpt' => $rename($excerpt)])->save();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private function pages(): array
    {
        $updated = '<p><em>Last updated: 26 September 2026. First draft pending review by Akuru Institute.</em></p>';

        return [
            'vendor-agreement' => ['Vendor Agreement', 'The terms on which a shop sells in the Akuru Bookstore.', <<<HTML
<h2>Vendor Agreement</h2>
$updated
<p>This agreement is between Akuru Institute ("Akuru") and the business selling in the Akuru Bookstore (the "vendor"). Each person who acts for the vendor in the vendor portal accepts it before the portal opens, and the date of acceptance is recorded.</p>
<h3>1. Selling in the bookstore</h3>
<p>Vendors sell by invitation. Every product a vendor marks for sale appears in the one Akuru Bookstore — in search, categories and collections — with the vendor's name on it, and on the vendor's own page. The bookstore's header, cart, checkout, payment, receipts and policies stay Akuru's, and the vendor's page carries the line "at Akuru Bookstore".</p>
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
            // B2: what a customer accepts by placing an order.
            'shop-terms' => ['Terms of Sale', 'How buying in the Akuru Bookstore works.', <<<HTML
<h2>Terms of Sale</h2>
$updated
<p>These terms apply to every order placed in the Akuru Bookstore. The bookstore is run by Akuru Institute ("Akuru"); the products in it are sold by Akuru and by the independent shops it works with, and each product page says who sells it.</p>
<h3>1. Your order</h3>
<p>An order is placed when you complete checkout. It becomes a confirmed order when your payment is received: at once for a card or wallet payment, and when the office confirms your bank transfer slip. Items are held for you for thirty minutes while you pay; a checkout not paid in that time is released and its items go back on sale.</p>
<p>A basket with products from more than one shop becomes one order per shop, each with its own delivery. You receive one receipt per order.</p>
<h3>2. Prices and tax</h3>
<p>Prices are in Maldivian rufiyaa and include any tax. Where a shop is registered for GST, your receipt shows the tax and the shop's TIN. Delivery fees are shown before you place the order and are never discounted.</p>
<h3>3. Paying</h3>
<p>You may pay by card through Bank of Maldives, from your Akuru wallet, or by bank transfer to Akuru's account with the slip uploaded on the order page. Payment is made to Akuru, which passes the shop its share. Akuru never asks for card details by message or phone.</p>
<h3>4. Discount codes</h3>
<p>A discount code reduces the price of goods, not delivery, and cannot be used to buy gift cards. One code per order.</p>
<h3>5. Stock</h3>
<p>Stock is checked when you add to the cart, again when you place the order, and once more when payment arrives. In the rare case an item runs out between your payment and the shop preparing it, the shop will contact you to replace or refund it.</p>
<h3>6. Cancelling, returns and refunds</h3>
<p>See the <a href="/page/delivery-and-returns">Delivery and Returns Policy</a>. Refunds go back the way you paid.</p>
<h3>7. Your details</h3>
<p>Your name, phone number and address are given to the shop that fulfils your order, for that purpose only. See Akuru's <a href="/page/privacy">privacy policy</a>.</p>
HTML],
            'delivery-and-returns' => ['Delivery and Returns', 'How orders reach you, and what to do if something is wrong.', <<<HTML
<h2>Delivery and Returns Policy</h2>
$updated
<h3>1. Delivery</h3>
<p>Each shop chooses how it delivers, and the options and fees for your basket are shown at checkout: collection from the shop or from Akuru Institute, courier in Malé, Hulhumalé and Villimalé, courier to the atolls, or a boat to your island. A boat's fee is paid to the boat on arrival, not at checkout. Each option shows how many days the shop needs to prepare the order.</p>
<p>The shop tells you when your order is ready or on its way. Please check the phone number on your address: it is how the shop and the boat reach you.</p>
<h3>2. If something is wrong</h3>
<p>If an item arrives damaged, faulty or not as described, tell the shop within 7 days of receiving it and it will replace or refund it, with return delivery at the shop's cost.</p>
<h3>3. Returns</h3>
<p>Unless the shop offers longer, you may return an unused item in its original condition within 7 days of receiving it. Return delivery is at your cost unless the item was faulty or not as described. Sealed items that have been opened, and items made to order, can be returned only if faulty.</p>
<h3>4. Refunds</h3>
<p>A refund goes back the way you paid — to your card, your Akuru wallet, or your bank account — once the shop has accepted the return. Delivery fees are refunded only when the return is the shop's fault.</p>
<h3>5. Cancelling</h3>
<p>An order that is not yet paid can simply be left; it is released after thirty minutes. To cancel a paid order before it is prepared, contact the shop or Akuru's office.</p>
HTML],
        ];
    }
}
