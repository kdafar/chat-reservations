<?php

/*
|--------------------------------------------------------------------------
| v2 admin "How to use this page" help content (English)
|--------------------------------------------------------------------------
| Dedicated to the v2 Inertia/Vue admin UI. Written against the ACTUAL v2
| pages (real button labels and flows), so it is intentionally separate from
| the legacy resources/lang/en/help.php used by the old Filament panel.
| Keys are v2 nav-item ids (see HelpController + helpMap.js).
*/

return [
    'modal' => [
        'heading' => 'How to use: :page',
        'description' => 'Quick guide for this page',
    ],
    'pages' => [

'dashboard' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only daily overview of how the clinic is performing today — revenue, visits, no-shows, average wait time, plus today\'s bookings and recent activity. It auto-refreshes every 20 seconds.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the four KPI cards at the top for "Today\'s revenue", "Today\'s visits", "No-shows" and "Avg. wait", each showing the change vs. yesterday.',
        'Use the "Revenue — last 30 days" line chart and the "Doctor utilization today" bars to spot trends.',
        'Click "New booking" in the header to open the booking slide-over without leaving the dashboard.',
        'In "Today\'s bookings", click a row to jump to that booking on the Bookings page; click "View all" to open the full Bookings list.',
        'In "Recent activity", click a row to open that visit\'s console, or "View all" to go to the Waiting Patients queue.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why don\'t the numbers match another clinic\'s?', 'a' => 'Every figure is scoped to the branches you can access. Revenue counts only paid payments; "Today\'s visits" counts visits checked in today; no-shows count today\'s bookings marked no-show on the Bookings page.'],
        ['q' => 'What does "Avg. wait" measure?', 'a' => 'The average minutes from when a patient was queued (checked in) to when the doctor started their service today. It drops as you start visits sooner from the Waiting Patients queue.'],
        ['q' => 'Can I change anything from here?', 'a' => 'No — the dashboard is read-only except for the "New booking" button. To act on a booking or visit, click through to the Bookings, Waiting Patients, or Visit console pages.'],
    ]],
],
'waiting' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The live clinic queue showing every patient who is waiting, in treatment, awaiting stock, or (for reception/admin) ready for payment, plus today\'s not-yet-arrived bookings. It refreshes automatically so the front desk and doctors always see the current state.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click any of the stat cards ("Total today", "Waiting", "In treatment", "Awaiting stock") or the filter chips to narrow the queue, and type in the search box to find by name, file # or booking code.',
        'On a "Pending check-in" card, click "Check in" to open the check-in modal pre-loaded with that booking, or use the "..." menu for "Mark no-show", "Cancel booking" or "Reschedule...".',
        'On a queued visit card, click "Open visit" to open the visit console; when a card is "Ready for payment" the button reads "Take payment".',
        'Inside a visit\'s clinical notes, use the quick-fill helpers to avoid typing: tap a phrase chip under Chief complaint / Examination / Diagnosis / Instructions (or "Save as phrase" to build your own library), use the prescription builder to search a drug and add a dosed line, and search the lab catalog to add test requests.',
        'Click a queued card to open its Quick view drawer, where you can "Call" the patient or "Open visit".',
        'Reception sees "New booking" and "Check in" buttons in the header; doctors instead see a read-only "Today\'s schedule" strip of their own appointments.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why do I see different cards than a colleague?', 'a' => 'Doctors only see visits assigned to them that have actually been checked in, and never see pending check-ins or the billing cards. Reception and admins see the whole queue including "Pending check-in" bookings and "Ready for payment" visits.'],
        ['q' => 'What happens when I check a patient in from here?', 'a' => 'It runs the same check-in as the Check-in desk: the booking is marked checked in, a room is occupied if you assign one, and a visit appears on the queue as "Waiting" for the doctor.'],
        ['q' => 'A patient finished with the doctor but still shows here — why?', 'a' => 'If the visit is "Ready for payment", reception/admin keep it on the queue until the remaining balance is collected and the visit is completed in the visit console.'],
    ]],
],
'checkin' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A reception-only three-step wizard to check a patient in: find today\'s booking, collect the consultation fee, then assign a room. Completing it creates the visit and puts the patient on the Waiting Patients queue.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'In "Find booking", type a booking code, phone, or patient name to search today\'s confirmed/pending bookings, then click a result to select it.',
        'In "Collect fee", pick "Cash" or "Card" and click "Collect payment" to record the consultation fee (this step is skipped automatically if the fee is already paid or the doctor has no fee).',
        'In "Assign room", tap an available room to select it, then click "Check in" — or use "Skip room" to check in without a room.',
        'On the success screen, click "Open visit" to go straight to the visit console, or "Start over" to check in the next patient.',
        'Use "Back" at any step to return to the search, and note already-checked-in bookings are greyed out and labelled "Already checked in".',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why won\'t it let me check the patient in?', 'a' => 'If the doctor has a consultation fee, it must be collected first — check-in is blocked until a paid consultation payment exists. Bookings that are cancelled, no-show, completed, or already checked in also cannot be checked in.'],
        ['q' => 'What does checking in actually change?', 'a' => 'It marks the booking checked in, sets any chosen room to occupied, and creates/updates the visit with status "Waiting" so it appears on the Waiting Patients queue and the doctor\'s console.'],
        ['q' => 'Can anyone use this page?', 'a' => 'No — only reception and admin roles can open the check-in desk and run any of its steps.'],
    ]],
],
'bookings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The full bookings list for any period, with filters and a quick-view drawer that exposes every booking action — check-in, collect fee, reschedule, assign room, cancel, no-show, edit and more. This is where reception manages appointments.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the search box, the period chips ("Today", "Tomorrow", "This week", "This month", "Past", "Any time"), the "Branch"/"Doctor" pickers and the status chips to filter the table.',
        'Click "New booking" to open the booking slide-over, or "Check-in" to open the check-in modal.',
        'Click a row to open the quick-view drawer, then use its buttons: "Open visit" or "Check in", "Collect fee", "Reschedule", "Assign room", "WhatsApp", "Resend confirmation", "Print", "Edit", and the destructive "No-show"/"Cancel".',
        'Tick the row checkboxes to select bookings, then click "Export Excel" in the bulk bar to download them.',
        'Status counts appear on the status chips so you can see how many are pending, confirmed, completed, cancelled or no-show within the current filter.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why are "Cancel", "No-show" or "Reschedule" missing on some bookings?', 'a' => 'Those actions only appear when allowed: you can\'t cancel/reschedule a checked-in or already-closed booking, and "No-show" only shows for confirmed/pending bookings that haven\'t been checked in.'],
        ['q' => 'What does "Collect fee" do here?', 'a' => 'It records the consultation payment for the booking (creating the visit shell if needed) — the same money path as the Check-in desk, which then satisfies the paid-before-check-in rule.'],
        ['q' => 'Where does "Open visit" take me?', 'a' => 'To that booking\'s visit console (/admin/v2/visits/{id}), available once a visit exists for the booking — for example after collecting the fee or checking the patient in.'],
    ]],
],
'visits' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A searchable list of all visits across the clinic. It is the entry point to each visit\'s console, where the actual clinical and billing work happens.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Filter with the search box (patient name or phone), the "All doctors"/"All branches"/"All statuses" pickers, the Accepted segment ("All"/"Accepted"/"Not accepted"), and the "From"/"Until" date range.',
        'Click any row to open that visit\'s console to manage items, payments, stock and completion.',
        'Tick row checkboxes and click "Export Excel" in the bulk bar to download the selected visits.',
        'Read the "Total" and "Completed" stat chips above the table for a quick count of the current scope.',
        'When financials are enabled, a calculator "Recompute" button appears on completed visits to rebuild that visit\'s financial snapshot.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why don\'t I see a "Fees" column or "Recompute" button?', 'a' => 'The fees column and recompute action only appear when visit financials are enabled in configuration, and recompute also requires permission to update visits and a visit in "Completed" status.'],
        ['q' => 'How do I edit a visit\'s items or take payment?', 'a' => 'Not from this list — click the row to open the visit console, which is where you add items/packages, record or void payments, request stock and complete the visit.'],
        ['q' => 'Why is a visit missing from my list?', 'a' => 'Visits are scoped to the branches (and, for doctors, the visits) you can access, and to your active filters. Widen the date range or clear filters to see more.'],
    ]],
],
'doctor-schedule' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A per-doctor appointment board grouped by day, showing each booking\'s time, patient, check-in status and a one-tap WhatsApp link. It is read-only — actual check-in is done from the Check-in desk.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick a doctor from the "Doctor" selector (hidden for doctors, who see only their own schedule).',
        'Switch the period with the "Today" / "Tomorrow" / "This week" / "All upcoming" segment.',
        'Narrow to a time-of-day with the "All times" / "Morning" / "Afternoon" / "Evening" segment.',
        'On any appointment row, click the WhatsApp icon to message the patient, or "Check in" to jump to the Check-in desk for that patient.',
        'Read the "Appointments", "Checked in" and "Pending" chips at the top for the current selection\'s totals.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I check a patient in directly from here?', 'a' => 'No — this board is read-only. The "Check in" button takes you to the Check-in desk, where you search the booking, collect any fee and assign a room.'],
        ['q' => 'Why can\'t I change the doctor?', 'a' => 'If you are logged in as a doctor, the board is locked to your own schedule and the doctor picker is hidden. Admins can pick any active doctor.'],
        ['q' => 'Which bookings appear here?', 'a' => 'Only confirmed and pending bookings for the chosen doctor, period and time-of-day; cancelled, completed and no-show bookings are not shown.'],
    ]],
],
'patients' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The patient directory — every patient file in the clinic, searchable by name, phone, civil ID or file number. Staff use it to look up a patient, see contact details and medical alerts, and jump to their full profile or a new booking.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type in the search box to find patients by name, phone, civil ID or file number; narrow further with the "All / Male / Female" gender pills and the "All / Has phone / No phone" pills.',
        'Click "New patient" to open the create form, or use "Import" to upload patients in bulk from a file.',
        'Click any row to open the quick-view panel showing alerts, contact, total visits, total paid, last visit and upcoming bookings.',
        'In the quick-view panel, click "New booking" to book the patient, or "Open profile" to go to their full record.',
        'Tick the row checkboxes and use "Export Excel" in the bottom bar to download the selected patients.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why do I only see some patients?', 'a' => 'Non-admin users only see patients who have a booking they can access. Admins and super admins see everyone.'],
        ['q' => 'What does the red warning triangle on a row mean?', 'a' => 'The patient has recorded allergies. Open the row to read the full allergy and medical-alert text in the highlighted panel.'],
        ['q' => 'How do I edit a patient\'s details?', 'a' => 'Open the row, click "Open profile", then use "Edit" on the profile page. The directory itself is for searching and viewing.'],
    ]],
],
'patient-files' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A clinic-wide browse view of every file attached to patient records — lab reports, prescriptions, imaging, insurance cards and more. Staff use it to find and open a document across all patients without going through each profile.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by filename, notes or patient name in the search box.',
        'Filter by document type using the "All categories" dropdown, and narrow by date with the "From" and "To" date pickers.',
        'Click "View" to open a file inline, or "Download" to save it; both actions are recorded in the file\'s access log.',
        'Click "Export Excel" to download the current filtered list as a spreadsheet.',
        'Use "Clear" to reset all filters back to the full list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I upload a new file?', 'a' => 'Uploading happens from the patient profile, not here. Open the patient, go to the Files tab, and use "Upload file". This page is read-only browsing across all patients.'],
        ['q' => 'Who can see this page?', 'a' => 'Only users with the patient-files view permission. Viewing or downloading a file writes an entry to its access log with your name, time and IP.'],
        ['q' => 'Can I delete a file from here?', 'a' => 'No — file deletion is done from the patient profile by users with delete rights. This list is for finding and opening files.'],
    ]],
],
'follow-up-plans' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only oversight list of follow-up plans, which are created automatically when a visit is saved with a follow-up. It shows who is due back, the source visit, and whether a follow-up booking was auto-created.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by patient name or phone in the search box.',
        'Use the "All / With booking / Without booking" pills to see which follow-ups still need a booking made.',
        'Narrow by follow-up date using the "From" and "Until" date pickers.',
        'Read the "Auto-book" column — a check mark means a booking was created automatically; a dash means none exists yet.',
        'Click the source visit code to open that visit, and use "Export Excel" to download the filtered list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I create or edit a plan here?', 'a' => 'No. Plans are generated by the visit save step, so this page is read-only. To change a plan, edit the originating visit.'],
        ['q' => 'A plan shows "Without booking" — what do I do?', 'a' => 'No appointment was auto-created for it. Open the patient (or use New booking) and schedule the follow-up manually so the patient is not missed.'],
        ['q' => 'What does the source visit link do?', 'a' => 'It opens the visit that triggered the follow-up, so you can see the diagnosis and context behind it.'],
    ]],
],
'inpatient-board' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A visual bed board showing every ward as a group of colour-coded bed cards (green available, red occupied, blue cleaning, grey maintenance, amber reserved). It is the command centre for the whole inpatient workflow — admit, view, transfer and free beds from here.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click a green (available) bed, or the "Admit patient" button, to open the admit form and admit a patient into that bed.',
        'Click a red (occupied) bed to open the admission panel where you can transfer, discharge, log rounds and add charges.',
        'Click a cleaning, maintenance or reserved bed to mark it available again (housekeeping states).',
        'Use the "Admissions" link to switch to the full admissions list, or read the top strip for live occupancy, available and active-admission counts.',
        'In the admit form choose the patient, admitting doctor, admission reason and optional diagnosis, then click "Admit".',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens to a bed when I admit a patient?', 'a' => 'The bed turns red (occupied) and shows the patient, doctor and admission code. Bed-day charges then accrue automatically each night for that stay.'],
        ['q' => 'Why can\'t I admit or move patients?', 'a' => 'Only doctors, admins and clinic admins can admit, transfer and discharge. Reception can view the board and flip non-occupied beds between housekeeping states but cannot manage admissions.'],
        ['q' => 'A bed is occupied but I need it free — what do I do?', 'a' => 'You cannot directly mark an occupied bed available; you must discharge or transfer the patient first. Discharging frees the bed and stops its nightly charges.'],
    ]],
],
'inpatient-admissions' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The list of inpatient admissions — currently active plus recently discharged ones. Staff use it to find an admission, review its details, and run the workflow (transfer, discharge, rounds, charges) from the admission panel.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Switch between the "Active", "Discharged" and "All" tabs (each shows a count) to filter the list.',
        'Click any row to open the admission panel with the patient, bed, doctor and full history.',
        'In the panel use the "Overview", "Bed history", "Charges" and "Rounds" tabs; click "Assign bed" / "Transfer" to move the patient and "Discharge" to close the admission.',
        'In the Charges tab click "Add manual charge", and in the Rounds tab click "Log round" to record a doctor visit.',
        'Use "Export Excel" to download the current view, or "Bed board" to jump to the visual board.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does discharging do?', 'a' => 'It closes the admission, frees the bed (it leaves the occupied pool on the bed board), stops nightly bed-day charges, and generates a final visit that carries the total bill. You enter a discharge summary and discharge type (Discharged, LAMA, Transferred out, or Expired).'],
        ['q' => 'How do bed charges get added?', 'a' => 'Bed-day charges accrue automatically overnight while the admission is active. You can also add one-off costs with "Add manual charge" in the Charges tab.'],
        ['q' => 'Where do I print a discharge summary?', 'a' => 'Open the admission and use "Print discharge summary" on the Overview tab — it opens a print-friendly page you can print or save as PDF from the browser.'],
    ]],
],
'inpatient-wards' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The setup screen for inpatient wards — the units (general, ICU, pediatric, maternity, VIP, isolation) that hold beds, each with its own nightly rate. Staff configure wards here before beds and the bed board can be used.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New ward" to add a ward, setting its name, code, type, branch and daily rate.',
        'Search by name or code, and filter by branch or ward type to find a ward.',
        'Click any row to open the "Edit ward" form and update its details or daily rate.',
        'Click the trash icon on a row to remove a ward.',
        'Use "Clear" to reset the search and filters.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I delete a ward?', 'a' => 'A ward that still has beds cannot be deleted. Remove its beds first (on the Beds page), then delete the ward.'],
        ['q' => 'What does the daily rate do?', 'a' => 'It is the default nightly charge for beds in that ward, used to accrue bed-day charges during an admission. A bed can override it with its own rate.'],
        ['q' => 'I added a ward but it isn\'t on the bed board — why?', 'a' => 'The bed board only shows active wards that contain beds. Add beds to the ward on the Beds page, and make sure the ward is active.'],
    ]],
],
'inpatient-beds' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The setup screen for individual beds inside wards, each with a status (available, occupied, reserved, maintenance, cleaning) and an optional rate override. These beds are what appears on the bed board.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New bed" to add a bed, choosing its ward, code and status (and an optional daily-rate override).',
        'Search by bed code, and filter by ward or by status to find beds.',
        'Click any row to open the "Edit bed" form and change its ward, status or rate.',
        'Click the trash icon to remove a bed.',
        'Read the count chips for total, available, occupied and maintenance/cleaning beds.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I delete a bed?', 'a' => 'An occupied bed cannot be deleted — discharge or transfer the patient first, then remove the bed.'],
        ['q' => 'Should I change a bed\'s status to "occupied" here?', 'a' => 'No. Occupancy is set automatically when you admit a patient on the bed board. Use this screen mainly for setup and housekeeping states like maintenance or cleaning.'],
        ['q' => 'What does the rate override do?', 'a' => 'If set, the bed uses its own nightly rate instead of the ward\'s daily rate when bed-day charges accrue.'],
    ]],
],
'inpatient-reports' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A reporting dashboard for the inpatient department — key stats plus charts for bed occupancy, admissions by ward and bed revenue per ward. Managers use it to monitor capacity and inpatient income.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the top KPI cards: "ALOS (30d)" average length of stay, "Admissions this month", "Bed revenue (month)" and "Active now".',
        'Review the "Bed occupancy (30 days)" chart to see the occupancy trend over the last month.',
        'Check "Admissions by ward" to see where current inpatients are concentrated.',
        'Use "Revenue per ward" to see which wards generate the most bed-day income this month.',
        'The figures load automatically when you open the page; revisit or refresh to see updated numbers.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does ALOS mean?', 'a' => 'Average length of stay, in days, calculated across admissions discharged in the last 30 days.'],
        ['q' => 'Why is bed revenue lower than I expect?', 'a' => 'Revenue per ward and bed revenue count only bed-day charges for the current month; one-off manual charges and other visit fees are not included here.'],
        ['q' => 'Who can open inpatient reports?', 'a' => 'Admins, super admins and clinic admins, plus any user with the admissions view permission.'],
    ]],
],
'insurance-insurers' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The master list of the insurance companies your clinic contracts with. Staff add and maintain each insurer\'s code, contacts and payment terms so they can be linked to plans, policies and claims.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New insurer" to open the form and fill in name, code, tax ID, contacts and payment terms (days), then "Save".',
        'Click any row to reopen it in the "Edit insurer" form.',
        'Filter the list with the "All" / "Active" / "Inactive" toggle, or type in the search box to match name, code or tax ID.',
        'Use the row archive button to retire an insurer; an "Undo" link appears, and archived rows can be brought back with the restore button.',
        'Tick rows and use the "Archive" button in the bulk bar, or pull a spreadsheet with "Export Excel" / load one with the import button.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the "Plans" number on each row mean?', 'a' => 'It is how many insurance plans belong to that insurer. You create those plans on the Insurance Plans page.'],
        ['q' => 'I archived an insurer by mistake — is it gone?', 'a' => 'No. Archiving is a soft delete. Click "Undo" on the toast right away, or switch the filter to "Inactive" and use the restore button on the row.'],
        ['q' => 'Why must the code be unique?', 'a' => 'The code identifies the insurer across plans, patient policies and claims, so the form blocks saving a duplicate code.'],
    ]],
],
'insurance-plans' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The catalog of coverage plans offered by each insurer (for example a Gold or Silver tier). Staff use it to define the plans that patient policies and claims are attached to.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New plan" to add one — pick the "Insurer", set "Tier", "Code", "Effective from" / "Effective until", then "Save".',
        'Click a row to reopen it in the "Edit plan" form.',
        'Narrow the list with the search box, the "All insurers" insurer filter, and the "All" / "Active" / "Inactive" toggle.',
        'Use "Export Excel" to download the filtered list as a styled spreadsheet.',
        'Each row shows a "Rules" count and a "Policies" count so you can see how widely a plan is used.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I delete a plan?', 'a' => 'If the plan is already used by patient policies or coverage rules the delete is blocked and you get a message saying so. Set it to inactive instead.'],
        ['q' => 'What do "Effective from" and "Effective until" control?', 'a' => 'They mark the window the plan is valid; "Effective until" must be on or after "Effective from".'],
        ['q' => 'Where do plans get used?', 'a' => 'A plan is chosen on the Patient Policies page, and through the policy it feeds into pre-authorizations and claims.'],
    ]],
],
'insurance-policies' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The record of which patients hold which insurance policies, including member/card numbers, holder relationship and status. Staff use it so visits can generate insurance claims against the right coverage.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New policy", then use the "Search a patient…" typeahead (matches name, phone or civil ID) to attach the patient.',
        'Choose the "Insurer" and "Plan", and enter the "Policy number", "Member ID" and "Card number".',
        'Set "Holder relationship" (self / spouse / child / parent / other) and tick "Primary policy" when this is the patient\'s main cover.',
        'Filter by the "All" / status toggle (Active, Expired, Suspended, Cancelled) or search by policy number, member ID, card or patient.',
        'Use "Export Excel" to download the filtered policies.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why does the "Primary" flag matter?', 'a' => 'When a visit is completed the system drafts the claim against the patient\'s primary active policy, so the patient with no primary active policy gets no auto-claim.'],
        ['q' => 'I can\'t delete a policy — why?', 'a' => 'If the policy already has claims or pre-authorizations the delete is blocked with a message; change its status to Cancelled or Expired instead.'],
        ['q' => 'The plan dropdown is empty after I pick an insurer.', 'a' => 'Only active plans belonging to the chosen insurer appear; add the plan on the Insurance Plans page first.'],
    ]],
],
'insurance-preauth' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Where staff raise and track pre-authorization requests sent to insurers for planned services, and record the insurer\'s decision. It captures the estimated services and the approved amount.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New request", pick the "Policy", optionally enter a "Visit #", and add one or more service lines with "Add service" (each has a description and estimated amount — the "Estimated total" adds up automatically).',
        'Click a row to reopen it in the "Edit request" form.',
        'Filter with the search box (reference or patient) and the "All statuses" status dropdown.',
        'Once a request is Submitted or Under review, use "Record decision" to set Approved / Partially approved / Rejected, the "Approved amount" and "Decision notes".',
        'Use "Export Excel" to download the filtered requests.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why is "Record decision" greyed out or refusing?', 'a' => 'A decision can only be recorded while the request is Submitted or Under review; draft requests must be moved forward first.'],
        ['q' => 'Is the estimated total something I type?', 'a' => 'No — it is summed from the estimated amount on each service line you add, though you can still adjust the lines.'],
        ['q' => 'Does a pre-authorization create a claim?', 'a' => 'No. Pre-auth records the insurer\'s prior approval only; the actual claim is created separately from the completed visit on the Claims page.'],
    ]],
],
'insurance-claims' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The workspace for insurance claims — the amounts charged, payable, paid and outstanding — and the place to move each claim through its lifecycle. Most claims are drafted automatically when a visit is completed.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click a row to open the claim drawer showing its items, payments, state log, balance due and the available action buttons.',
        'Drive the claim with the drawer actions: "Submit", "Mark under review", "Approve" (enter approved amount + reference), "Partially approve" (approved + rejected amounts) or "Reject" (reason required).',
        'On an approved or partially approved claim use "Record payment" — enter amount, "Method" (Bank transfer / Cheque / Cash), reference and the "Deposited to" account.',
        'Use "Write off" to clear an uncollectable balance, and "Void" (admins only) to cancel a claim with a reason.',
        'Need a claim that wasn\'t auto-created? Click "Claim from visit", enter the "Visit #", and "Create". Filter the list by search or the status dropdown, and "Export Excel" from the bulk bar.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Where do claims come from?', 'a' => 'When a visit is marked completed the system auto-drafts a claim against the patient\'s primary active insurance policy. If there is no such policy, no claim is drafted and you can raise one manually with "Claim from visit".'],
        ['q' => 'What happens when I "Record payment"?', 'a' => 'It logs the insurer payment, reduces the balance due, and posts the matching journal entry into the accounting ledger (using the "Deposited to" bank/cash account you pick).'],
        ['q' => 'Why don\'t I see all the action buttons?', 'a' => 'Buttons depend on the claim\'s current status (only valid next steps appear) and on your permissions — for example only admins can "Void", and recording payments or decisions needs the matching permission.'],
        ['q' => 'Can I change a claim\'s status directly?', 'a' => 'No. Status only changes through the action buttons, which run the claim state machine and write an entry to the state log so the history is auditable.'],
    ]],
],
'lab-tests' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The catalog of lab tests the clinic can order, with their codes, specimen type, units, reference ranges and default price. Staff maintain it so tests appear correctly when ordered on a visit.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New test" to add one — set "Code", "Name", "Specimen type", "Unit", "Reference range" and "Default price (KWD)", then "Save".',
        'Click a row to reopen it for editing.',
        'Filter with the search box (code, name or specimen), the branch filter, and the "All" / "Active" / "Archived" toggle.',
        'Use the row "Archive" button to retire a test, and "Restore" to bring an archived one back.',
        'Use "Export Excel" to download the filtered catalog, or the import button to bulk-load tests.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens to past results when I archive a test?', 'a' => 'Nothing is lost — archiving only hides the test from new orders; historical lab results keep showing it.'],
        ['q' => 'Why is a code rejected as duplicate?', 'a' => 'Codes must be unique within a branch, so the same code can exist under different branches but not twice in one.'],
        ['q' => 'Who orders these tests?', 'a' => 'Reception and doctors don\'t come here to order — they pick from this catalog using the lab orders section on the visit page. This page is only for managing the catalog itself.'],
    ]],
],
'clinic-items' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The catalogue of every item and service your clinic uses or bills for. Consumables can be made stockable (so they get inventory tracking), while services never carry stock.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New item" to add one; pick a "Type" of either "Consumable" or "Service", enter the English and Arabic names, and set "Default cost" and "Default price" in KWD.',
        'Use the search box and the type segment ("All types" / "Consumable" / "Service") plus the status segment ("All" / "Active" / "Inactive") to narrow the list; "Clear" resets them.',
        'For a consumable, tick "Stockable" to reveal the "Inventory settings" (Stock unit, Usage unit, Conversion factor, Consume step) — these are required before it can hold stock.',
        'Click any row to edit it, or use the trash icon to delete; mark an item "Inactive" instead when it has history.',
        'Use "Export Excel" to download the filtered list, or the import button to bulk-load items.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I make a service use up its own consumables?', 'a' => 'Edit the service and fill in "Consumables used per service" (its bill of materials) — e.g. a Botox service uses 2 vials + 3 syringes. When that service is added to a visit, those items auto-deduct from stock (or open a stock request if short), so you no longer relist them in every package.'],
        ['q' => 'Why can\'t I delete an item?', 'a' => 'If an item already has stock or usage history the delete is blocked and you are asked to mark it "Inactive" instead, which keeps its records intact.'],
        ['q' => 'What does "Stockable" actually do?', 'a' => 'Only a stockable consumable can have a record on the Clinic Stock page and appear in stock movements; services and non-stockable consumables are billed but never tracked for quantity.'],
        ['q' => 'What is "Billable"?', 'a' => 'It controls whether the item is charged on a visit. Services are always billable; for consumables you can turn it off so they are consumed without adding a charge.'],
    ]],
],
'clinic-stock' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The on-hand inventory balance for each stockable item at each branch. Staff use it to see current quantities, set low-stock alert thresholds, and record deliveries.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "Receive stock" to log a delivery — choose the branch and item and enter either "Qty (stock units)" or "Qty (base units)"; this is the only way the on-hand quantity goes up.',
        'Click "New record" to start tracking a stockable item at a branch (it begins at zero on-hand).',
        'Use the pencil icon to edit a row — only the "Alert threshold" and "Bin location" are editable; on-hand quantity cannot be typed in directly.',
        'Tick "Low only" or use the search box to filter; rows at or below their threshold show a "Low" badge and feed the low-stock count.',
        'Use "Export Excel" for the filtered list, or the import button to bulk-load stock records.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I just type the on-hand quantity?', 'a' => 'On-hand quantity moves only through "Receive stock" (or automatic consumption on visits), and every change writes an audited entry on the Stock Movements page, so it can never be edited by hand.'],
        ['q' => 'What is the difference between stock units and base units?', 'a' => 'You can receive in stock units (e.g. boxes) and the system multiplies by the item\'s conversion factor to store base units, or enter base units directly; enter only one of the two.'],
        ['q' => 'What triggers the "Low" badge and alerts?', 'a' => 'A row is flagged Low when its on-hand quantity is at or below the "Alert threshold" you set; this drives the low-stock count and notifications. An item with no threshold is never flagged.'],
    ]],
],
'stock-movements' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only audit log of every change to clinic stock — each receipt, consumption, and adjustment, with the quantity change and the resulting balance. You cannot add or edit rows here.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by item name to trace the full movement history of a specific item.',
        'Use the type segment ("All types" / "Restock" / "Consume" / "Adjustment") to filter to one kind of movement.',
        'Read the "Change" column — green positive values are stock coming in, red negative values are stock going out — and "After" for the running balance.',
        'Click "Clear" to reset the search and type filter.',
        'Use "Export Excel" to download the filtered log.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I edit or delete a movement?', 'a' => 'No — this page is purely a record. Rows are created automatically by the system when you receive stock on the Clinic Stock page or when items are consumed on a visit.'],
        ['q' => 'Where do "Consume" rows come from?', 'a' => 'They are written automatically when a visit uses a stockable item (for example when a stock request is fulfilled), reducing on-hand quantity on the Clinic Stock page.'],
        ['q' => 'Why does a movement show a branch?', 'a' => 'Stock is tracked per branch, so each movement records which branch\'s balance changed; the list only shows movements for branches you have access to.'],
    ]],
],
'stock-requests' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The pharmacy worklist of stock-item requests raised from the visit console. Each request is checked live against the branch\'s current stock so the dispenser can see what is in stock or short before issuing it.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Switch between the "Pending", "Fulfilled" and "Cancelled" tabs (each shows a live count) to find requests to act on.',
        'On a pending request, read each line: the requested quantity is shown against live availability, with a red "short!" marker when stock is insufficient.',
        'Click "Fulfil" to issue the stock — choose which status the visit returns to ("Awaiting doctor" or "In progress"), add optional notes, then "Confirm fulfil".',
        'Click the cancel (X) button to reject a request; a reason is required to "Confirm cancel".',
        'Click the visit code badge to open that visit, or use "Export Excel" to download the filtered list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens to stock when I fulfil a request?', 'a' => 'Fulfilling issues the items atomically: it deducts the quantities from on-hand on the Clinic Stock page, writes consume entries on the Stock Movements page, and resumes the visit to the status you pick.'],
        ['q' => 'Can I fulfil a request when stock is short?', 'a' => 'The page warns you with a "short!" marker on any line below the live available quantity, but the decision to fulfil is yours; only pending requests can be acted on.'],
        ['q' => 'Where do these requests come from?', 'a' => 'They are raised from the visit console when a doctor or nurse needs stock items for a visit; this page is where pharmacy fulfils or cancels them.'],
    ]],
],
'purchase-orders' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The procurement workspace: raise a purchase order to a vendor, receive the goods into a branch\'s stock, and pay the vendor. Receiving and paying post automatically to the accounting books, so inventory and Accounts Payable always stay correct.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New PO", pick the vendor and receiving branch, then add each item with its quantity and unit cost; the order total updates live. The PO is created as a draft.',
        'Open a draft and click "Approve" once the order is confirmed with the vendor. Only draft POs can be edited or cancelled.',
        'When goods arrive, click "Receive" — enter the quantity received for each line (you can receive in several batches). This adds the items to stock and books Dr Inventory / Cr Accounts Payable at the PO cost.',
        'Click "Pay" to record a payment to the vendor: enter the amount (defaulting to the outstanding balance), the method, and an optional reference. This books Dr Accounts Payable / Cr Cash or Bank.',
        'Use the status filter, vendor filter and search box to find POs; expand a row to see its lines, receipts, payments and the outstanding balance.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'When does stock actually increase?', 'a' => 'Only when you receive goods — each receipt adds the received quantity to on-hand on the Clinic Stock page and writes a stock movement. Creating or approving a PO does not touch stock.'],
        ['q' => 'How does this affect the accounting books?', 'a' => 'Receiving posts Dr Inventory (1200) / Cr Accounts Payable (2010) at the purchase cost; paying the vendor posts Dr Accounts Payable / Cr Cash or Bank. The outstanding balance on a PO is what you still owe the vendor (received minus paid).'],
        ['q' => 'Can I receive only part of an order?', 'a' => 'Yes. Enter whatever arrived for each line; the PO moves to "Partially received" and remembers what is still outstanding so you can receive the rest later. It becomes "Received" once every line is fully received.'],
        ['q' => 'What if I record a payment by mistake?', 'a' => 'Void the payment — it reverses the journal entry it created and removes it from the PO, restoring the outstanding balance.'],
        ['q' => 'Does the unit cost change my item costs?', 'a' => 'The purchase cost is used for the inventory value posted to the books. It does not overwrite the item\'s own default cost used elsewhere.'],
    ]],
],
'clinic-packages' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Reusable bundles of clinic items sold at a single price that a doctor can add to a visit in one tap. Use it to set up common combinations such as a consultation-plus-supplies package.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New package", enter the English and Arabic names and a "Default price", then use "Add item" to add each clinic item with its "Qty (base)".',
        'For each line, tick the "Deduct from stock" box if that item should reduce inventory when the package is used on a visit.',
        'Leave "Branch" empty to offer the package at every branch, or pick one branch to limit it.',
        'Filter the list with the search box, the branch selector ("All branches"), and the status segment ("All" / "Active" / "Inactive"); click a row to edit it.',
        'Use the trash icon to delete a package, "Export Excel" to download the list, or the import button to bulk-load packages.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can a package include a service, not just stock items?', 'a' => 'Yes. The item dropdown lists services, products and consumables together — each tagged with its type, with services shown first. Add the service as a line; the consumables it uses are defined on the service itself (its "Consumables used" bill of materials on the Clinic Items page) and deduct from stock automatically when the package is used. The per-line "Deduct from stock" toggle is only for standalone consumable lines you add directly.'],
        ['q' => 'Does editing a package change its items?', 'a' => 'Yes — saving resyncs the line items, so the package always reflects exactly the items currently listed in the editor.'],
        ['q' => 'What does "Deduct from stock" do on a line?', 'a' => 'When ticked, using the package on a visit treats that item as a stockable consumable and reduces its on-hand quantity on the Clinic Stock page; untick it for items that should not affect inventory.'],
        ['q' => 'How is the package price related to its items?', 'a' => 'The "Default price" is the single price charged for the whole bundle on a visit; it is set independently of the individual items\' own prices.'],
    ]],
],
'leaves' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page tracks staff leave requests. HR managers see and decide on everyone\'s requests, while regular staff see only their own and can submit new ones.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "Request leave" to open the form and pick a type (annual, sick, maternity, unpaid, emergency or other), start and end dates, and an optional reason.',
        'HR managers approve a pending row with the green "Approve" action or turn it down with "Reject", adding optional "Notes" in the decision dialog.',
        'Use the "Pending", "Approved", "Rejected" and "Cancelled" status filter plus the type dropdown to narrow the list.',
        'HR managers can search by staff name or email and pick a person from the "All staff" selector to view just their leave.',
        'Use "Export Excel" to download the current filtered list, and the edit or delete actions to amend a request.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can I only see my own requests?', 'a' => 'Non-managers are scoped to their own records and can only request leave for themselves; the staff search and "All staff" picker appear only for HR managers.'],
        ['q' => 'What do the "Total", "Pending" and "Approved" chips at the top mean?', 'a' => 'They are live counts of your leave (or, for HR managers, all staff leave) so you can see outstanding requests at a glance.'],
        ['q' => 'Who can approve or reject?', 'a' => 'Only HR managers see the "Approve"/"Reject" actions, and only on rows still in the "Pending" status; the decision and any notes are stored against the request with the deciding manager\'s name.'],
    ]],
],
'attendance' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page records daily clock-in and clock-out times and the hours worked. Staff log their own shifts here; HR managers can view and correct everyone\'s records.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'When you haven\'t started your shift, click "Clock me in"; the card then shows "Clock me out" to close the shift and tally your hours.',
        'The two stat tiles show your hours for this week and this month so you can track your totals.',
        'HR managers can search staff by name, pick someone from the "All staff" selector, and filter by a "From"/"Until" date range.',
        'On a row that is clocked in but not yet out, the green clock-out action closes it; use the edit pencil to correct times.',
        'Use "Export Excel" to download the current filtered attendance list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'I see "You haven\'t clocked in today." — what now?', 'a' => 'That message means no shift is open for today; click "Clock me in" to start one. Once in, the same card switches to "Clock me out".'],
        ['q' => 'Why don\'t I see other people\'s attendance?', 'a' => 'Non-managers only see their own records; the staff search, "All staff" picker and delete action are reserved for HR managers.'],
        ['q' => 'Can a manager fix a wrong time?', 'a' => 'Yes. HR managers (and the record owner where permitted) can use the edit action to adjust the date and clock-in/out times, which recalculates the hours worked.'],
    ]],
],
'doctors' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the doctor directory where you manage each doctor\'s consultation fee, license, branch and active status. It feeds doctor selection across visits, bookings and earnings.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New doctor" to add one, entering the name, specialty, phone, license, "Consultation fee (KWD)", "Branch", "Partner" and an optional "Linked user account".',
        'Click any row to open "Edit doctor" and update its details.',
        'Filter with the "Active", "Archived" and "All" status tabs and the branch selector, or search by name.',
        'Use the archive action to hide a doctor from active lists, or "Restore" to bring an archived one back; tick rows to bulk-archive selected doctors.',
        'Use "Export Excel" to download the current directory.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the "Linked user account" field do?', 'a' => 'It ties the doctor to a staff login from the Users page, so that user can act as that doctor (for example in the consult console). The dropdown lists users not already linked.'],
        ['q' => 'Does archiving delete the doctor?', 'a' => 'No. Archiving only removes the doctor from active lists; the record and its history stay and can be brought back with "Restore".'],
        ['q' => 'How does the consultation fee relate to earnings?', 'a' => 'The fee here is the default charge for the doctor\'s consultations; how much of the fees or profit the doctor actually earns is set separately on the Compensation Profiles page and captured per visit in the Doctor Earnings ledger.'],
    ]],
],
'users' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This admin-only page manages staff user accounts and the roles that control their permissions. It is where logins are created, deactivated and assigned roles.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New user" to create an account, setting name, email, phone, "Password", "Status" and one or more "Roles".',
        'Click any row to open "Edit user"; leave the "New password (leave empty to keep current)" field blank to keep the existing password.',
        'Filter with the "Active"/"Inactive"/"All" status tabs and the "All roles" selector, or search by name or email.',
        'Use the row action to deactivate an active user instead of deleting the account outright.',
        'Use "Export Excel" to download the user list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How are roles used?', 'a' => 'Roles assigned here determine what each user can access across the app; the role badges show on each row and can be edited at any time.'],
        ['q' => 'Can I link a user to a doctor here?', 'a' => 'Not from this page. You create the login here, then link it to a doctor via the "Linked user account" field on the Doctors page, which lets that user act as the doctor.'],
        ['q' => 'Why deactivate instead of delete?', 'a' => 'Setting a user to "Inactive" blocks the login while preserving their history; this is safer than deleting an account that owns past records.'],
    ]],
],
'doctor-comp' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Compensation profiles define how each doctor is paid — a fixed salary, or a percentage of fees or of net profit. These rules drive what doctors earn on each completed visit.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New profile", choose the "Doctor", set the "Type" (Salary or Percentage) and "Basis" (Fees only or Net profit).',
        'For a Percentage type, enter the "Percentage rate (%)" that the doctor receives.',
        'Use the "Active" toggle so only current profiles apply; click any row to edit it.',
        'Filter with the "All types", "Salary" and "Percentage" tabs, or search by doctor name.',
        'Use "Export Excel" to download the profiles, or the delete action to remove one.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the difference between "Fees only" and "Net profit"?', 'a' => 'Fees only takes the percentage of the consultation/service fees, while Net profit takes it after costs are deducted; the chosen basis is what the Doctor Earnings ledger uses to compute each visit\'s cut.'],
        ['q' => 'Why can\'t I change the doctor when editing?', 'a' => 'The "Doctor" field is locked in edit mode because a profile belongs to one doctor; create a new profile to set up a different doctor.'],
        ['q' => 'How does this affect doctor pay?', 'a' => 'When a visit completes, the active profile here is applied to capture that doctor\'s earnings as a per-visit snapshot, which then appears on the read-only Doctor Earnings page.'],
    ]],
],
'doctor-earnings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is a read-only ledger of per-visit doctor earnings. Each row is a snapshot captured when a visit completes, showing the fees, profit and the doctor\'s cut.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick a doctor from the "All doctors" selector to see only their earnings.',
        'Narrow the period with the "From" and "Until" date filters.',
        'Read each row\'s "Fees", "Profit" and "Doctor cut" columns alongside the visit and date.',
        'Check the "Records" and "Total doctor cut" chips at the top for the filtered totals.',
        'Use "Export Excel" to download the filtered earnings.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I edit earnings here?', 'a' => 'No. This page is read-only; the figures are snapshots taken at visit completion based on the doctor\'s compensation profile. To change future calculations, update the profile on the Compensation Profiles page.'],
        ['q' => 'How is the "Doctor cut" calculated?', 'a' => 'It comes from the doctor\'s active compensation profile — the type and basis (Fees only or Net profit) and percentage rate set there are applied to that visit\'s fees or profit.'],
        ['q' => 'Why is a recent visit missing?', 'a' => 'A row is only created when the visit completes; visits still in progress have no earnings snapshot yet.'],
    ]],
],
'accounts' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The Chart of Accounts is the tree of every financial account in the clinic (assets, liabilities, equity, revenue, COGS, expenses). Staff use it to organise the ledger and to see each account\'s balance, which is computed from posted journal entries.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New account" to add an account, choosing its "Code", "Name", "Type" and optional "Parent account".',
        'Search with the box at the top, or filter by "All types" and by the "All" / "Active" / "Inactive" buttons.',
        'Click a row to open "Edit account" and change its details, or toggle the "Active" switch to retire it.',
        'Accounts marked with a lock icon are "System account" entries — their code and type are locked; you can still edit the description and active state.',
        'The "Balance" column reflects only posted entries, so a new account shows zero until entries are posted against it.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I change a system account\'s code or type?', 'a' => 'System accounts are wired into automatic postings (sales, payments, closing entries). Only their description and active status are editable; code and type stay locked to keep auto-posting reliable.'],
        ['q' => 'Why won\'t an account delete?', 'a' => 'Deletion is blocked if the account has journal lines or child accounts, and system accounts can never be deleted. Set it to inactive instead to hide it from new entries.'],
        ['q' => 'Where does the balance come from?', 'a' => 'It is summed from posted (and reversed) journal entry lines. Draft entries do not affect it, so balances only move once entries are posted on the Journal Entries page.'],
    ]],
],
'journal-entries' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Journal Entries are the manual double-entry records that move money between accounts in the ledger. Staff use this page to draft balanced entries, post them, and correct mistakes by reversal.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New entry", set the "Date" and "Narration", then add at least two lines with "Add line" so total debit equals total credit.',
        'The form shows a "Balanced" / "Unbalanced" indicator; you can only save and post once debits equal credits.',
        'Save a draft with "Save draft"; drafts can be reopened with "Edit draft" or removed, but only while still in draft.',
        'Click "Post" to commit the entry to the ledger — after posting it is immutable and cannot be edited.',
        'To fix a posted entry, use "Reverse" and enter a reversal reason; this creates an offsetting posted entry rather than changing the original.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I edit or delete an entry?', 'a' => 'Only draft entries can be edited or deleted. Once posted, an entry is locked; the only way to undo it is "Reverse".'],
        ['q' => 'What does "Post" actually do?', 'a' => 'It validates that the entry is balanced and falls in an open period, then writes it to the ledger so it affects account balances on the Chart of Accounts and the financial reports.'],
        ['q' => 'Why was my post rejected?', 'a' => 'Posting fails if the entry is unbalanced or its date falls inside a closed accounting period. Balance the lines or have an admin reopen the period first.'],
        ['q' => 'Who can create and post entries?', 'a' => 'Drafting, posting and reversing are limited to admin and super-admin users; others can view entries only.'],
    ]],
],
'expenses' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The Expenses page records the clinic\'s operational spending against expense accounts and vendors. Each expense is drafted, then posted to the ledger, and can later be voided.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New expense" and fill in the "Date", "Amount", "Expense account" and optionally a "Vendor"; the expense code is generated automatically.',
        'Choose a "Payment account" for cash/bank-paid expenses, or pick "On account (A/P)" to record it as payable.',
        'Save as a draft; you can reopen "Edit expense" while it is still in draft.',
        'Click "Post" to send the expense to the ledger — posting routes through the accounting engine and creates the journal entry.',
        'Use the search box and the "All statuses" filter (Draft / Posted / Void) to find expenses.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I "Void" a posted expense?', 'a' => 'Voiding reverses the expense\'s ledger entry, removing its effect on account balances while keeping the record for audit. Voiding is admin-only.'],
        ['q' => 'Why can\'t I edit a posted expense?', 'a' => 'Only draft expenses can be edited. Once posted you must void it (then optionally create a corrected one) instead of editing.'],
        ['q' => 'Why did posting fail?', 'a' => 'Posting fails if the required accounts are not configured. Make sure the expense account and payment/payable account exist and are active on the Chart of Accounts.'],
        ['q' => 'Can I delete an expense?', 'a' => 'You can delete a draft or voided expense, but a posted one must be voided first before it can be deleted.'],
    ]],
],
'vendors' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Vendors are the payees and suppliers the clinic incurs expenses with. Staff keep their contact and tax details here and pin default accounts so logging an expense is one click.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New vendor" and enter at least a "Name"; "Code", "Contact name", "Phone", "Email" and "Tax / Commercial Reg. No." are optional.',
        'Under "Default accounts", set a "Default expense account" and "Default payable account" to pre-fill new expenses for this vendor.',
        'Search by name, code or phone, and use the "All" / "Active" / "Inactive" filter to narrow the list.',
        'Click a row to open "Edit vendor", or toggle the "Active" switch to stop a vendor appearing in new expenses.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the default account do?', 'a' => 'When you create an expense and choose this vendor, its default expense account is pre-selected on the Expenses page, saving a step.'],
        ['q' => 'What happens when I delete a vendor?', 'a' => 'Deleting archives the vendor (a soft delete) so historical expenses keep their link intact; it is removed from the active list rather than erased.'],
        ['q' => 'Why doesn\'t a vendor appear when adding an expense?', 'a' => 'Only active vendors are offered in the expense form. Re-open the vendor and turn "Active" back on.'],
    ]],
],
'reconciliation' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Bank Reconciliation matches an imported bank statement against the ledger lines on a bank account for a period, so you can confirm the book balance agrees with the bank. Staff use it to find and clear differences, then lock the period\'s reconciliation.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New reconciliation", pick the bank "Account", set "Period start" / "Period end" and the "Opening balance" / "Closing balance".',
        'Open the reconciliation and click "Import statement" to upload the bank statement (CSV or Excel) as statement lines.',
        'Click "Auto-match" to pair statement lines with ledger entries automatically, or use "Match" / "Unmatch" on individual lines, and "Recompute" to refresh the book balance.',
        'Watch the "Difference" between statement and book balances — aim to bring it to zero.',
        'When balanced, an admin clicks "Complete" to lock it; a completed reconciliation can be unlocked again with "Reopen".',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Which ledger lines can I match against?', 'a' => 'Only posted journal entry lines on the same bank account within the reconciliation period are offered; draft entries are not matchable.'],
        ['q' => 'What does "Auto-match" do?', 'a' => 'It automatically pairs statement lines with matching posted ledger lines; it reports how many lines it matched, and you can still adjust matches manually with "Match" / "Unmatch".'],
        ['q' => 'Can I edit a completed reconciliation?', 'a' => 'No. Importing, matching, recompute and balance edits are only allowed while it is in progress. An admin must use "Reopen" first.'],
        ['q' => 'Who can complete or reopen?', 'a' => '"Complete" and "Reopen" are admin-only; other staff can still create, import and match while it is in progress.'],
    ]],
],
'periods' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Accounting Periods are the auto-created monthly windows that every journal entry attaches to. Staff use this page to review periods and, when a month is finished, close it so the books for that month are locked.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Filter the list with the "All" / "Open" / "Closed" status buttons and the "All years" selector.',
        'Each row shows the period "Code", date range, "Status", and once closed its "Closing entry", "Closed at" and "By".',
        'Click "Close" on an open period to lock it — this posts the closing journal entry and blocks any new entries dated in that period.',
        'Click "Reopen" on a closed period to unlock it; this reverses the closing entry and lets the period accept entries again.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does closing a period actually do?', 'a' => 'It posts a closing journal entry and prevents new or edited entries dated within that period, so posting on the Journal Entries and Expenses pages will be rejected for closed dates.'],
        ['q' => 'Can I undo a close?', 'a' => 'Yes — "Reopen" reverses the closing entry and reopens the period for posting. Both close and reopen are admin-only.'],
        ['q' => 'Why can\'t I create periods here?', 'a' => 'Periods are created automatically each month; this screen is read-only apart from the close/reopen lifecycle.'],
    ]],
],
'trial-balance' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only accounting report listing every active account with its total debit and credit for a date range, so you can confirm the books are balanced before producing financial statements.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Set the period with the "From" and "To" date pickers; it defaults to the start of the month through today.',
        'The table lists each account by "Code" and "Account" name with its "Debit" and "Credit" columns; a blank amount shows as a dash.',
        'The "Total" footer row sums all debits and all credits, which should be equal.',
        'Watch the status badge at the bottom right: "Balanced" (green) or "Out of balance" (red).',
        'Use the "Print" button for a clean printout; this page is view-only and has no editing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why does it say "Out of balance"?', 'a' => 'Total debits and total credits differ for the selected dates. The numbers come straight from posted journal entries via the accounting engine; an imbalance usually points to an unposted or malformed entry.'],
        ['q' => 'Can I change the figures here?', 'a' => 'No. This is a read-only report. Corrections are made in the underlying journal entries; the trial balance just re-aggregates them.'],
        ['q' => 'Why is an account missing?', 'a' => 'Only accounts with activity in the period appear; accounts with no movement and zero balance are not listed ("No activity in this period").'],
    ]],
],
'general-ledger' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only ledger that shows every journal line posted to one selected account over a date range, with a running balance, so staff can trace exactly what moved an account.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Choose the account in the searchable "Account" selector (shown as code and name); until you pick one the page prompts "Pick an account to see its ledger".',
        'Set the window with "From" and "To"; defaults to month-to-date.',
        'Optionally narrow to one site with the "Branch" filter, or leave it on "All branches".',
        'Read the three KPI cards: "Opening balance", "Period activity" and "Closing balance".',
        'The table lists each line by "Date", "Entry" (the journal entry code), "Description", "Debit", "Credit" and the running "Balance"; "Print" gives a hard copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What are the small labels under a description?', 'a' => 'They are the entry source plus its branch, doctor and patient where applicable, so you can trace the line back to the visit or document that created it.'],
        ['q' => 'How is the running balance calculated?', 'a' => 'It starts from the "Opening balance" before the From date and adds each line\'s debit or credit in date order, ending at "Closing balance". All figures come from posted journal entry lines.'],
        ['q' => 'Why is nothing shown yet?', 'a' => 'No account is selected, or the chosen account had "No activity in this period". Pick an account and adjust the dates.'],
    ]],
],
'profit-loss' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only income statement for a date range showing revenue, cost of goods sold, operating expenses and the resulting net profit. Staff use it to see whether the clinic made money in the period.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Set the reporting window with the "From" and "To" date pickers; it defaults to month-to-date.',
        'Read the statement top to bottom: "Revenue" (and "Revenue contra" if any) leading to "Net revenue".',
        'Then "Cost of goods sold" gives "Gross profit", and "Operating expenses" lead to the bold "Net profit" line.',
        'Accounts are indented under their parent groups; parent rows show a rolled-up total, deductions appear in red with a minus sign.',
        'Use "Print" for a copy. The page is read-only with no editing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Where do these numbers come from?', 'a' => 'They are aggregated from posted journal entries by the accounting engine for the dates you choose, grouped into revenue, COGS and expense accounts from the chart of accounts.'],
        ['q' => 'What is "Revenue contra"?', 'a' => 'Reductions to revenue (such as discounts or refunds) that net against gross revenue to give "Net revenue". The section only appears when there is contra activity.'],
        ['q' => 'Why is net profit negative?', 'a' => 'Costs and expenses exceeded net revenue in the period; the figure turns red. It is a reporting result only, not something you edit here.'],
    ]],
],
'balance-sheet' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only snapshot of the clinic\'s assets, liabilities and equity as of a single date, used to check overall financial position and that the books balance.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the snapshot date with the "As of" date picker (defaults to today); the report is a point-in-time view, not a range.',
        'The left card lists "Assets" (with "Less: accumulated depreciation" shown as a deduction) ending in "Total assets".',
        'The right card lists "Liabilities", then "Equity" including "Retained earnings (period)", ending in "Total liabilities & equity".',
        'Check the badge bottom right: "Balanced", or "Out of balance" with the difference shown as Δ.',
        'Use "Print" for a copy. Figures are read-only.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does "Retained earnings (period)" mean?', 'a' => 'It is the net profit accumulated into equity as of the snapshot date, so total equity reflects current earnings. It is computed by the accounting engine, not entered by hand.'],
        ['q' => 'Why is it out of balance?', 'a' => 'Total assets did not equal total liabilities plus equity as of that date; the Δ shows the gap. This usually traces back to an unbalanced or unposted journal entry.'],
        ['q' => 'Can I see a date range instead?', 'a' => 'No. A balance sheet is always a single point in time; use the "As of" date. For period totals use Profit & Loss or Cash Flow.'],
    ]],
],
'cash-flow' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only cash flow statement for a date range that explains how cash moved through operating, investing and financing activities, reconciling beginning cash to ending cash.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Set the period with the "From" and "To" date pickers; defaults to month-to-date.',
        'Read "Operating activities" starting from "Net income" with working-capital changes (payables, doctor payable, receivables, inventory) down to "Net cash from operations".',
        'Then "Investing activities" (fixed-asset purchases) and "Financing activities" (owner capital changes) each with their own subtotal.',
        'The "Net change in cash" plus "Cash, beginning" gives "Cash, ending"; positive amounts add cash, negatives are shown in red with a minus sign.',
        'Check the badge: "Reconciles" or "Doesn\'t reconcile" with the difference as Δ. Use "Print" for a copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why does a positive change show in red sometimes?', 'a' => 'The signs follow cash impact: an increase in receivables or inventory uses cash, so it is shown negative (red), while an increase in payables frees cash and shows positive.'],
        ['q' => 'What does "Doesn\'t reconcile" mean?', 'a' => 'The statement\'s computed ending cash did not match the actual cash account movement; the Δ shows the gap. Numbers are derived from posted journal entries by the accounting engine.'],
        ['q' => 'Can I edit any of these lines?', 'a' => 'No, the report is read-only; it only summarises posted accounting activity for the chosen dates.'],
    ]],
],
'reports' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only operational dashboard for clinic performance over a date range: visit counts, fees, item costs, profit and doctor compensation, plus top doctors and items. Staff use it to gauge clinic productivity.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Set the window with "From" and "To" (defaults to month-to-date), then optionally filter by branch ("All branches") and doctor ("All doctors").',
        'The five KPI cards show "Visits", "Fees", "Items cost", "Profit" and "Doctor cut" for the selection.',
        'The "Profit trend" chart shows daily profit as bars across the period.',
        'The "Top doctors" table ranks doctors by their compensation cut with visit counts; "Top items" ranks items by profit with quantity.',
        'All figures recompute when you change a filter; the page is view-only with no export button.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Which visits are counted?', 'a' => 'Visits whose costing was computed within the date range (by their computed date), filtered to the chosen branch and doctor. Profit and fees come from those visits\' totals.'],
        ['q' => 'Where does "Doctor cut" come from?', 'a' => 'From the doctor compensation ledger entries in the period; "Top doctors" sums each doctor\'s cut from the same ledger.'],
        ['q' => 'Why differ from the old today-only widgets?', 'a' => 'The v2 page honours your own From/To/branch/doctor filters instead of forcing today, so you can report on any period.'],
    ]],
],
'daily-closing' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only end-of-day summary for one date covering bookings, visit financials and per-doctor results, used to close out the day and review takings.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the "Date" (defaults to today) and optionally select one or more sites in the multi-select "Branches" list (leave empty for all).',
        'The "Bookings" card shows the total, "Checked in", "Auto no-show" and a "By status" breakdown.',
        'The "Financials · Visits" card lists fees, packages, items price, discount, "Total revenue" and "Profit".',
        'The "Hourly bookings" chart shows booking volume by hour of the day.',
        'The doctors table lists each doctor\'s completed visits, revenue and profit; use "Print" for a copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I report on several branches at once?', 'a' => 'The "Branches" control is a multi-select; pick as many as you need or leave it empty to include all branches.'],
        ['q' => 'What counts as "Auto no-show"?', 'a' => 'Bookings the system automatically marked as no-show because the patient never checked in; it is shown alongside "Checked in" in the Bookings card.'],
        ['q' => 'What is "Total revenue" made of?', 'a' => 'Fees plus packages plus items price, less discount, taken from the day\'s visits. The figures are read-only and computed by the daily closing service.'],
    ]],
],
'daily-reconciliation' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only daily cash-up listing every confirmed payment collected on one date, totalled and split by payment method and by who collected it, used to reconcile the till.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the "Date" (defaults to today) and optionally a "Branch" (defaults to "All my branches" you have access to).',
        'The headline shows "Total collected" and the "Payments" count for the day.',
        'The "By payment method" card breaks the total into Cash, Card, KNET and Link with bars.',
        'The "By collector" card shows how much each staff member collected (online payments appear as "System (online)").',
        'The detail table lists each payment by time, patient, doctor, kind, method, collector and amount; "Print" gives a copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Which payments are included?', 'a' => 'Only payments with status "paid" whose paid date falls on the selected day, within branches you are allowed to see. If you are a doctor it is also limited to your own visits.'],
        ['q' => 'Why do some payments show collector "System (online)"?', 'a' => 'Those were taken online (e.g. a payment link) with no staff member recording them, so the system is shown as the collector.'],
        ['q' => 'Can I edit a payment from here?', 'a' => 'No. This is a read-only reconciliation view; payments are managed on the visit record.'],
    ]],
],
'executive' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only high-level dashboard with headline KPIs and trends across the business: revenue, profit, margin, payment mix, booking sources, and branch, doctor and item performance, for the chosen period.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Choose the period with the segmented buttons "Today", "Week", "Month", "Quarter", "Year" or "Custom"; with "Custom" pick start and end dates.',
        'Optionally filter to one site with the branch selector ("All branches").',
        'The six KPI cards show "Revenue", "Profit", "Margin %", "Avg transaction", "Visits" and "Show rate %", each with a change indicator versus the prior period.',
        'Review the "Revenue trend" bars, "Payment mix" and "Booking sources" breakdowns.',
        'Scan the "Branch performance", "Doctor performance" and "Item profitability" tables plus "Cancellation analysis" and the "Follow-up funnel".',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the change percentage on a KPI mean?', 'a' => 'It compares the current period to the previous equivalent period, with an up/down trend arrow. The figures come from the executive dashboard service aggregating visits, payments and bookings.'],
        ['q' => 'How is "Show rate" calculated?', 'a' => 'It reflects the share of bookings where the patient actually attended (checked in) versus no-shows for the period.'],
        ['q' => 'Can I drill into a single doctor or item?', 'a' => 'Not from this dashboard; it is a read-only overview. Use Clinic Reports or the relevant record pages for detail.'],
    ]],
],
'clinics' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Clinics (partners) are the owning tenants of the system. Each clinic owns branches, staff, and medical specialties, and its details print on prescriptions and invoices.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New clinic" to add one; the modal asks for Name (English), Name (Arabic), and a "Code / slug" used in links.',
        'Fill the "MOH / commercial license" and "Print footer / disclaimer" fields, since the footer appears at the bottom of prescriptions and invoices.',
        'Tick the relevant "Medical specialties" chips to attach the services this clinic offers.',
        'Use the search box and the "All" / "Active" / "Inactive" filter to narrow the list, or "Export Excel" to download it.',
        'Click any row to edit it; the eye-off button deactivates a clinic (there is no hard delete).',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Who can manage clinics?', 'a' => 'This page is admin-only (admin or super_admin roles); other staff cannot open it.'],
        ['q' => 'Why can\'t I deactivate a clinic?', 'a' => 'A clinic that still has branches cannot be deactivated. Reassign or remove its branches on the Branches page first.'],
        ['q' => 'Where does the clinic name and footer show up?', 'a' => 'The clinic owns branches and its name plus the print footer/disclaimer appear on prescriptions and invoices generated under it.'],
    ]],
],
'branches' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Branches are the individual clinic locations, each owned by a clinic. This page keeps the contact details, city, and booking horizon staff need to configure.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New branch" and pick the owning "Clinic (owner)" from the searchable dropdown.',
        'Set the "Max advance booking days" to control how far ahead patients can book at this branch.',
        'Leave "Slug" empty to have it auto-generated, or type one to set the URL name.',
        'Tick "Available for booking" to expose the branch; untick to hide it from booking.',
        'Filter with "All" / "Available" / "Unavailable" or search by name, phone, or license; click a row to edit.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I delete a branch?', 'a' => 'Branches are never hard-deleted; the action just marks the branch "Unavailable" so its history is preserved.'],
        ['q' => 'Why must I choose a clinic?', 'a' => 'Every branch belongs to a clinic (partner), which owns and scopes its data; the clinic must exist first on the Clinics page.'],
        ['q' => 'Who can manage branches?', 'a' => 'This page is admin-only (admin or super_admin); other roles cannot access it.'],
    ]],
],
'gateways' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Gateway accounts are the payment options the booking and payment flows can offer. Each is either a manual/POS method (cash, KNET, card, payment link) or an online gateway account such as MyFatoorah.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New account", then choose "Manual / POS method" or "Online gateway" as the account kind.',
        'For manual, pick the "Manual payment method"; for a gateway, pick the "Gateway" from the list.',
        'Set a "Display name" and "Currency", and tick "Active" to enable it and "Default" to make it the preferred option.',
        'Use the "Owner" toggle (System / Clinic / Branch / Service) to scope which records the account applies to.',
        'Add free-form gateway settings under "Credentials (key / value)" with "Add key" (e.g. api_key, mode, country_iso).',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the difference between manual and gateway?', 'a' => 'A manual account records an offline/POS payment (cash, KNET, card, link); a gateway account drives an online card/electronic payment through a configured provider.'],
        ['q' => 'How does the owner scope work?', 'a' => 'The owner type (System, Clinic, Branch, or Service) decides where the account is offered — system-wide, or limited to a specific clinic, branch, or service.'],
        ['q' => 'Can I delete a gateway account?', 'a' => 'Yes — the trash button removes it permanently (unlike clinics and branches, which are only deactivated). This page is admin-only.'],
    ]],
],
'roles' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Roles & Permissions define what each role can see and do. Permissions a role carries gate every page and the sidebar, so a user only sees what their role allows.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New role", give it a "Role name", then tick the permissions it should carry.',
        'Permissions are grouped by the resource they govern; use the "Filter permissions…" box or "Expand all" / "Collapse all" to navigate.',
        'Use a group\'s "All" and "None" buttons to bulk-toggle a whole resource at once.',
        'Click any role row to edit it; the counter shows how many permissions are "selected".',
        'Use "Save" to apply, or "Delete" to remove a role that has no users assigned.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I rename or delete some roles?', 'a' => 'The admin and super_admin roles are "Protected" — they are wired into the code, so they can\'t be renamed or deleted, but their permissions are still editable.'],
        ['q' => 'Why can\'t I delete a role?', 'a' => 'A role still assigned to users cannot be deleted; reassign those users to another role first. The delete button only appears when the user count is zero.'],
        ['q' => 'How do permissions affect other pages?', 'a' => 'A role\'s permissions gate access across the whole app — every page and sidebar item is shown or hidden based on what the user\'s role allows.'],
    ]],
],
'settings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Settings holds the global system configuration, including the WhatsApp integration. These values are platform-wide and apply across all clinics and branches.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Edit the typed fields grouped into "General", "WhatsApp API", and "WhatsApp Templates".',
        'Each field is the right input type — checkbox, number, text, or a masked password for secrets.',
        'For a secret field, leave it blank to keep the stored value; only type to replace it.',
        'Click "Save settings" to apply your changes.',
        'If you only have view permission the fields are disabled and the "Save settings" button is hidden.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I see the value of a secret like an API token?', 'a' => 'Secret values are never sent back to the browser — the field only shows whether one is "Set". Leaving it blank on save keeps the current secret untouched.'],
        ['q' => 'Are these settings per-branch?', 'a' => 'No — settings are global to the whole platform; there is one fixed set of allowed keys shared by every clinic and branch.'],
        ['q' => 'Why are the fields greyed out for me?', 'a' => 'Viewing needs the view_any_system_setting permission and editing needs update_system_setting; without edit rights the fields are read-only and the save button is hidden.'],
    ]],
],
'activity' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The Activity Log is an immutable audit trail of every system change — who did what and when. It is read-only and used for review and troubleshooting.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by description or subject in the search box at the top.',
        'Narrow results with the "All logs" log picker and the "All events" picker (Created / Updated / Deleted / Restored).',
        'Set the "From" and "Until" date pickers to limit the time range.',
        'Read the "Changes" column to see each changed field as old → new values.',
        'Use "Clear" to reset all filters back to the full list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I edit or delete log entries?', 'a' => 'No — the activity log is read-only and immutable; there are no create, edit, or delete actions on this page.'],
        ['q' => 'Who can view the activity log?', 'a' => 'It is restricted to admin and super_admin roles; other staff cannot open it.'],
        ['q' => 'What does the "By" column show?', 'a' => 'It shows the user who caused the change; automated/background changes appear as "— system".'],
    ]],
],
'wa-triggers' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you define the WhatsApp bot\'s auto-reply rules. Each trigger (a keyword, the welcome message, the post-booking finale, or the fallback for unrecognized text) maps to a response the bot sends back. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New trigger", then pick a "Trigger type" (Keyword, Welcome, Finale, or Fallback) and a "Response type".',
        'For keyword triggers, type each word in the "Keywords" box and press Enter to add it as a tag.',
        'Choose how the bot replies: "Text", "Link", "Image", "Document", "Buttons", "List", "Template", or "Flow" — the editor shows the matching fields (captions, buttons, sections, template params, or Flow ID).',
        'Fill both the "Response (English)" and "Response (Arabic)" fields so the bot can answer in the customer\'s language.',
        'Tick "Active" and click "Save". Click any row to edit, or use the trash icon to delete.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What do the four trigger types do?', 'a' => '"Keyword" fires when the customer sends a matching word; "Welcome" opens a new conversation; "Finale" is sent after a booking completes; "Fallback" replies when the bot does not understand the message.'],
        ['q' => 'How is a "Template" trigger different from a Campaign?', 'a' => 'A Template trigger sends a Meta-approved template as an automatic reply inside a live chat. Campaigns send a template in bulk to many numbers at once.'],
        ['q' => 'How do I add an image or document the bot will send?', 'a' => 'Pick the "Image" or "Document" response type and upload the file in the editor; the uploaded file is stored and reused until you replace it.'],
        ['q' => 'Will turning off "Active" delete the trigger?', 'a' => 'No. An inactive trigger is kept but the bot ignores it until you tick "Active" again. Use the trash icon to remove it permanently.'],
    ]],
],
'wa-campaigns' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page runs bulk WhatsApp campaigns that send a Meta-approved template to many phone numbers at once. Staff use it to invite or notify large audiences. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New campaign", give it a "Campaign name", pick a "Meta template", set "Max sends / minute", optionally set "Schedule at", then click "Create".',
        'On the campaign page, fill any "Template variables" and a "Header image path" if the template needs one, then click "Save changes".',
        'Add audience under "Add recipients": paste numbers (one per line), choose a "Preferred region" and optional "Locale"/"Name", then click "Add".',
        'Use "Send test" with a single "Test phone" to preview a real message to yourself before launching.',
        'Click "Validate & queue" to check the template/variables and queue every pending recipient for sending.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Does sending cost money?', 'a' => 'Yes. Each delivered template message is a paid WhatsApp business message billed by Meta, so a large recipient list multiplies the cost. Use "Send test" first and double-check the audience.'],
        ['q' => 'Why is "Validate & queue" blocked?', 'a' => 'It refuses to send if no valid template is selected, a required template variable is empty, a template needing a header image has none set, there are no recipients, or the campaign is already running/completed.'],
        ['q' => 'What does "Max sends / minute" do?', 'a' => 'It throttles how fast messages go out (minimum 60/min) to respect WhatsApp rate limits. The status moves to "scheduled" if you set a schedule, otherwise "running" once queued.'],
        ['q' => 'Where do I see what was delivered or failed?', 'a' => 'The recipients table shows each number\'s status and any error. The status counts update as the send job runs, and the WhatsApp Logs page records the underlying delivery.'],
    ]],
],
'wa-commands' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page manages the keyword shortcuts the bot recognizes inside a chat — words that reset the conversation, start it, show the menu, or jump to a specific step. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New command", enter the "Keyword" the customer might type and pick its "Language".',
        'Choose an "Action": Reset & Start, Start, Show Menu/Help, or Jump to State.',
        'If you choose Jump to State, fill the "Target state (for jump)" with the step the bot should jump to.',
        'Set a "Priority" (higher numbers win when multiple commands match) and tick "Enabled".',
        'Click "Save". Click a row to edit, or use the trash icon to delete.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How are commands different from triggers?', 'a' => 'Commands are control shortcuts that steer an ongoing conversation (reset, start, menu, jump). Triggers decide the actual reply content the bot sends.'],
        ['q' => 'What does Priority do?', 'a' => 'When more than one command could match, the bot uses the one with the highest priority first.'],
        ['q' => 'Why does the language matter?', 'a' => 'A command only applies to chats in its language (EN or AR), so add both versions if customers use both languages.'],
    ]],
],
'wa-messages' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the catalog of the bot\'s own message templates, stored by a key and language with {token} placeholders the bot fills in at send time. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New template", enter the "Key" that identifies the message and pick its "Language".',
        'Write the message in "Text", using {token} placeholders where the bot should insert values.',
        'Tick "Enabled" and click "Save"; click any row to edit or use the trash icon to delete.',
        'Filter with the search box ("Search key or text…") and the language selector to find a specific message.',
        'Use "Export Excel" to download the current filtered list as a styled spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Are these the same as Meta-approved templates?', 'a' => 'No. These are the bot\'s internal worded replies. Meta-approved templates (used by Campaigns and Template triggers) are managed in Meta and referenced by name.'],
        ['q' => 'What are the {token} placeholders?', 'a' => 'They are slots the bot replaces with real values (such as a name or date) when it sends the message; keep the token text intact so the substitution works.'],
        ['q' => 'What happens if I disable a template?', 'a' => 'The bot stops using that keyed message until you re-enable it; the record itself stays in the catalog.'],
    ]],
],
'wa-texts' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the reusable message catalog — short copy strings stored by a key and locale and reused across the app. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New string", enter a unique "Key" and choose the "Locale" (English or Arabic).',
        'Type the wording in "Text" and click "Save".',
        'Click any row to edit the string, or use the trash icon to delete it.',
        'Filter by the search box ("Search key or text…") and the language selector to locate a string.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How is this different from WhatsApp Templates?', 'a' => 'WhatsApp Templates are the chatbot\'s replies; this catalog holds general reusable copy strings used across the app, one row per key and locale.'],
        ['q' => 'Can I have the same key twice?', 'a' => 'Only once per locale — a key must be unique within English and within Arabic, so you typically create one English and one Arabic row sharing the same key.'],
        ['q' => 'Why can\'t I find a string I expected?', 'a' => 'Check the locale filter; the same key can exist separately for English and Arabic and the list filters by the chosen language.'],
    ]],
],
'wa-logs' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only log of every inbound and outbound WhatsApp message, showing its Meta message ID, phone, delivery status, and time. Staff use it to confirm messages went out and to troubleshoot. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by phone or message ID in the "Search phone or message id…" box.',
        'Narrow results with the status selector ("All statuses") to focus on a delivery state.',
        'Click any row to open the "Payload" panel and inspect the full raw message data.',
        'Click "Export Excel" to download the filtered log as a styled spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I edit or resend a message here?', 'a' => 'No. This page is read-only; it only records what was sent or received. Re-sending a campaign is done from the Campaigns page.'],
        ['q' => 'A campaign message shows as failed — where do I see why?', 'a' => 'Open the row to view the payload for the error detail; the campaign\'s recipients table also shows a per-number error message.'],
        ['q' => 'What is the message ID?', 'a' => 'It is the WhatsApp/Meta identifier (wamid) for that specific message, useful for matching a log entry to a delivery report.'],
    ]],
],
'wa-sessions' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only view of live WhatsApp conversation state — one row per phone showing the current bot screen, provider, service type, locale, status, and last interaction time. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by phone in the "Search phone…" box to find a specific conversation.',
        'Filter by the status selector ("All statuses") to see only certain session states.',
        'Read the "Screen" column to see where each customer currently is in the bot flow.',
        'Click a row to open the "Context" panel and inspect the session\'s stored data.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is a session and the 24-hour window?', 'a' => 'A session tracks one customer\'s ongoing chat. WhatsApp only allows free-form replies within 24 hours of the customer\'s last message; "Last interaction" tells you whether that window is still open.'],
        ['q' => 'Can I message or reset a customer from here?', 'a' => 'No, this page is read-only. To change conversation behavior, adjust Commands and Triggers; sessions only reflect current state.'],
        ['q' => 'What does the "Screen" value mean?', 'a' => 'It is the current step in the bot conversation flow, so you can tell, for example, whether a customer is mid-booking or finished.'],
    ]],
],
'wa-audience' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A read-only, per-phone engagement rollup (bookings, confirmations, last branch, last interaction) used to understand and build WhatsApp campaign audiences. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Search by phone in the "Search phone…" box.',
        'Set "Min bookings" to keep only contacts with at least that many bookings.',
        'Use the "From" and "To" date pickers to filter by last booking date.',
        'Read the "Bookings", "Confirmed", "Last branch", and "Last interaction" columns to judge who to target.',
        'Click "Export Excel" to download the filtered audience as a styled spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I launch a campaign directly from here?', 'a' => 'No, this page is read-only metrics. Use it to decide who to target, then add those numbers on the Campaigns page.'],
        ['q' => 'Where does this data come from?', 'a' => 'It is rolled up automatically from booking activity per phone number; you cannot edit the figures here.'],
        ['q' => 'How do I export a target list?', 'a' => 'Apply the filters you want (min bookings, date range), then click "Export Excel" to get the matching contacts as a spreadsheet.'],
    ]],
],
'stock-transfers' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Move stock between your clinic\'s branches. The central hub branch dispatches items to a branch that is short — each transfer reduces the hub\'s on-hand and increases the receiving branch\'s, with a stock movement recorded at both ends. Transfers are inventory moves only; they do not post to the accounting books.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New transfer", choose the source branch (defaults to the hub) and the destination branch, then use "Add" to add each item with the quantity to move.',
        'Click "Create transfer" to save it as Pending — nothing has moved yet.',
        'A user with dispatch rights clicks "Dispatch" on a pending row to commit the move: stock leaves the source and arrives at the destination.',
        'Click "Cancel" to drop a pending transfer you no longer need.',
        'Use the "All / Pending / Dispatched / Cancelled" tabs to filter the list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why does it say "No hub set for this clinic"?', 'a' => 'A transfer needs a designated hub branch to dispatch from. Open a branch in its settings and mark it as the hub; until then the "New transfer" button is limited.'],
        ['q' => 'When does stock actually move?', 'a' => 'Only on "Dispatch". Creating a transfer leaves it Pending and does not change any on-hand quantity; dispatching writes the stock movements that decrement the source and increment the destination.'],
        ['q' => 'Does a transfer affect the accounting books?', 'a' => 'No. Moving stock between your own branches does not change the total inventory value, so transfers are GL-neutral — no journal entry is posted.'],
        ['q' => 'Can I send more than a branch has on hand?', 'a' => 'No — the quantity available at the source limits what you can dispatch; the item picker shows the hub on-hand for each item.'],
    ]],
],
'payroll-runs' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Monthly payroll. A run gathers every active staff member into one batch of payslips — basic salary, allowances, doctor commission, and deductions for loans and unpaid leave — then posts the salary cost to the accounting books and disburses the net pay.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New payroll run", pick the year, month and (optionally) a branch and pay date, then "Create". Payslips are generated automatically from the active salary profiles.',
        'Open the run to review each payslip. Click "Regenerate" to rebuild them after changing a salary profile, loan or leave record while the run is still a draft.',
        'Click "Approve & post" to lock the run and post the salary accrual to the ledger (Dr Payroll expense / Cr Payables).',
        'Click "Mark paid", choose the bank or cash account to pay from, and confirm — this posts the disbursement (Dr Payables / Cr Cash) and settles doctor commission and withheld loan installments.',
        'Use "Delete draft" to remove a run that has not been approved, or "Export Excel" to download the payslips.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Where do the payslip amounts come from?', 'a' => 'From each staff member\'s salary profile (basic + allowances − recurring deductions), plus any doctor commission earned, minus the loan installment due and any unpaid-leave deduction for the period.'],
        ['q' => 'Can I edit a payslip after approving?', 'a' => 'No — approving locks the run and posts it to the books. Make corrections while it is still a draft, regenerate, then approve.'],
        ['q' => 'How are doctor commissions handled?', 'a' => 'Commission a doctor has already earned is included in their payslip and settled against the doctor compensation ledger when the run is marked paid, so it is not expensed twice.'],
        ['q' => 'What does marking paid actually do?', 'a' => 'It posts the cash disbursement, records the loan repayments withheld this run against each staff loan, and stamps the run as paid on the chosen date.'],
    ]],
],
'salary-profiles' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Each staff member\'s monthly salary structure — basic salary, recurring allowances and deductions, annual leave entitlement, and the hire/termination dates that drive payroll and end-of-service gratuity. Payroll runs read these profiles to build payslips.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "Add profile", pick the staff member, set the "Basic salary" and "Annual leave days", and add any "Allowances" and "Deductions" as labelled lines; the gross monthly total updates live.',
        'Optionally set the "Hire date" (used for gratuity) and a branch; tick "Active" for staff currently on payroll.',
        'Click a row\'s edit icon to amend a profile, or the trash icon to remove it.',
        'Use the search box and the "All / Active / Inactive" filter to find profiles.',
        'Use "Export Excel" to download the list, or "Import" to bulk-load profiles from a spreadsheet during onboarding.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can one staff member have two profiles?', 'a' => 'No — there is one salary profile per person. Importing again updates the existing profile rather than creating a duplicate.'],
        ['q' => 'How do allowances and deductions work?', 'a' => 'Each is a labelled recurring amount (e.g. Housing, Transport). The gross monthly figure is basic + allowances; recurring deductions are taken in each payroll run.'],
        ['q' => 'How do I bulk-load salaries when setting up?', 'a' => 'Click "Import", download the template, fill the Data sheet (staff by email, basic salary, allowances as "Label:amount" pairs), then upload and Preview before confirming.'],
        ['q' => 'What is the hire date used for?', 'a' => 'It sets the start of service, which the End of Service page uses to compute Kuwait-law gratuity.'],
    ]],
],
'staff-loans' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Staff loans and salary advances, repaid by withholding an installment from each payroll run. Approving a loan disburses it and posts to the accounting books; the outstanding balance falls as payroll withholds repayments until it is settled.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New loan", pick the staff member, choose "Loan" or "Advance", and enter the "Principal amount", the "Installment amount" to withhold each run, and the issue date. It is saved as Pending.',
        'Click "Approve" to disburse the loan — this pays it out and posts Dr Loans Receivable / Cr Cash. Only pending loans can be edited.',
        'Each payroll run automatically withholds the installment and reduces the outstanding balance; the loan auto-settles when it reaches zero.',
        'Use "Cancel" for a loan that should not proceed, or "Export Excel" / "Import" to download or bulk-load loans.',
        'Filter with the search box and the type and status selectors.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'When is the loan actually paid out and booked?', 'a' => 'On "Approve" — that posts the disbursement (Dr Loans Receivable / Cr Cash or Bank) and activates the loan. A pending loan has not been paid out.'],
        ['q' => 'How is a loan repaid?', 'a' => 'Each payroll run withholds the installment from the staff member\'s net pay and records it against the loan, lowering the outstanding balance until it is fully settled.'],
        ['q' => 'Can I delete a disbursed loan?', 'a' => 'No — once it has posted to the books it is kept for the audit trail. Cancel it instead, or let it settle through repayments.'],
        ['q' => 'How do I load existing loans from another system?', 'a' => 'Use "Import" — imported loans come in as opening balances with their current outstanding amount and do not post a disbursement entry, so they don\'t double-count the ledger.'],
    ]],
],
'leave-balances' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Annual leave balances per staff member per year. The balance is entitled days + days carried over from last year − days already used (approved leave), so it always reflects what each person has left. "Used" is computed live from approved leave and is never entered here.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the year at the top; the table shows each staff member\'s entitled, carried-over, used, pending and remaining days.',
        'Click the edit action on a row to set that person\'s "Entitled days" and any "Carried-over days" for the year.',
        'Click "Seed year from profiles" to create default entitlements for everyone who does not yet have one, using the annual-leave days from their salary profile.',
        'Use the search box to find a staff member, and "Import" to bulk-load entitlements for a year.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I edit the "Used" days?', 'a' => 'Used days are calculated live from approved leave requests on the Leaves page, so the balance can never drift out of sync — you only set the entitlement.'],
        ['q' => 'What does "Seed year from profiles" do?', 'a' => 'For every active staff member without an entitlement for the chosen year, it creates one using the annual-leave days set on their salary profile. It never overwrites an entitlement you have already set.'],
        ['q' => 'What are carried-over days?', 'a' => 'Unused balance brought forward from the previous year; it is added to this year\'s entitlement when computing the remaining days.'],
        ['q' => 'How do "pending" days differ from "used"?', 'a' => 'Pending days are requested but not yet approved leave; they are shown for awareness but only approved leave counts as used against the balance.'],
    ]],
],
'settlements' => [
    'what' => ['heading' => 'What is this?', 'body' => 'End-of-service settlements. When a staff member leaves, this computes their Kuwait-law gratuity from years of service, adds any leave encashment, subtracts outstanding loans, and posts the final settlement to the accounting books.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New settlement", pick the staff member and their "Last working day", and choose the reason for leaving — "Termination / non-renewal" (full gratuity) or "Resignation" (reduced).',
        'The form shows a live preview: years of service, gratuity, leave encashment, outstanding-loan clawback and the net settlement. Adjust "Other additions" or "Other deductions" if needed, then "Save draft".',
        'Click "Approve" to post the settlement to the ledger and clear the staff member\'s outstanding loans.',
        'Click "Pay" to disburse the net amount; use "Edit" or "Delete" on drafts.',
        'Filter with the "All / Draft / Approved / Paid" tabs.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How is the gratuity calculated?', 'a' => 'From the staff member\'s years of service (hire date to last working day) and basic salary, following Kuwait labour-law rates. Resignation applies the reduced scale; termination or non-renewal gives the full entitlement.'],
        ['q' => 'Why does it say the staff member has no salary profile?', 'a' => 'Gratuity needs a basic salary and hire date. Add a salary profile for the person first, then create the settlement.'],
        ['q' => 'What is the loan clawback?', 'a' => 'Any outstanding staff loan balance is deducted from the settlement, and approving the settlement clears those loans so nothing is left owing.'],
        ['q' => 'What does approving post to the books?', 'a' => 'It accrues the end-of-service expense and settles the staff member\'s loans; marking it paid then disburses the net amount from the chosen account.'],
    ]],
],
'visit-console' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The visit console is where a single patient visit is worked end to end — clinical notes, the services and items billed, payments, and moving the visit through its lifecycle to completion. It is the screen the doctor and reception share for one visit.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Move between the "Overview", "Items", "Payments" and "Notes" tabs to reach each part of the visit.',
        'Clinical fields ("Chief complaint", "Diagnosis", "Prescriptions", "Lab requests", "Follow-up date" and so on) save automatically as you type — a "Saved" toast confirms each change.',
        'Use the primary action button at the top right, which changes with the status: "Start treatment", then "Complete visit" or "Discharge to payment".',
        'On the "Items" tab click "Add item" to search the catalogue and bill a service or consumable, add a package, or request stock items for the pharmacy.',
        'On the "Payments" tab click "Record payment" — set the amount, kind and method; the "Visit balance after" shows what remains. Use "Void payment" to reverse one entered by mistake.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I "Complete visit"?', 'a' => 'It closes the visit and recomputes its financials, and — if the patient has an active insurance policy — auto-drafts a claim on the Insurance Claims page. A completed visit is locked from further edits.'],
        ['q' => 'Why do some items reduce stock?', 'a' => 'Stockable consumables deduct from on-hand inventory and write a Stock Movements entry when added or when a stock request is fulfilled; plain services do not affect stock.'],
        ['q' => 'What does setting a "Follow-up date" do?', 'a' => 'It creates a follow-up plan (visible on the Follow-up Plans page) so the patient is brought back, and can auto-create the follow-up booking.'],
    ]],
],

    ],
];
