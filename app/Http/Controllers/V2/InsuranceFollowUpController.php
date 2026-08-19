<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Insurance\InsuranceClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Insurance Follow-up — the collections board for money insurers owe us.
 *
 * The Claims page answers "what is the state of this claim?". This page answers
 * "who do I chase today, and how much is stuck with them?" — outstanding money
 * grouped per insurer with aging buckets, plus a worklist of the individual
 * claims behind it. Chasing is recorded on the claim itself (last_chased_at /
 * follow_up_at / meta.chases[]) so nothing needs a spreadsheet on the side.
 *
 * Nothing here mutates a claim's STATUS — that stays with ClaimsController and
 * the state machine. This controller only writes chase notes and due dates.
 */
class InsuranceFollowUpController extends Controller
{
    /** Balance still owed to us by the insurer. Mirrors ClaimsController::BALANCE_SQL. */
    private const BALANCE_SQL = '(insurance_claims.insurer_payable - insurance_claims.paid_amount - insurance_claims.write_off_amount - insurance_claims.rejected_amount)';

    /** Days the claim has been open: since we sent it, else since it was drafted. */
    private const AGE_SQL = 'DATEDIFF(CURDATE(), DATE(COALESCE(insurance_claims.submitted_at, insurance_claims.created_at)))';

    /** Terms fall back to 30 days when an insurer has none recorded. */
    private const TERMS_SQL = 'COALESCE(NULLIF(insurers.payment_terms_days, 0), 30)';

    /** Past the insurer's agreed payment terms — i.e. they are late. */
    private const OVERDUE_SQL = self::AGE_SQL.' > '.self::TERMS_SQL;

    /** Claims still worth chasing: not settled, not dead, and money still on them. */
    private const OPEN_STATUSES = [
        InsuranceClaim::STATUS_DRAFT,
        InsuranceClaim::STATUS_SUBMITTED,
        InsuranceClaim::STATUS_UNDER_REVIEW,
        InsuranceClaim::STATUS_APPROVED,
        InsuranceClaim::STATUS_PARTIALLY_APPROVED,
    ];

    private const TABS = ['chase_now', 'scheduled', 'unsubmitted', 'waiting', 'unpaid', 'all'];

