# Clinic v2 — Phase work progress (Phases 1–7)

Checkpoint of the `whatsapp-module` branch. All seven phases below were built,
tested, and reviewer-cleared. Suite at checkpoint: **227 passing**.

> Context note: most work lives in `app/Http/Controllers/V2/*`,
> `app/Services/Clinic/*`, `app/Services/Insurance/*`, and
> `resources/js/v2/*` (Inertia/Vue). Verify against current code — the
> `~/.claude/.../memory/*.md` notes (local to the office machine) carry the
> deeper design rationale.

## Phase 1 — Stock fixes ✅
- Qty stepper bug (`step="0.0001"` → integer/`consume_step`); receive-stock item
  search deduped + branch-scoped; base-units prefilled from stock-units via
  `conversion_factor`; package-count-0 (`cache: 'no-store'` on visit reload);
  fulfill list shows patient name not bare code.
- Hardening: server-side `itemFitsBranch()` guard (active + stockable + branch
  clinic) on receive/store.
- Files: `ClinicStockController`, `ClinicStock/Index.vue`, `VisitSheet.vue`,
  `VisitStockRequestsController`, `VisitStockRequests/Index.vue`.

## Phase 2 — Waiting cards + modal refresh ✅
- Richer waiting cards (phone, paid/unpaid, insurance chip, discount) computed in
  bulk (no N+1). `VisitSheet` refetches on window focus/visibility; wired missing
  `@changed` on `Patients/Profile.vue`.
- Files: `WaitingPatientsController`, `WaitingPatients/Index.vue`, `VisitSheet.vue`.

## Phase 3 — Patient identity (reception confirms match) ✅
- Phone match + different name → flag for review; at check-in reception confirms
  (keep) or splits (new patient, repoint booking + visit). Dropped the blocking
  `patients_partner_id_phone_unique` index (migration) so two people can share a
  reassigned number; lookup matches most-recent patient.
- Files: `BookingService`, `CheckinController`, `PatientsController`,
  `CheckinModal.vue`, migration `*_drop_unique_partner_phone_on_patients`.

## Phase 4 — Payment (MyFatoorah) ✅
- Per-clinic admin-configured methods (`clinic_payment_methods` + resolver +
  Filament resource); card/online require a transaction/reference id; MyFatoorah
  payment-link for the outstanding balance + server-rendered QR in `VisitSheet`;
  send link to WhatsApp; secure visit finalizer records the payment on gateway
  confirm. MyFatoorah demo/sandbox token already on the seeded `GatewayAccount`s.
- Files: `VisitConsoleController`, `VisitSheet.vue`, `ClinicPaymentMethod*`,
  `VisitPaymentLinkService`, `MyFatoorahService`, `PaymentCallbackController`.

## Phase 5 — Orphaned stock requests ✅
- Removing a package reconciles its consumables out of the pending stock request
  (subtract-based, since one request merges several sources); cancels + flags
  "Package removed" when nothing remains.
- Files: `VisitPackageService`, `VisitStockRequestService`, `VisitConsoleController`,
  `VisitStockRequests/Index.vue`.

## Phase 6 — Insurance UX ✅ (over existing backend)
- Reception captures civil id + insurer/plan/policy from the visit modal
  (`attachInsurance`); visual coverage builder in the banner (covers %, copay,
  already-paid); Claims page "draft from visit" redesigned to a searchable visit
  picker + coverage preview. Shared claimable-visit guard (awaiting_payment /
  completed, non-zero charges); attach rejects inactive insurer/plan.
- Files: `ClaimsController`, `Claims/Index.vue`, `VisitConsoleController`,
  `VisitSheet.vue`.

## Phase 7 — Follow-up auto-booking ✅
- Doctor sets a follow-up date → auto-books the first FREE slot that day via
  `AvailabilityService::timesFor`; `needs_scheduling` if the doctor is off / fully
  booked. Re-sync preserves a still-valid booking; reschedule releases the stale
  one. Config: `follow_up_auto_create_booking_default => true`,
  `follow_up_booking_status => 'pending'` (so the slot is actually held).
- Files: `FollowUpService`, `config/clinic.php`, `VisitObserver`.

## Tests added this work
`tests/Feature/V2/VisitDischargeTest`, `CheckinIdentityTest`, `VisitPaymentTest`,
`VisitInsuranceCaptureTest`; `tests/Feature/Insurance/ClaimDraftPickerTest`;
`tests/Feature/Clinic/FollowUpAutoBookingTest`; plus `VisitModelTest` +
`VisitStockRequestServiceTest` additions.
