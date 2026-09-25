<?php

namespace Database\Seeders;

use App\Domains\Website\Models\Page;
use Illuminate\Database\Seeder;

/**
 * LIBRARY_PLAN "Required pages": Publishing Terms, Reader Terms, Gift Card
 * Terms, Wallet Terms, Copyright Policy, Writer Agreement, Promotion
 * Policy. (Refund Policy and Privacy Policy exist from PolicyPagesSeeder.)
 *
 * Seeded as CMS pages, so the office edits them in the page editor rather
 * than in code. Idempotent on slug; a page the office has already edited
 * is left alone (`updateOrCreate` only fills a page that does not exist).
 *
 * The text is a careful first draft that states what the platform
 * actually does — the 70/30 default split, the 7-day refund window, the
 * webhook-only access rule, hashed gift card codes — and is marked for the
 * owner's review. Production, once:
 *
 *   php artisan db:seed --class=LibraryPolicyPagesSeeder --force
 */
class LibraryPolicyPagesSeeder extends Seeder
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
        $updated = '<p><em>Last updated: 25 September 2026. First draft pending review by Akuru Institute.</em></p>';

        return [
            'publishing-terms' => ['Publishing Terms', 'The terms under which Akuru Institute publishes writers\' work in the Knowledge Library.', <<<HTML
<h2>Publishing Terms</h2>
$updated
<h3>1. What you give us</h3>
<p>By submitting a book, article or research paper to the Akuru Knowledge Library you grant Akuru Institute a non-exclusive right to publish, display, sell access to and promote that work on its platforms. You keep the copyright. You may ask for a work to be withdrawn from sale at any time; readers who have already bought it keep their access.</p>
<h3>2. What we check</h3>
<p>Nothing is published without editorial approval. Research is additionally sent to a peer reviewer. We may ask for changes, and we may decline a work without giving a reason.</p>
<h3>3. Money</h3>
<p>Prices are suggested by the writer and set by Akuru Institute. Unless a different split is agreed in writing, the writer receives 70% of the price paid by the reader and Akuru Institute 30%. Where a discount funded by Akuru Institute is applied, the writer's share is calculated on the full price; where a discount funded by the writer is applied, on the discounted price. Earnings become available for payout seven days after the sale, once the refund window has closed. Payouts are made on request to the bank details on file, and are subject to the Writer Agreement.</p>
<h3>4. Removal</h3>
<p>Akuru Institute may remove a work on a credible copyright complaint, on a breach of these terms, or where the work is unlawful, and will tell the writer why.</p>
HTML],
            'writer-agreement' => ['Writer Agreement', 'What a writer confirms when applying to publish with Akuru Institute.', <<<HTML
<h2>Writer Agreement</h2>
$updated
<p>By applying to write for the Akuru Knowledge Library, and again each time you submit a work, you confirm that:</p>
<ol>
<li>you own the copyright in everything you upload, or hold the permission needed to publish it, and it does not infringe anyone else's rights;</li>
<li>Akuru Institute may publish, display, promote and sell access to the work under the Publishing Terms;</li>
<li>the work is your own; where AI tools were used in preparing it, you have said so on the submission form;</li>
<li>for research, the work is original, is not under review or published elsewhere, and any conflict of interest and any ethics approval have been declared;</li>
<li>Akuru Institute may remove the work on a valid complaint;</li>
<li>you accept the payment and commission terms, the refund window, the promotion and discount rules and the payout process described in the Publishing Terms and the Promotion Policy.</li>
</ol>
HTML],
            'reader-terms' => ['Reader Terms', 'How reading, buying and keeping access to library items works.', <<<HTML
<h2>Reader Terms</h2>
$updated
<h3>1. Reading</h3>
<p>Library items are read on this website, one page at a time, and each page carries the reader's name and the time. Items are not downloadable. Access is personal: sharing an account, copying pages or attempting to extract a work is a breach of these terms and may end your access without refund.</p>
<h3>2. Buying</h3>
<p>Paid items are bought by card through Bank of Maldives, by wallet balance, or with a discount code. Access opens when the bank confirms the payment, which usually takes a moment. A gift card is redeemed into your wallet first and then spent from it.</p>
<h3>3. Refunds</h3>
<p>Our Refund Policy applies. Because access to a work opens immediately, refunds are considered on request within seven days and only where the work could not be read.</p>
<h3>4. Your reading record</h3>
<p>We keep your reading position, bookmarks and private notes so you can continue where you left off. They are visible to you only.</p>
HTML],
            'gift-card-terms' => ['Gift Card Terms', 'Buying, sending and redeeming an Akuru Institute gift card.', <<<HTML
<h2>Gift Card Terms</h2>
$updated
<ol>
<li>A gift card is bought by card through Bank of Maldives for a whole amount within the range shown on the gift card page. Discount codes and wallet balance cannot be used to buy a gift card.</li>
<li>The card is issued when the bank confirms the payment. Its code is sent once, to the email address and/or mobile number given for the recipient, and is not stored anywhere it can be read back. Keep it safe: a lost code cannot be reissued.</li>
<li>A gift card is redeemed in full into the redeeming account's wallet, and can be redeemed once. It has no expiry unless one is stated at purchase.</li>
<li>Gift cards are not refundable and cannot be exchanged for cash.</li>
</ol>
HTML],
            'wallet-terms' => ['Wallet Terms', 'How the Akuru wallet holds and spends money.', <<<HTML
<h2>Wallet Terms</h2>
$updated
<ol>
<li>The wallet holds Maldivian rufiyaa credited from redeemed gift cards, refunds and credits made by Akuru Institute. It cannot be topped up directly and cannot be withdrawn as cash.</li>
<li>Wallet balance can be spent on library items and, where offered, on course fees. Every movement is recorded on the wallet's ledger, which you can see on the My Wallet page.</li>
<li>A purchase paid from the wallet opens access immediately.</li>
<li>Akuru Institute may correct a wallet balance where a credit was made in error, and will record the correction on the ledger.</li>
</ol>
HTML],
            'copyright-policy' => ['Copyright Policy', 'How Akuru Institute handles copyright in the Knowledge Library.', <<<HTML
<h2>Copyright Policy</h2>
$updated
<p>Every work in the Knowledge Library is published with the writer's declaration that they own it or have permission to publish it. Copyright stays with the author; Akuru Institute holds a publishing licence under the Publishing Terms.</p>
<p>If you believe a work infringes your copyright, write to Akuru Institute with the title of the work, what you own, and how it is infringed. We will take the work down while we look into it, tell the writer, and restore or remove it according to what we find. Repeated infringement ends a writer's account.</p>
<p>Readers may not copy, redistribute or extract works from the reader. Each delivered page is marked with the reader's identity for this reason.</p>
HTML],
            'promotion-policy' => ['Promotion Policy', 'How discounts and promotions are applied to library items.', <<<HTML
<h2>Promotion Policy</h2>
$updated
<ol>
<li>Discount codes reduce the price of a library item or a course. They never apply to gift cards.</li>
<li>Each discount code says who funds it: Akuru Institute or the writer. A writer's share of a discounted sale is calculated as set out in the Publishing Terms.</li>
<li>Codes may have a validity period, a usage limit and a per-person limit, and may be withdrawn at any time. A code confirmed at checkout is honoured for that purchase.</li>
<li>Akuru Institute may feature, bundle or promote works at its discretion and will tell writers of promotions that change their share.</li>
</ol>
HTML],
        ];
    }
}