    private const SORTS = ['aging', 'outstanding', 'due'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_insurance_claims')) {
            abort(403, 'Not authorized to view insurance follow-up.');
        }
    }

    /** Logging a chase is a claim-handling action, same bar as sending a claim. */
    protected function canChase(Request $request): bool
    {
        $u = $request->user();

        return (bool) ($u?->can('insurance_submit_claim') || $u?->can('insurance_record_payment'));
    }

    /**
     * Every open claim, joined to its policy + insurer so aging can be compared
     * against that insurer's payment terms. Branch filter applied here so the
     * KPIs, the insurer table and the worklist can never disagree.
     */
    protected function openClaims(?int $branchId = null)
    {
        $query = InsuranceClaim::query()
            ->join('patient_insurance_policies', 'patient_insurance_policies.id', '=', 'insurance_claims.patient_policy_id')
            ->join('insurers', 'insurers.id', '=', 'patient_insurance_policies.insurer_id')
            ->whereIn('insurance_claims.status', self::OPEN_STATUSES)
            ->whereRaw(self::BALANCE_SQL.' > 0.0005');

        if ($branchId) {
            $query->where('insurance_claims.branch_id', $branchId);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'chase_now';
        $sort = in_array($request->input('sort'), self::SORTS, true) ? $request->input('sort') : 'aging';
        $insurerId = (int) $request->input('insurer', 0) ?: null;
        $branchId = (int) $request->input('branch', 0) ?: null;
        $q = trim((string) $request->input('q', ''));

        $filters = ['tab' => $tab, 'sort' => $sort, 'insurer' => $insurerId, 'branch' => $branchId, 'q' => $q];

        return Inertia::render('Insurance/FollowUp', [
            'filters' => $filters,
            'kpis' => $this->kpis($branchId),
            'insurers' => $this->insurerRows($branchId),
            'page' => $this->worklist($tab, $sort, $insurerId, $branchId, $q),
            'tab_counts' => $this->tabCounts($insurerId, $branchId),
            'branches' => $this->branchOptions(),
            'today' => now()->toDateString(),
            'can' => [
                'chase' => $this->canChase($request),
                'view_claims' => (bool) $request->user()?->can('view_any_insurance_claims'),
                // Demo-only: the simulate-reply button on the email log.
                'simulate_replies' => (bool) config('clinic.insurance_replies.demo_enabled', false),
            ],
        ]);
    }

    /** Headline numbers for the whole board (respect the branch filter only). */
    protected function kpis(?int $branchId): array
    {
        $row = $this->openClaims($branchId)
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw('COALESCE(SUM('.self::BALANCE_SQL.'), 0) as outstanding')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::OVERDUE_SQL.' THEN '.self::BALANCE_SQL.' ELSE 0 END), 0) as overdue_amount')
            ->selectRaw('SUM(CASE WHEN '.self::OVERDUE_SQL.' THEN 1 ELSE 0 END) as overdue_count')
            ->selectRaw("SUM(CASE WHEN insurance_claims.status = 'draft' THEN 1 ELSE 0 END) as draft_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN insurance_claims.status = 'draft' THEN ".self::BALANCE_SQL.' ELSE 0 END), 0) as draft_amount')
            ->selectRaw('SUM(CASE WHEN insurance_claims.follow_up_at IS NOT NULL AND insurance_claims.follow_up_at <= CURDATE() THEN 1 ELSE 0 END) as due_count')
            ->selectRaw('SUM(CASE WHEN insurance_claims.last_chased_at IS NULL THEN 1 ELSE 0 END) as never_chased')
            ->selectRaw('MAX('.self::AGE_SQL.') as oldest_days')
            ->first();

        // Money actually collected from insurers in the last 30 days — the
        // counterweight to "outstanding": is the chasing working?
        $collected30 = (float) \App\Models\Insurance\InsuranceClaimPayment::query()
            ->when($branchId, fn ($p) => $p->where('branch_id', $branchId))
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');

        return [
            'open_count' => (int) ($row->open_count ?? 0),
            'outstanding' => round((float) ($row->outstanding ?? 0), 3),
            'overdue_amount' => round((float) ($row->overdue_amount ?? 0), 3),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'draft_count' => (int) ($row->draft_count ?? 0),
            'draft_amount' => round((float) ($row->draft_amount ?? 0), 3),
            'due_count' => (int) ($row->due_count ?? 0),
            'never_chased' => (int) ($row->never_chased ?? 0),
            'oldest_days' => (int) ($row->oldest_days ?? 0),
            'collected_30d' => round($collected30, 3),
        ];
    }

    /**
     * One row per insurer: what they owe, how old it is, and how to reach them.
     * This is the "who do I call today" half of the page.
     */
    protected function insurerRows(?int $branchId): array
    {
        $rows = $this->openClaims($branchId)
            ->selectRaw('insurers.id as insurer_id, insurers.name, insurers.name_ar, insurers.code')
            ->selectRaw('insurers.contact_email, insurers.contact_phone, insurers.payment_terms_days')
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw('COALESCE(SUM('.self::BALANCE_SQL.'), 0) as outstanding')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::AGE_SQL.' <= 30 THEN '.self::BALANCE_SQL.' ELSE 0 END), 0) as b0')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::AGE_SQL.' BETWEEN 31 AND 60 THEN '.self::BALANCE_SQL.' ELSE 0 END), 0) as b31')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::AGE_SQL.' BETWEEN 61 AND 90 THEN '.self::BALANCE_SQL.' ELSE 0 END), 0) as b61')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::AGE_SQL.' > 90 THEN '.self::BALANCE_SQL.' ELSE 0 END), 0) as b90')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::OVERDUE_SQL.' THEN '.self::BALANCE_SQL.' ELSE 0 END), 0) as overdue_amount')
            ->selectRaw("SUM(CASE WHEN insurance_claims.status = 'draft' THEN 1 ELSE 0 END) as draft_count")
            ->selectRaw("SUM(CASE WHEN insurance_claims.status IN ('submitted','under_review') THEN 1 ELSE 0 END) as waiting_count")
            ->selectRaw("SUM(CASE WHEN insurance_claims.status IN ('approved','partially_approved') THEN 1 ELSE 0 END) as unpaid_count")
            ->selectRaw('SUM(CASE WHEN insurance_claims.follow_up_at IS NOT NULL AND insurance_claims.follow_up_at <= CURDATE() THEN 1 ELSE 0 END) as due_count')
            ->selectRaw('MAX('.self::AGE_SQL.') as oldest_days')
            ->selectRaw('MAX(insurance_claims.last_chased_at) as last_chased_at')
            ->groupBy('insurers.id', 'insurers.name', 'insurers.name_ar', 'insurers.code', 'insurers.contact_email', 'insurers.contact_phone', 'insurers.payment_terms_days')
            ->orderByRaw('SUM('.self::BALANCE_SQL.') DESC')
            ->get();

        return $rows->map(fn ($r) => [
            'insurer_id' => (int) $r->insurer_id,
            'name' => $r->name,
            'name_ar' => $r->name_ar,
            'code' => $r->code,
            'contact_email' => $r->contact_email,
            'contact_phone' => $r->contact_phone,
            'payment_terms_days' => (int) ($r->payment_terms_days ?: 30),
            'open_count' => (int) $r->open_count,
            'outstanding' => round((float) $r->outstanding, 3),
            'aging' => [
                'b0' => round((float) $r->b0, 3),
                'b31' => round((float) $r->b31, 3),
                'b61' => round((float) $r->b61, 3),
                'b90' => round((float) $r->b90, 3),
            ],
            'overdue_amount' => round((float) $r->overdue_amount, 3),
            'draft_count' => (int) $r->draft_count,
            'waiting_count' => (int) $r->waiting_count,
            'unpaid_count' => (int) $r->unpaid_count,
            'due_count' => (int) $r->due_count,
            'oldest_days' => (int) $r->oldest_days,
            'last_chased_at' => $r->last_chased_at,
        ])->all();
    }

    /** Apply a worklist tab to the query. Tabs are plain-language buckets. */
    protected function applyTab($query, string $tab)
    {
        return match ($tab) {
            // Late by the insurer's own terms, or we scheduled a chase for today.
            'chase_now' => $query->where(function ($w) {
                $w->whereRaw(self::OVERDUE_SQL)
                    ->orWhereRaw('insurance_claims.follow_up_at IS NOT NULL AND insurance_claims.follow_up_at <= CURDATE()');
            }),
            'scheduled' => $query->whereRaw('insurance_claims.follow_up_at IS NOT NULL AND insurance_claims.follow_up_at > CURDATE()'),
            'unsubmitted' => $query->where('insurance_claims.status', InsuranceClaim::STATUS_DRAFT),
            'waiting' => $query->whereIn('insurance_claims.status', [InsuranceClaim::STATUS_SUBMITTED, InsuranceClaim::STATUS_UNDER_REVIEW]),
            'unpaid' => $query->whereIn('insurance_claims.status', [InsuranceClaim::STATUS_APPROVED, InsuranceClaim::STATUS_PARTIALLY_APPROVED]),
            default => $query,
        };
    }

    /** The claim-level worklist under the insurer table. */
    protected function worklist(string $tab, string $sort, ?int $insurerId, ?int $branchId, string $q)
    {
        $query = $this->openClaims($branchId)
            ->select('insurance_claims.*')
            ->selectRaw(self::BALANCE_SQL.' as balance_due')
            ->selectRaw(self::AGE_SQL.' as age_days')
            ->selectRaw(self::TERMS_SQL.' as terms_days')
            ->selectRaw('insurers.name as insurer_name')
            ->selectRaw('insurers.id as insurer_id')
            ->with(['patientPolicy.patient:id,name,phone', 'branch:id,name']);

        if ($insurerId) {
            $query->where('insurers.id', $insurerId);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('insurance_claims.claim_number', 'like', "%{$q}%")
                    ->orWhere('insurance_claims.reference_no', 'like', "%{$q}%")
                    ->orWhereHas('patientPolicy.patient', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        $this->applyTab($query, $tab);

        match ($sort) {
            'outstanding' => $query->orderByRaw(self::BALANCE_SQL.' DESC'),
            'due' => $query->orderByRaw('insurance_claims.follow_up_at IS NULL, insurance_claims.follow_up_at ASC'),
            default => $query->orderByRaw(self::AGE_SQL.' DESC'),
        };
        $query->orderBy('insurance_claims.id');

        $page = $query->paginate(20)->withQueryString();

        $page->getCollection()->transform(function (InsuranceClaim $c) {
            $c->setAttribute('patient_name', $c->patientPolicy?->patient?->name);
            $c->setAttribute('patient_phone', $c->patientPolicy?->patient?->phone);
            $c->setAttribute('branch_name', $this->branchName($c->branch));
            $c->setAttribute('is_overdue', (int) $c->age_days > (int) $c->terms_days);
            // Send the follow-up date as a plain Y-m-d string. The `date` cast
            // serialises to UTC, which reads a day early in a UTC+3 browser.
            $c->setAttribute('follow_up_on', optional($c->follow_up_at)->toDateString());
            $c->setAttribute('chases', array_slice(array_reverse((array) ($c->meta['chases'] ?? [])), 0, 5));
            $c->unsetRelation('patientPolicy')->unsetRelation('branch');

            return $c;
        });

        return $page;
    }

    /** Counts for the worklist tabs — same scope as the list itself. */
    protected function tabCounts(?int $insurerId, ?int $branchId): array
    {
        $counts = [];
        foreach (self::TABS as $tab) {
            $query = $this->openClaims($branchId);
            if ($insurerId) {
                $query->where('insurers.id', $insurerId);
            }
            $counts[$tab] = (int) $this->applyTab($query, $tab)->count('insurance_claims.id');
        }

        return $counts;
    }

    protected function branchOptions(): array
    {
        $ids = InsuranceClaim::query()->whereNotNull('branch_id')->distinct()->pluck('branch_id')->all();

        return \App\Models\Branch::query()
            ->whereIn('id', $ids)->get(['id', 'name'])
            ->map(fn ($b) => ['value' => $b->id, 'label' => $this->branchName($b)])
            ->sortBy('label')->values()->all();
    }

    /** Branch names are translatable arrays/JSON; resolve to the current locale. */
    protected function branchName($branch): ?string
    {
        if (! $branch) {
            return null;
        }
        $name = $branch->name;
        if (is_array($name)) {
            return $name[app()->getLocale()] ?? $name['en'] ?? reset($name) ?: null;
        }

        return $name;
    }

    /**
     * Record a chase against one claim: stamp when, by whom, what was said, and
     * when to come back to it. History is appended to meta.chases[].
     */
    public function chase(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canChase($request)) {
            abort(403, 'Not authorized to record insurance follow-ups.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'channel' => ['nullable', 'string', 'in:call,email,whatsapp,portal,visit,other'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $meta = (array) ($claim->meta ?? []);
        $chases = (array) ($meta['chases'] ?? []);
        $chases[] = [
            'at' => now()->toDateTimeString(),
            'by' => $request->user()?->name,
            'by_id' => $request->user()?->id,
            'channel' => $data['channel'] ?? 'other',
            'note' => $data['note'] ?? null,
            'next' => $data['follow_up_at'] ?? null,
        ];
        // Keep the trail bounded — the last 50 chases is plenty of history.
        $meta['chases'] = array_slice($chases, -50);

        $claim->forceFill([
            'meta' => $meta,
            'last_chased_at' => now(),
            'chase_count' => (int) $claim->chase_count + 1,
            'follow_up_at' => $data['follow_up_at'] ?? null,
            'follow_up_note' => $data['note'] ?? null,
        ])->save();

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Follow-up logged on {$claim->claim_number}.",
        ]);
    }

    /**
     * Group the selected claims by insurer, ready for a bulk email: who would
     * be written to, how much it covers, and which insurers can't be reached
     * because nobody recorded a claims address. Read-only — the confirm step.
     */
    public function emailPreview(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'claim_ids' => ['required', 'array', 'min:1', 'max:500'],
            'claim_ids.*' => ['integer'],
        ]);

        $groups = $this->groupClaimsByInsurer($data['claim_ids']);

        return response()->json([
            'groups' => array_values(array_map(fn ($g) => [
                'insurer_id' => $g['insurer']->id,
                'insurer_name' => $g['insurer']->name,
                // Whose letterhead this statement goes out on.
                'clinic_name' => $this->clinicProfile($g['branch_ids'] ?? [])['name'],
                'to_email' => $g['insurer']->contact_email,
                'claim_count' => count($g['claims']),
                'claim_numbers' => array_column($g['claims'], 'claim_number'),
                'outstanding' => round(array_sum(array_column($g['claims'], 'outstanding')), 3),
                'oldest_days' => (int) max(array_column($g['claims'], 'age_days') ?: [0]),
            ], $groups)),
            // Deliverable groups only — the UI greys out the rest.
            'sendable' => count(array_filter($groups, fn ($g) => filled($g['insurer']->contact_email))),
            'redirect_to' => config('clinic.insurance_email_redirect_to'),
        ]);
    }

    /**
     * Send one follow-up email per insurer covering the selected claims, log
     * exactly what left, and record a chase (channel=email) on every claim
     * included — so the board's "never chased" and last-chased columns reflect
     * the email without anyone re-typing it.
     *
     * A per-insurer failure is caught and logged, never aborting the rest: one
     * bad address must not cost the other four insurers their reminder.
     */
    public function email(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canChase($request)) {
            abort(403, 'Not authorized to send insurance follow-ups.');
        }

        $data = $request->validate([
            'claim_ids' => ['required', 'array', 'min:1', 'max:500'],
            'claim_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $groups = $this->groupClaimsByInsurer($data['claim_ids']);
        if (empty($groups)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'None of the selected claims are still open.']);
        }

        $redirectTo = config('clinic.insurance_email_redirect_to');
        $branchId = (int) $request->input('branch', 0) ?: null;
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $claimsChased = 0;

        foreach ($groups as $group) {
            $insurer = $group['insurer'];
            $claims = $group['claims'];

            if (blank($insurer->contact_email)) {
                $skipped++;

                continue;
            }

            $totals = $this->statementTotals($claims, $insurer);
            $reference = $this->statementReference($insurer);

            $mail = new \App\Mail\InsurerFollowUpMail(
                ['name' => $insurer->name, 'code' => $insurer->code],
                $claims,
                $totals,
                $this->clinicProfile($group['branch_ids'] ?? []),
                ['name' => $request->user()?->name, 'role' => 'Insurance Collections'],
                $reference,
                $data['note'] ?? null,
            );

            $log = new \App\Models\Insurance\InsuranceFollowUpEmail([
                'insurer_id' => $insurer->id,
                'branch_id' => $branchId,
                'to_email' => $insurer->contact_email,
                'redirected_to' => $redirectTo,
                'subject' => $mail->envelope()->subject,
                'note' => $data['note'] ?? null,
                'claim_ids' => array_column($claims, 'id'),
                'claim_numbers' => array_column($claims, 'claim_number'),
                'claim_count' => $totals['count'],
                'total_outstanding' => $totals['outstanding'],
                'sent_by_user_id' => $request->user()?->id,
                'meta' => ['mailer' => config('mail.default'), 'reference' => $reference],
            ]);

            try {
                $sentMessage = \Illuminate\Support\Facades\Mail::to($redirectTo ?: $insurer->contact_email)->send($mail);

                $log->status = \App\Models\Insurance\InsuranceFollowUpEmail::STATUS_SENT;
                $log->sent_at = now();
                // Keep the Message-ID: an insurer replying with In-Reply-To can
                // then be threaded back to this statement automatically.
                $log->meta = array_merge((array) $log->meta, [
                    'message_id' => trim((string) $sentMessage?->getMessageId(), '<> ') ?: null,
                ]);
                $log->save();
                $sent++;

                $claimsChased += $this->recordEmailChase($claims, $insurer, $request, $data, $log->id);
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                // Recording the failure must not itself fail the request — the
                // operator still needs the summary of what did go out.
                try {
                    $log->status = \App\Models\Insurance\InsuranceFollowUpEmail::STATUS_FAILED;
                    $log->error = mb_substr($e->getMessage(), 0, 2000);
                    $log->save();
                } catch (\Throwable $inner) {
                    report($inner);
                }
            }
        }

        $parts = [];
        if ($sent) {
            $parts[] = "Emailed {$sent} insurer(s) covering {$claimsChased} claim(s).";
        }
        if ($failed) {
            $parts[] = "{$failed} failed — see the email log.";
        }
        if ($skipped) {
            $parts[] = "{$skipped} skipped (no claims email on file).";
        }

        return back()->with('flash', [
            'type' => $sent ? ($failed || $skipped ? 'warning' : 'success') : 'error',
            'message' => $parts ? implode(' ', $parts) : 'Nothing was sent.',
        ]);
    }

    /**
     * Record what the insurer said back to a statement we sent.
     *
     * The reply is stored against the send it answers — that row already knows
     * which claims were listed, so one reply resolves the whole statement. When
     * they commit to a date, every claim still open on that statement is
     * re-scheduled to it, which is the only automatic change: the board should
     * resurface those claims on the day the money was promised, not before.
     */
    public function recordReply(Request $request, \App\Models\Insurance\InsuranceFollowUpEmail $email): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canChase($request)) {
            abort(403, 'Not authorized to record insurance follow-ups.');
        }

        $data = $request->validate([
            'reply_outcome' => ['required', 'string', Rule::in(\App\Models\Insurance\InsuranceFollowUpEmail::OUTCOMES)],
            'replied_at' => ['required', 'date', 'before_or_equal:today'],
            'reply_note' => ['nullable', 'string', 'max:2000'],
            'promised_payment_date' => ['nullable', 'date', 'after_or_equal:today'],
            // They cannot promise more than the statement asked for.
            'promised_amount' => ['nullable', 'numeric', 'min:0', 'max:'.number_format((float) $email->total_outstanding, 3, '.', '')],
        ], [
            'promised_amount.max' => 'The promised amount cannot exceed the '.number_format((float) $email->total_outstanding, 3).' KWD on this statement.',
        ]);

        $email->forceFill([
            'reply_outcome' => $data['reply_outcome'],
            'replied_at' => $data['replied_at'],
            'reply_note' => $data['reply_note'] ?? null,
            'promised_payment_date' => $data['promised_payment_date'] ?? null,
            'promised_amount' => $data['promised_amount'] ?? null,
            'reply_recorded_by_user_id' => $request->user()?->id,
            'reply_recorded_at' => now(),
        ])->save();

        // Re-schedule only the claims from this statement that are still open —
        // one already paid since must not be dragged back onto the worklist.
        $rescheduled = 0;
        if (! empty($data['promised_payment_date'])) {
            $rescheduled = InsuranceClaim::query()
                ->whereIn('id', (array) ($email->claim_ids ?? []))
                ->whereIn('status', self::OPEN_STATUSES)
                ->update(['follow_up_at' => $data['promised_payment_date']]);
        }

        $message = 'Reply recorded for '.($email->insurer?->name ?? 'insurer').'.';
        if ($rescheduled) {
            $message .= " {$rescheduled} claim(s) rescheduled to ".$data['promised_payment_date'].'.';
        }

        return back()->with('flash', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Pull the mailbox now rather than waiting for the scheduled poll — the
     * "any word from them yet?" button. Import failures are reported as a flash
     * rather than an exception: an unreachable mailbox is an operational fact,
     * not a crash.
     */
    public function checkReplies(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canChase($request)) {
            abort(403, 'Not authorized to fetch insurance replies.');
        }

        try {
            $stats = app(\App\Services\Insurance\InsurerReplyImporter::class)->run();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('flash', ['type' => 'error', 'message' => 'Could not read the mailbox: '.$e->getMessage()]);
        }

        $message = $stats['imported']
            ? "{$stats['imported']} new reply(s) — {$stats['matched']} matched to a statement, {$stats['unmatched']} unmatched."
            : 'No new replies.';

        return back()->with('flash', [
            'type' => $stats['unmatched'] ? 'warning' : 'success',
            'message' => $message,
        ]);
    }

    /**
     * Demo aid: stage an insurer reply and import it in one click.
     *
     * Gated on clinic.insurance_replies.demo_enabled so it cannot exist in a
     * live clinic — a button that invents insurer correspondence has no place
     * near real collections. Everything past the drop is the genuine path: the
     * file is parsed, matched and filed like any other inbound message.
     */
    public function simulateReply(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canChase($request)) {
            abort(403, 'Not authorized to record insurance follow-ups.');
        }
        abort_unless((bool) config('clinic.insurance_replies.demo_enabled', false), 404);

        $data = $request->validate([
            'statement_id' => ['nullable', 'integer'],
            'tone' => ['nullable', 'string', Rule::in(\App\Services\Insurance\SimulatedReplyFactory::TONES)],
        ]);

        $statement = ! empty($data['statement_id'])
            ? \App\Models\Insurance\InsuranceFollowUpEmail::find($data['statement_id'])
            : \App\Models\Insurance\InsuranceFollowUpEmail::query()
                ->where('status', \App\Models\Insurance\InsuranceFollowUpEmail::STATUS_SENT)
                ->whereNull('reply_outcome')->latest('id')->first();

        if (! $statement) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No sent statement is awaiting a reply — send one first.',
            ]);
        }

        app(\App\Services\Insurance\SimulatedReplyFactory::class)
            ->create($statement, $data['tone'] ?? 'promise');

        // Import straight away: in a meeting nobody wants a second click, and
        // the import is the same one the button and the cron run.
        $stats = app(\App\Services\Insurance\InsurerReplyImporter::class)->run();

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Reply from {$statement->to_email} received — ".
                "{$stats['matched']} matched to a statement.",
        ]);
    }

    /** JSON: what we have emailed insurers, newest first (the log drawer). */
    public function emailLog(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $rows = \App\Models\Insurance\InsuranceFollowUpEmail::query()
            ->with(['insurer:id,name', 'sentBy:id,name', 'replyRecordedBy:id,name'])
            ->when((int) $request->input('insurer', 0), fn ($q, $id) => $q->where('insurer_id', $id))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // Inbound messages filed against those statements, oldest first so a
        // thread reads top to bottom.
        $inbound = \App\Models\Insurance\InsuranceFollowUpEmailReply::query()
            ->whereIn('followup_email_id', $rows->pluck('id'))
            ->orderBy('received_at')
            ->get()
            ->groupBy('followup_email_id');

        // Replies nobody could file — a separate work item on the drawer.
        $unmatched = \App\Models\Insurance\InsuranceFollowUpEmailReply::query()
            ->with('insurer:id,name')
            ->where('status', \App\Models\Insurance\InsuranceFollowUpEmailReply::STATUS_UNMATCHED)
            ->orderByDesc('received_at')->limit(25)->get();

        return response()->json([
            'rows' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'insurer_name' => $r->insurer?->name,
                'to_email' => $r->to_email,
                'redirected_to' => $r->redirected_to,
                'subject' => $r->subject,
                'note' => $r->note,
                'claim_numbers' => $r->claim_numbers ?? [],
                'claim_count' => (int) $r->claim_count,
                'total_outstanding' => (float) $r->total_outstanding,
                'status' => $r->status,
                'error' => $r->error,
                'sent_by' => $r->sentBy?->name,
                'sent_at' => optional($r->sent_at)->toDateTimeString(),
                'created_at' => optional($r->created_at)->toDateTimeString(),
                // The insurer's side of the exchange.
                'awaiting_reply' => $r->awaitingReply(),
                'reply_outcome' => $r->reply_outcome,
                'replied_at' => optional($r->replied_at)->toDateString(),
                'reply_note' => $r->reply_note,
                'promised_payment_date' => optional($r->promised_payment_date)->toDateString(),
                'promised_amount' => $r->promised_amount !== null ? (float) $r->promised_amount : null,
                'reply_by' => $r->replyRecordedBy?->name,
                // Messages that actually arrived from the insurer.
                'inbound' => ($inbound[$r->id] ?? collect())->map(fn ($m) => [
                    'id' => $m->id,
                    'from_email' => $m->from_email,
                    'from_name' => $m->from_name,
                    'subject' => $m->subject,
                    'body_text' => $m->body_text,
                    'received_at' => optional($m->received_at)->toDateTimeString(),
                    'matched_by' => $m->matched_by,
                    'source' => $m->source,
                ])->values(),
            ])->values(),
            'unmatched' => $unmatched->map(fn ($m) => [
                'id' => $m->id,
                'from_email' => $m->from_email,
                'insurer_name' => $m->insurer?->name,
                'subject' => $m->subject,
                'body_text' => $m->body_text,
                'received_at' => optional($m->received_at)->toDateTimeString(),
            ])->values(),
        ]);
    }

    /**
     * Resolve claim ids into buckets, skipping anything no longer open (a claim
     * paid while the page sat idle must not be chased).
     *
     * Buckets are per insurer AND per clinic group: the statement goes out on
     * one licensed entity's letterhead, so an insurer owed money by two groups
     * gets two statements rather than one letter that misattributes half its
     * lines. Single-clinic installs never notice the difference.
     *
     * @return array<string, array{insurer:\App\Models\Insurance\Insurer, claims:array<int, array>, branch_ids:array<int, int>}>
     */
    protected function groupClaimsByInsurer(array $claimIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $claimIds))));
        if (empty($ids)) {
            return [];
        }

        $rows = $this->openClaims()
            ->whereIn('insurance_claims.id', $ids)
            ->select('insurance_claims.*')
            ->selectRaw(self::BALANCE_SQL.' as balance_due')
            ->selectRaw(self::AGE_SQL.' as age_days')
            ->selectRaw('insurers.id as insurer_id')
            ->with(['patientPolicy.patient:id,name', 'patientPolicy.insurer'])
            ->get();

        // Which clinic group each branch belongs to, in one lookup.
        $partnerByBranch = \App\Models\Branch::query()->withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('branch_id')->filter()->unique()->all())
            ->pluck('partner_id', 'id');

        $groups = [];
        foreach ($rows as $claim) {
            $insurer = $claim->patientPolicy?->insurer;
            if (! $insurer) {
                continue;
            }

            $partnerId = (int) ($partnerByBranch[$claim->branch_id] ?? 0);
            $key = $insurer->id.':'.$partnerId;

            $groups[$key] ??= ['insurer' => $insurer, 'claims' => [], 'branch_ids' => []];
            if ($claim->branch_id) {
                $groups[$key]['branch_ids'][] = (int) $claim->branch_id;
            }
            $groups[$key]['claims'][] = [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'patient' => $claim->patientPolicy?->patient?->name,
                'submitted' => optional($claim->submitted_at)->toDateString(),
                'age_days' => (int) $claim->age_days,
                'outstanding' => round((float) $claim->balance_due, 3),
                'status' => $claim->status,
            ];
        }

        return $groups;
    }

    /**
     * Statement figures for one insurer: the total, the age of the oldest item,
     * their agreed terms, and the aging split the insurer's own AP team will
     * want to reconcile against.
     */
    protected function statementTotals(array $claims, $insurer): array
    {
        $aging = ['b0' => 0.0, 'b31' => 0.0, 'b61' => 0.0, 'b90' => 0.0];
        foreach ($claims as $c) {
            $bucket = match (true) {
                $c['age_days'] <= 30 => 'b0',
                $c['age_days'] <= 60 => 'b31',
                $c['age_days'] <= 90 => 'b61',
                default => 'b90',
            };
            $aging[$bucket] += (float) $c['outstanding'];
        }

        return [
            'count' => count($claims),
            'outstanding' => round(array_sum(array_column($claims, 'outstanding')), 3),
            'oldest_days' => (int) max(array_column($claims, 'age_days') ?: [0]),
            'terms_days' => (int) ($insurer->payment_terms_days ?: 30),
            'aging' => array_map(fn ($v) => round($v, 3), $aging),
        ];
    }

    /** Quotable reference so the insurer can cite this statement in their reply. */
    protected function statementReference($insurer): string
    {
        return 'AR-'.now()->format('Ymd').'-'.strtoupper($insurer->code ?: 'INS'.$insurer->id);
    }

    /**
     * Who the letter is from. Insurer correspondence goes out under the clinic's
     * own identity — the licensed entity that treated the patient — not the
     * app name, so it is resolved from the partner behind the claims' branches
     * with the branch supplying the reachable contact details.
     */
    protected function clinicProfile(array $branchIds): array
    {
        $branch = \App\Models\Branch::query()->withoutGlobalScopes()
            ->when($branchIds, fn ($q) => $q->whereIn('id', array_unique($branchIds)))
            ->orderByDesc('is_hub')->orderBy('id')
            ->first();

        $partner = $branch?->partner_id
            ? \App\Models\Partner::query()->find($branch->partner_id)
            : null;

        $name = $this->localizedValue($partner?->name)
            ?: $this->branchName($branch)
            ?: config('app.name');

        return [
            'name' => $name,
            'license' => $partner?->license_number ?: $branch?->license_number,
            'email' => $branch?->email ?: $partner?->email ?: config('mail.from.address'),
            'phone' => $branch?->phone,
            'address' => $this->localizedValue($branch?->address),
            'website' => $partner?->website,
        ];
    }

    /** Translatable columns arrive as arrays or JSON strings; resolve to a string. */
    protected function localizedValue($value): ?string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $value = json_decode($value, true);
        }
        if (is_array($value)) {
            return $value[app()->getLocale()] ?? $value['en'] ?? (reset($value) ?: null);
        }

        return $value ? (string) $value : null;
    }

    /**
     * Stamp the emailed claims as chased, with the same shape the manual
     * "Log chase" action writes, plus a pointer back to the email log row.
     */
    protected function recordEmailChase(array $claims, $insurer, Request $request, array $data, int $logId): int
    {
        $note = $data['note'] ?? null;
        $summary = 'Follow-up email sent to '.$insurer->contact_email
            .($note ? ' — '.mb_substr($note, 0, 200) : '');

        $count = 0;
        foreach (InsuranceClaim::query()->whereIn('id', array_column($claims, 'id'))->get() as $claim) {
            $meta = (array) ($claim->meta ?? []);
            $chases = (array) ($meta['chases'] ?? []);
            $chases[] = [
                'at' => now()->toDateTimeString(),
                'by' => $request->user()?->name,
                'by_id' => $request->user()?->id,
                'channel' => 'email',
                'note' => $summary,
                'next' => $data['follow_up_at'] ?? null,
                'email_log_id' => $logId,
            ];
            $meta['chases'] = array_slice($chases, -50);

            $claim->forceFill([
                'meta' => $meta,
                'last_chased_at' => now(),
                'chase_count' => (int) $claim->chase_count + 1,
                'follow_up_at' => $data['follow_up_at'] ?? $claim->follow_up_at,
            ])->save();
            $count++;
        }

        return $count;
    }

    /** Set/clear only the next-chase date (the snooze buttons on a row). */
    public function snooze(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canChase($request)) {
            abort(403, 'Not authorized to record insurance follow-ups.');
        }

        $data = $request->validate([
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $claim->forceFill(['follow_up_at' => $data['follow_up_at'] ?? null])->save();

        return back()->with('flash', [
            'type' => 'success',
            'message' => $data['follow_up_at']
                ? "Next follow-up set for {$data['follow_up_at']}."
                : 'Follow-up date cleared.',
        ]);
    }

    /** JSON: the chase history for one claim (drawer on the worklist). */
    public function history(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $this->authorizeAccess($request);

        $claim->loadMissing(['patientPolicy.patient:id,name,phone', 'patientPolicy.insurer:id,name', 'payments']);

        return response()->json([
            'claim' => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status' => $claim->status,
                'patient_name' => $claim->patientPolicy?->patient?->name,
                'insurer_name' => $claim->patientPolicy?->insurer?->name,
                'total_charged' => (float) $claim->total_charged,
                'insurer_payable' => (float) $claim->insurer_payable,
                'paid_amount' => (float) $claim->paid_amount,
                'balance_due' => $claim->balanceDue(),
                'submitted_at' => optional($claim->submitted_at)->toDateTimeString(),
                'follow_up_at' => optional($claim->follow_up_at)->toDateString(),
                'last_chased_at' => optional($claim->last_chased_at)->toDateTimeString(),
                'chase_count' => (int) $claim->chase_count,
                'reference_no' => $claim->reference_no,
            ],
            'chases' => array_reverse((array) ($claim->meta['chases'] ?? [])),
            'payments' => $claim->payments->map(fn ($p) => [
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'reference_no' => $p->reference_no,
                'paid_at' => optional($p->paid_at)->toDateString(),
            ])->values(),
        ]);
    }

    /** Styled .xlsx of the current worklist — the chase sheet, for the insurer. */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);

        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'chase_now';
        $insurerId = (int) $request->input('insurer', 0) ?: null;
        $branchId = (int) $request->input('branch', 0) ?: null;

        $query = $this->openClaims($branchId)
            ->select('insurance_claims.*')
            ->selectRaw(self::BALANCE_SQL.' as balance_due')
            ->selectRaw(self::AGE_SQL.' as age_days')
            ->selectRaw('insurers.name as insurer_name')
            ->with(['patientPolicy.patient:id,name']);

        if ($insurerId) {
            $query->where('insurers.id', $insurerId);
        }
        $this->applyTab($query, $tab);
        $query->orderByRaw(self::AGE_SQL.' DESC');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query,
                ['Claim #', 'Insurer', 'Patient', 'Sent on', 'Days open', 'Billed', 'Insurer owes', 'Paid', 'Outstanding', 'Status', 'Last chased', 'Next follow-up', 'Note'],
                fn ($c) => [
                    $c->claim_number,
                    $c->insurer_name,
                    $c->patientPolicy?->patient?->name,
                    optional($c->submitted_at)->toDateString(),
                    (int) $c->age_days,
                    number_format((float) $c->total_charged, 3, '.', ''),
                    number_format((float) $c->insurer_payable, 3, '.', ''),
                    number_format((float) $c->paid_amount, 3, '.', ''),
                    number_format((float) $c->balance_due, 3, '.', ''),
                    $c->status,
                    optional($c->last_chased_at)->toDateString(),
                    optional($c->follow_up_at)->toDateString(),
                    $c->follow_up_note,
                ],
                'Insurance Follow-up',
                app()->getLocale() === 'ar',
            ),
            'insurance-follow-up-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
