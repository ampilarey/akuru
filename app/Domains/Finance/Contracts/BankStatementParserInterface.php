<?php

namespace App\Domains\Finance\Contracts;

use App\Domains\Finance\DTOs\BankStatementRow;

/**
 * Rule 4: a bank's file format never reaches domain logic.
 *
 * This exists because **the real BML export format is not known**. Rather than
 * guess a layout and bake it into an importer, the parser is a seam: the
 * shipped implementation reads a column map from config, and the day a genuine
 * export arrives, adapting to it is a config change — or, if the file is not
 * CSV at all, a new implementation and a driver change. Neither touches the
 * import, matching or receipt code.
 */
interface BankStatementParserInterface
{
    /**
     * @return list<BankStatementRow>
     *
     * @throws \Illuminate\Validation\ValidationException when the file cannot be read as a statement
     */
    public function parse(string $contents): array;
}
