# Independent clinic UX competitor review

Research date: 2026-09-13.

## Scope and evidence

Independent review of official workflow documentation from Jane, Cliniko,
CareStack, Zenoti, and Fresha, compared with this repository. Claude's report
was not used as evidence for the findings below.

- Documented behavior: official help articles describing specific actions.
- Visual evidence: Jane's official Day View screenshot was downloaded and
  inspected. It visibly shows October 2019; it supports the layout pattern,
  not a claim about current visual styling.
- Repository evidence: source inspection, not an authenticated usability test.
- Recommendations: design judgments to validate with our clinics.

No competitor account was created, no authenticated product was operated,
and no customer interviews were conducted. Jane's demo requires a weekly
password obtained through its customer/community channels. Vendor help pages
are primary evidence of documented workflows, not independent proof of speed,
satisfaction, adoption, or business outcomes. Guide freshness varies.

Jane and Cliniko inform general practice workflows; CareStack informs dental
charting. Zenoti and Fresha are adjacent service-business references for
reception and checkout, not templates for clinical documentation.

## Findings

### 1. Keep the day's work beside the patient record

Jane documents a practitioner Day View with a daily schedule, chart access,
and an appointment panel for arrival and payment. Its dashboard is optional.
The published screenshot shows a narrow schedule at left, patient charts in
the center, and appointment actions at right. The useful pattern is retained
context while working on a patient, not a particular sidebar color.

