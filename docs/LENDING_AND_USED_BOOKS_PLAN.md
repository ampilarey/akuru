# Used books and book lending — plan (2026-10-01)

The owner, 2026-10-01: "I want two more features. 1) book lending features.
Lender register and list books to lend, and lend books to others. 2) old and
used books selling features."

Both sit beside the Akuru Bookstore (`docs/BOOKSHOP_PLAN.md`). Used books are
the Bookstore with a condition on the product and a lighter way in for a
person who is not a shop. Lending is new: no money changes hands through
Akuru, so it is its own small domain, not a corner of Commerce.

## 1. What exists

- **The Bookstore** sells new products through shops (`vendors`), each with
  an owner whose ID card the office checks (COMMERCE_PARITY_PLAN P2) and
  listings the office approves (P4). A person opens a shop by applying
  (B9a). Products carry price, stock, photos, category, book details. Nothing
  says whether a product is new or used.
- **Circulation** (`app/Domains/Circulation`) lends the *school's* book
  copies to *students and staff* by academic year — textbooks issued in
  bulk, returned in bulk. It is the school library's desk, not a place
  where one person lends another their own book, and its borrower is a
  student or a staff member. Lending between Akuru's users is a different
  thing and gets its own domain.

## 2. Decisions, with defaults

The agent decided these to build without stopping; the owner can overturn
any of them with a word.

| # | Decision | Default | Why |
|---|---|---|---|
| D1 | **Who may sell used books** | Any shop, and any **person** who applies as a *personal seller*. A personal seller is a vendor of kind `personal`: the same ID check, the same listing approval, the same commission, payouts and delivery methods. | Everything that keeps the Bookstore honest already hangs off `vendors`; a second seller table would duplicate all of it. The office can still tell the two apart (`vendors.kind`). |
| D2 | **Condition grades** | `new` (default), `like_new`, `good`, `fair`, `worn`, plus a free-text *condition note* (what is marked, written in, missing). | Five words a buyer understands; the note carries the rest. |
| D3 | **Returns on used books** | The shop's usual window (seven days at least, BOOKSHOP_PLAN decision 8). | A used book described honestly is still returnable if it is not as described. |
| D4 | **Lending and money** | **No money through Akuru in v1.** Lending is free; a lender may name a deposit, which is written on the book's page and settled between the two people at handover. | Taking deposits would make every loan a payment under rule 12 (webhook confirmation, append-only ledger, refunds) — a week of money work before the first book changes hands. Said plainly on the page; revisit when lenders ask. |
| D5 | **Who may lend** | Any signed-in person who registers as a lender. Their books go public once the office has checked their ID card (purpose `lender`), the same card check shops get. Borrowers must be signed in; a lender may require a borrower with a checked ID. | A stranger takes a book home; the lender should know who. |
| D6 | **Who runs it at the office** | Bookstore admins (`bookshop.manage`). | One team already looks at ID cards and listings daily. |
| D7 | **Where it lives** | `/shop/used` and the *Used* filter inside the Bookstore; `/lending` beside it, with *Borrow books* in the Bookstore's menu; *My lending* in the signed-in shell. | Readers already know where the Bookstore is. |

## 3. Slices

| Slice | What | State |
|---|---|---|
| **U1** | **Old and used books.** `products.condition` and `condition_note`; `vendors.kind` (`shop` / `personal`) and the same on applications; the application form asks *A shop* or *Just me, selling my own books*, and hides the legal name, TIN and link for a person; the product form carries the condition and note, and a *Add a used book* button opens a short form (title, author, condition, note, price, photos — one in stock); the card and the product page show the condition; `/shop/used` and a *Used books only* filter; a *Used books* shelf on the Bookstore's front and *Used books* in its menu; JSON-LD `itemCondition`; CSV export and import carry `condition`; the office's application card says *Personal seller*; a personal seller's shop head and product page say so. EN/DV/AR. | **Built 2026-10-01** (STATUS §5mr) |
| **L1** | **Book lending, the loop.** `Domains/Lending`: `lenders` (one per user: display name, island, about, whether borrowers need a checked ID, status), `lending_books` (title, author, language, condition, description, photo, grade/subject, how long it may be kept, a deposit as words, status), `lending_loans` (requested → accepted / declined → out → returned, or cancelled; `academic_year_id`, rule 10). A person registers as a lender (and sends their ID card); lists books; `/lending` shows available books from checked lenders with search and filters; a book's page; *Ask to borrow* with a message (signed in); the lender accepts (setting the return date) or declines; on acceptance both see each other's phone; the lender marks *Handed over* and *Returned*; the borrower may cancel before handover; in-app, email and SMS notices at each step; `/my-lending` with *Books I lend* and *Books I borrow*; `/admin/lending` listing lenders, books and loans. EN/DV/AR. | **Built 2026-10-01** (STATUS §5ms) |
| **L2** | **Lending, the rest.** `lending:remind` daily (two days before, on the day, every day overdue — to both); ratings of each other after a return, shown on the lender's books and told to a lender about a borrower; a lender pauses or removes a book; office moderation (pause a lender, remove a book, with a note) and CSV. | **Built 2026-10-01** (STATUS §5mt) |
| **L3** | **Free books, given away.** The owner, after L2: "I don't see free book, give away". `lending_books.offer` (`lend` / `give`); the add/edit form asks which; a *Free to keep* badge on the card and page, *Given by*, no days or deposit; an *Offered* filter and a *Free books* chip on the shelf; a give-away is accepted without a return date and at handover closes as *Given* with the book *Given away* — gone from the shelf for good; both may still rate. EN/DV/AR. | **Built 2026-10-01** (STATUS §5mv) |

## 4. What the owner owns

- **D4**: whether Akuru should ever hold deposits or charge a lending fee.
  If yes, it is a Commerce slice: a deposit is a payment (rule 12), its
  return a reversal on the ledger, and Akuru's cut — if any — a commission
  like the Bookstore's.
- Whether a personal seller's commission should differ from a shop's (the
  office can set a rate per vendor already).
