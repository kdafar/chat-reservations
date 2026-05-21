<?php

namespace App\Policies\Clinic\Accounting;

use App\Policies\Clinic\BaseClinicFilamentPolicy;

class JournalEntryPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'accounting_journal_entries';
}
