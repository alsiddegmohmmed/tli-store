<?php

namespace App\Domain\Accounting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReadDocumentJournal
{
    /**
     * Caller authorizes access to the source document and its financial journal.
     *
     * @param  array{sale_id: int}|array{shift_cash_transfer_id: int}|array{purchase_bill_id: int}|array{supplier_payment_id: int}  $source
     * @return Collection<int, \stdClass>
     */
    public function execute(array $source): Collection
    {
        return DB::table('journal_lines')->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('ledger_accounts', 'journal_lines.account_code', '=', 'ledger_accounts.code')
            ->where('journal_entries.'.array_key_first($source), reset($source))->orderBy('journal_lines.id')
            ->get(['ledger_accounts.name', 'journal_lines.debit', 'journal_lines.credit']);
    }
}
