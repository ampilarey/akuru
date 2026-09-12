<?php

namespace App\Domains\Finance\Enums;

enum BankStatementMatchStatus: string
{
    /** Nothing proposed. Debits stay here forever; credits land here when matching finds nothing. */
    case Unmatched = 'unmatched';

    /** The matcher proposed an invoice. A **person** still has to agree — nothing is money yet. */
    case Suggested = 'suggested';

    /** A person confirmed it and a receipt was written through the ordinary audited path. */
    case Confirmed = 'confirmed';

    /** Not a school payment: bank charges, interest, an outgoing transfer. */
    case Ignored = 'ignored';
}
