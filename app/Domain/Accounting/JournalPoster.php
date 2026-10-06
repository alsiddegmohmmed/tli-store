<?php

namespace App\Domain\Accounting;

use Illuminate\Support\Facades\DB;

class JournalPoster
{
    /**
     * Caller owns the surrounding business transaction.
     *
     * @param  array{sale_id: int}|array{shift_cash_transfer_id: int}|array{purchase_bill_id: int}|array{supplier_payment_id: int}  $source
     * @param  list<array{account_code: string, debit?: numeric-string, credit?: numeric-string}>  $lines
     */
    public function post(array $source, int $userId, string $date, string $description, array $lines): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Journal posting requires a business transaction.');
        }
        $debits = $credits = '0.00';
        $rows = [];
        foreach ($lines as $line) {
            $debit = $line['debit'] ?? '0.00';
            $credit = $line['credit'] ?? '0.00';
            if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $debit) || ! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $credit)) {
                throw new \LogicException('Journal amounts must be rounded to cents before posting.');
            }
            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
                continue;
            }
            if (bccomp($debit, '0', 2) < 0 || bccomp($credit, '0', 2) < 0 || (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0)) {
                throw new \LogicException('Invalid journal line.');
            }
            $debits = bcadd($debits, $debit, 2);
            $credits = bcadd($credits, $credit, 2);
            $rows[] = ['account_code' => $line['account_code'], 'debit' => $debit, 'credit' => $credit];
        }
        if (bccomp($debits, $credits, 2) !== 0) {
            throw new \LogicException('Unbalanced journal.');
        }
        if ($rows === []) {
            throw new \LogicException('A posted document requires journal lines.');
        }
        $id = DB::table('journal_entries')->insertGetId([...$source,
            'created_by' => $userId, 'posting_date' => $date, 'description' => $description, 'created_at' => now()]);
        DB::table('journal_lines')->insert(array_map(fn ($line) => ['journal_entry_id' => $id, ...$line], $rows));

        return $id;
    }
}