Source: [Jane charting training](https://jane.app/guide/lesson-2-charting-basics).

Recommendation: prototype a doctor workspace that keeps the personal queue
available while opening the selected visit. On smaller screens, switch between
queue and visit while preserving selection, scroll position, and unsaved work.
Do not squeeze three desktop panes onto a phone.

### 2. Make unfinished documentation visible

Jane distinguishes signed, drafted, and never-started appointment charts and
provides a route from the practitioner dashboard to unfinished entries.
Cliniko's completion report identifies appointments without finalized notes
and notes not linked to appointments. These are documented examples of
showing actionable unfinished work.

Sources: [Jane charting status](https://jane.app/guide/appointment-charting-status),
[Cliniko note completion](https://help.cliniko.com/en/articles/9016974-see-which-appointments-have-completed-treatment-notes).

Recommendation: distinguish treatment progress from documentation completion.
An appointment can finish while a note remains unfinished. First inspect the
existing backend model and agree on what constitutes a complete note; a
nonempty text field or a successful save must not silently mean signed.

### 3. Simplify arrival without losing the handoff

Cliniko documents opening an appointment, selecting Arrived, showing an
arrival indicator, and notifying the practitioner with a sound.

Source: [Cliniko check-in](https://help.cliniko.com/en/articles/1024062-checking-a-patient-in).

Recommendation: make check-in an obvious action from the daily worklist and
show that it succeeded. Preserve required room, payment, or package checks
where our workflow requires them. Inspect existing notification behavior
before adding another alert channel.

### 4. Let permissions support different operating models

Cliniko documents separate scheduling, reception, practitioner, bookkeeping,
and administration roles. Practitioner financial access can be restricted;
it is not universally absent. Jane also documents payment access in its
practitioner Day View.

Sources: [Cliniko roles](https://help.cliniko.com/en/articles/1087327-user-security-roles),
[Jane charting training](https://jane.app/guide/lesson-2-charting-basics).

Recommendation: prioritize tasks by role and actual permissions. A doctor
who also runs a small clinic may need payment access. Keep authorization on
the server; hiding a navigation item is only a presentation decision.

### 5. Give appointment status a consistent meaning

Fresha documents status visibility in the calendar, appointment list, and
client profile. A status update immediately changes its calendar color.
Its Completed status follows checkout even when payment remains unpaid or
part-paid. This is an important warning against treating a competitor's
status label as equivalent to our own business state.

Source: [Fresha appointment statuses](https://www.fresha.com/help-center/knowledge-base/calendar/600-update-appointment-statuses).

Recommendation: define one mapping for our booking and visit labels, icons,
and colors across screens, while preserving their distinct backend states.
Always provide a readable label, not color alone. Clinical completion and
payment settlement should remain distinguishable.

### 6. Support different ways of scanning the schedule

Zenoti's redesigned Appointment Book documents prominent guest names,
configurable indicators, appointment expansion without leaving the calendar,
and filters that can be saved as views. It distinguishes provider and room
context in appointment details.

Source: [Zenoti redesigned Appointment Book](https://help.zenoti.com/en/appointments/redesigned-appointment-book.html).

Recommendation: test saved views such as my patients, waiting, and ready for
payment. Show patient name, time, doctor, room, and status before secondary
metadata. Preserve active filters when returning from a visit.

### 7. Dental charting needs explicit clinical context

CareStack documents direct tooth and surface selection, 2D and 3D views,
condition and treatment layers, a legend, and history for inactive conditions.
Its workflow distinguishes planned from completed treatment.

Source: [CareStack odontogram guide](https://carestack.zendesk.com/hc/en-us/articles/27732426396564-A-Guide-to-the-Odontogram).

Recommendation: for a dental-specific pilot, make the selected tooth and
surface unmistakable, distinguish findings from planned and completed work,
and provide a correction path. A colorful tooth drawing or a fixed number of
buttons alone does not establish a complete charting workflow. This is a
specialty feature, not a prerequisite for every tenant.

## Fit with the current repository

| Existing implementation | Implication |
| --- | --- |
| `resources/js/v2/Layouts/AppLayout.vue` filters navigation by permissions and role flags. | Improve the actual role experience rather than introducing a second permission system. |
| `app/Http/Controllers/V2/DashboardController.php` redirects users without report access to an allowed landing page. | A management dashboard is not necessarily the doctor's current home. |
| `resources/js/v2/Pages/WaitingPatients/Index.vue` has a doctor schedule strip, status and doctor filters, search, and check-in entry points. | Evolve this workspace; do not assume a personal daily view is completely missing. |
| The queue initializes its status and doctor filters to `all` in local component state. | Investigate retaining each user's view across navigation. |
| `resources/js/v2/Pages/Visit/Console.vue` chooses a primary action from visit status. | Status-driven buttons already exist; improve placement and clarity. |
| The console uses inline field saving, QuickPhrases, RxBuilder, LabPicker, and QuickPicks. | Build on existing quick entry before making voice the default. |
| The console includes overview, items, payments, and notes tabs. | Test role-specific ordering and visibility against real permissions and duties. |
| `resources/js/v2/Components/NewBookingSheet.vue` includes patient creation/search, doctor selection, linked room, and availability slots. | Improve this existing booking flow instead of duplicating it. |
| `docs/DESIGN-LANGUAGE.md` already defines tokens, components, RTL, and layout recipes. | Extend the existing system with workflow rules, rather than replacing its framework. |

These are source-level observations. They do not establish which configurations
are in use at each clinic, or how users experience the rendered screens.

## Recommended design-system direction

Keep Vue/Inertia v2 and the existing component foundation. Add a small set of
shared workflow conventions:

1. Role-specific default workspaces, still governed by existing permissions.
2. A consistent patient identity area and appropriate alert visibility.
3. Shared status presentation and clearly named next actions.
4. Predictable booking and visit panels that retain parent-page context.
5. Explicit saving, failure, retry, and unfinished-documentation states.
6. Consistent search/filter controls and restoration of working context.
7. Arabic/RTL layouts, readable contrast, keyboard focus, and touch-friendly
   controls verified in the rendered app.

These are our design recommendations, not claims that every competitor follows
all seven rules. Tenant identity and clinic-specific settings must continue to
come from tenant configuration or the environment.

## Implementation order and validation

1. Observe a receptionist booking, checking in, rescheduling, and collecting
   payment; observe a doctor opening the next patient, reviewing history,
   documenting, and completing treatment. Record confusion and recovery steps.
2. Prototype the existing waiting-room/visit flow with retained patient context,
   clearer primary actions, and a role-appropriate information hierarchy.
3. Compare the same tasks before and after. Measure task completion time,
   navigation steps, errors, assistance requests, and successful recovery.
4. Evaluate documentation completion states as a separate feature with explicit
   backend semantics. Do not create a cosmetic signed badge.
5. Expand to specialty charting, voice input, or sign-in changes only when the
   workflow observations justify them.

Use a non-production environment with representative synthetic data for
prototypes. Include English and Arabic, desktop and tablet, and interrupted
saving in validation. Production clinic data must not be sent to competitor
demos. This research made no changes to application behavior or the database.

## Decisions this evidence does not justify

- Replacing the platform based on route count or Vue file length.
- Removing all financial access from every practitioner.
- Assuming SMS OTP is the best workflow on a shared clinic workstation.
- Treating badges or revenue counters as a proven motivation improvement.
- Making voice notes the default without validating the clinic's languages,
  correction workflow, and recording environment.
- Copying visual styling from an old help screenshot as a current design trend.

The strongest supported direction is to keep the clinical task, patient
context, and next action together, then validate the result with our users.
