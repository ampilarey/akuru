# ADR-042: Akuru's fulfilment fees are lines on the order and the earning, never edits

## Context

The owner (2026-09-30, COMMERCE_PARITY_PLAN brief, point 8) asked that inventory and
delivery be handled "by the vendor or by Akuru, with an extra charge when Akuru does it".
P6a builds this, and the charge has to fit the money rules (rule 12: append-only ledgers;
reversals, never deletes or edits), the shop's earning (B6) and the monthly commission
invoice (B6, audit finding 4).

There were two ways to charge:
- fold Akuru's share into the commission rate;
- give it separate lines of its own.

A higher commission rate would hide the charge, so it could not be shown or switched per
shop. It would also make the invoice's tax line wrong, because the charge is a service,
not a commission.

## Decision

- **Two charges, each named on its own line.**
  - The **handling fee**: per order Akuru packs. It is fixed on the order when the order is
    placed (`orders.akuru_handling_fee`), recorded on the shop's earning
    (`vendor_earnings.akuru_handling_fee`) and taken off its net. It is summed onto the
    monthly invoice as *Handling by Akuru* (`vendor_commission_invoices.handling`).
  - The **Akuru courier fee**: paid by the customer at checkout. It is Akuru's revenue
    (`orders.delivery_revenue_to = akuru`), so it never enters the shop's earning.
- **Fixed when the order is placed.** `fulfilled_by`, the handling fee and who the delivery
  fee belongs to are copied onto the order. A later change to the shop's arrangement, or to
  the office's fees, never rewrites an order already placed.
- **Reversals follow the existing rule.** An order undone in full was never packed, so its
  handling goes to zero with the rest of the earning. A partial refund keeps the handling,
  because the packing was done.
- **Stock at Akuru is a place, not a count.** `products.stock_at_akuru` is the part of
  `stock` Akuru holds:
  - a hand-over and a hand-back are logged (`received_at_akuru`, `returned_to_vendor`) and
    leave `stock` unchanged;
  - a paid Akuru-packed order draws from it, and a cancellation puts it back.
- **The office works Akuru's orders through the shop's own state machine.**
  `FulfilVendorOrderAction` accepts an office scope (`ResolveVendorScopeAction::forOffice`)
  only for an order Akuru packs, and refuses a member's scope for one. Nobody gets a second
  path to an order.

## Consequences

- The shop sees exactly what Akuru charged: on each earning, on the order page and on the
  invoice. Turning Akuru on or off for a shop never changes past money.
- The handling fee carries no GST of its own. If Akuru becomes GST-registered for services,
  the invoice gains a tax line for handling. That is a new line, not a change to these.
- Drivers, proof of delivery and the driver's page are P6b. Until then the office marks
  Akuru's deliveries itself, as a shop does.
