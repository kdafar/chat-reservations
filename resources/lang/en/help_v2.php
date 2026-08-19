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
    'what' => ['heading' => 'What is this?', 'body' => 'Your daily overview of how the clinic is doing today — money taken, visits, no-shows, average wait time, plus today\'s bookings and the latest activity. It updates by itself about every 20 seconds.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Look at the four cards along the top — "Today\'s revenue", "Today\'s visits", "No-shows" and "Avg. wait". Revenue and visits also show an up or down arrow comparing today with yesterday.',
        'Use the "Revenue — last 30 days" line and the "Doctor utilization today" bars to see how the clinic is trending and who is busiest.',
        'Click "New booking" at the top right to add an appointment without leaving this page.',
        'In "Today\'s bookings", click any row to open that booking, or click "View all" to see the full bookings list.',
        'In "Recent activity", click a row to open that patient\'s visit, or click "View all" to go to the waiting queue.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why are my numbers different from a colleague\'s?', 'a' => 'You only see figures for the branches you work in. Revenue counts payments that have actually been collected, and the visit and no-show counts are only for today.'],
        ['q' => 'What does "Avg. wait" mean?', 'a' => 'It is the average time, in minutes, from when a patient is checked in until the doctor starts seeing them. Starting visits sooner from the waiting queue brings this number down.'],
        ['q' => 'Can I change anything here?', 'a' => 'Only the "New booking" button adds something. Everything else is just for viewing — to act on a booking or visit, click through to it.'],
    ]],
],
'waiting' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A live list of everyone in the clinic right now: patients waiting, in treatment, awaiting stock, and (for reception) ready to pay, plus today\'s booked patients who have not arrived yet. It updates on its own so the front desk and doctors always see who is where.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Tap one of the count boxes at the top ("In queue", "Waiting", "In treatment", "Awaiting stock") or a filter button to show only those patients, and type a name, file number, booking code or phone in the search box to find someone fast.',
        'On a booked patient who has not arrived, click "Check in" to register them, or open the "..." menu to "Mark no-show", "Cancel booking" or "Reschedule...".',
        'On a patient already in the queue, click "Open visit" to open their visit; if the card says "Ready for payment" the button instead reads "Take payment".',
        'Click any card to open the Quick view side panel, where you can "Call" the patient, see their fee and balance, change the assigned doctor, or "Open visit".',
        'Reception staff see "New booking" and "Check in" buttons at the top right; doctors instead see a "Today\'s schedule" strip of their own appointments.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why do I see different patients than a coworker?', 'a' => 'Doctors only see patients assigned to them who have checked in. Reception and admins see the whole clinic, including patients who have not arrived yet and visits waiting to be paid.'],
        ['q' => 'A patient finished with the doctor but still shows here. Why?', 'a' => 'If the card says "Ready for payment" it stays on the list until reception collects the remaining balance and finishes the visit.'],
        ['q' => 'Do I need to refresh the page?', 'a' => 'No. The list updates by itself. You can tap "Refresh" at the top if you want to see the very latest right away.'],
    ]],
],
'checkin' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A simple three-step screen for the front desk: find today\'s booking, take the consultation fee, and give the patient a room. Once you finish, the patient appears on the Waiting Patients list for the doctor.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Under "Find booking", type the booking code, phone number, or patient name. Today\'s bookings appear below — click the right one to open it.',
        'Under "Collect fee", you\'ll see the amount due. Choose a payment method (for example Cash, Card, or KNET), enter a transaction/reference number if asked, then click "Collect payment". If the doctor has no fee or it is already paid, this step is skipped for you.',
        'To let the patient pay online, click "Generate payment link / QR" — the patient can scan the QR code or you can use "Copy link" or "Send WhatsApp". After they pay, click "Check payment status" to continue.',
        'Under "Assign room", click an available room, then click "Check in". If you don\'t need a room, click "Skip room".',
        'On the success screen, click "Open visit" to go to the patient\'s visit, or "Start over" to check in the next patient.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I check the patient in?', 'a' => 'If the doctor charges a consultation fee, you must collect it first. Bookings that are cancelled, marked no-show, already completed, or already checked in cannot be checked in again — these show as "Already checked in" and are greyed out.'],
        ['q' => 'I can\'t find the booking — what\'s wrong?', 'a' => 'Only today\'s bookings show here. Double-check the booking code or phone number, and make sure the appointment is for today and not already checked in.'],
        ['q' => 'What happens after I check the patient in?', 'a' => 'The booking is marked as arrived, the room you picked becomes occupied, and the patient moves onto the Waiting Patients list so the doctor can start the visit.'],
    ]],
],
'bookings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the list of all your appointments. Reception uses it to find a booking, see who is coming, and take any action on it.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Find a booking with the search box (type a name, phone, or booking code), the time tabs ("Today", "Tomorrow", "This week", "This month", "Past", "Any time"), and the "All branches" / "All doctors" pickers.',
        'Tap the status chips ("Pending", "Confirmed", "Completed", "Cancelled", "No-show") to show only those bookings. Each chip shows how many there are.',
        'Click "New booking" to add an appointment, or "Check-in" to mark a patient as arrived.',
        'Click any row to open its details panel. From there use "Open visit" or "Check in", "Collect fee", "Reschedule", "Assign room", "WhatsApp", "Resend confirmation", "Print", "Edit", or "No-show" / "Cancel".',
        'To download a list, tick the boxes on the rows you want, then click "Export Excel" in the bar at the bottom.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why don\'t I see "Cancel", "No-show" or "Reschedule" on some bookings?', 'a' => 'Those buttons only show when they make sense. You cannot cancel or reschedule a patient who has already arrived or whose booking is finished, and "No-show" only appears for upcoming bookings the patient has not arrived for.'],
        ['q' => 'What does "Collect fee" do?', 'a' => 'It takes the consultation payment for the booking right here. It is the same as paying at the check-in desk, so the patient can then be checked in.'],
        ['q' => 'Where does "Open visit" take me?', 'a' => 'To the patient\'s visit screen, where the clinical and billing work is done. It appears once a visit has started for that booking, for example after you collect the fee or check the patient in.'],
    ]],
],
'visits' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A searchable list of every visit in the clinic. From here you open any visit to do the actual work on it.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Find a visit by typing a patient name or phone in the search box, or narrow the list with the doctor, branch, and status pickers and the From/Until dates.',
        'Use the Accepted buttons (All / Accepted / Not accepted) to show only visits a doctor has already started, or only those still waiting to be started.',
        'Click any row to open that visit, where you add items, take payment, request stock, and mark it complete.',
        'Read the Total and Completed chips above the table for a quick count of what is currently showing.',
        'To download visits, tick the boxes on the rows you want, then click Export Excel in the bar at the bottom.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I edit a visit\'s items or take payment?', 'a' => 'Not from this list. Click the row to open the visit, where you add items and packages, record or cancel payments, request stock, and complete the visit.'],
        ['q' => 'Why is a visit missing from my list?', 'a' => 'You only see visits for the branches you work in, and the filters you set hide the rest. Widen the date range or click Clear to show more.'],
        ['q' => 'What is the Recompute button on completed visits?', 'a' => 'It rebuilds the money figures (fees and profit) for that visit. Use it if the totals look wrong after a change. It only shows when fees are turned on for your clinic.'],
    ]],
],
'doctor-schedule' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A day-by-day list of one doctor\'s appointments. For each booking you see the time, the patient, whether they have arrived, and a quick WhatsApp button. It is for viewing only — you actually check patients in from the Check-in desk.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Choose who you want to see in the "Doctor" box (if you are signed in as a doctor, you only see your own list).',
        'Tap "Today", "Tomorrow", "This week" or "All upcoming" to pick which days to show.',
        'Tap "All times", "Morning", "Afternoon" or "Evening" to show only that part of the day.',
        'On any appointment, tap the WhatsApp icon to message the patient, or "Check in" to open the Check-in desk for that patient.',
        'Glance at the "Appointments", "Checked in" and "Pending" boxes at the top to see the totals for what you are viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I check a patient in from this page?', 'a' => 'No, this page only shows the schedule. Tapping "Check in" takes you to the Check-in desk, where you confirm the booking, take any payment and assign a room.'],
        ['q' => 'Why can\'t I change the doctor?', 'a' => 'If you are signed in as a doctor, the page is locked to your own appointments. Reception and admin staff can choose any doctor.'],
        ['q' => 'Which appointments show up here?', 'a' => 'Only confirmed and pending bookings for the doctor, days and time you picked. Cancelled, finished and no-show bookings are not shown.'],
    ]],
],
'patients' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of every patient in the clinic. Use it to find someone fast, check their phone number and medical alerts, and jump to their full file or a new booking.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type in the search box to find a patient by name, phone, civil ID or file number. Use the Male / Female buttons and the Has phone / No phone buttons to narrow the list.',
        'Click "New patient" to add someone new, or "Import" to add many patients at once from a file.',
        'Click any row in the list to open a quick card showing their contact details, medical alerts, total visits, total paid, last visit and any upcoming bookings.',
        'On that quick card, click "New booking" to book the patient, or "Open profile" to see their complete file.',
        'To download a group of patients, tick the boxes next to their rows and click "Export Excel" in the bar that appears at the bottom.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why do some rows have a red warning triangle?', 'a' => 'That patient has recorded allergies. Click the row to read the full allergy and medical-alert details in the card that opens.'],
        ['q' => 'How do I change a patient\'s details?', 'a' => 'Click the row, then "Open profile", then use "Edit" on the profile page. This list is just for searching and viewing.'],
        ['q' => 'Why don\'t I see every patient in the clinic?', 'a' => 'Depending on your role you may only see patients connected to bookings you work with. If you expect to see more, ask an administrator.'],
    ]],
],
'patient-files' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A single place to browse every file attached to your patients\' records — lab reports, prescriptions, imaging, insurance cards and more. Use it to find and open a document quickly without opening each patient one by one.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type in the search box to find a file by its name, its notes, or the patient\'s name.',
        'Use the "All categories" dropdown to show only one type of document, like Lab Report or Prescription.',
        'Pick a "From" and "To" date to show only files added in that period.',
        'On any row, click the eye icon to View the file, or the download icon to save it to your computer.',
        'Click "Export Excel" to save the list you are looking at as a spreadsheet, or "Clear" to remove all filters and see everything again.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I add a new file?', 'a' => 'You upload files from inside the patient\'s profile, not here. Open the patient (click their name in the list), go to their Files section, and upload there. This page is just for browsing and opening files.'],
        ['q' => 'How do I open the patient a file belongs to?', 'a' => 'Click the patient\'s name in the "Patient" column and it will open that patient\'s profile.'],
        ['q' => 'Can I delete a file from this page?', 'a' => 'No. Deleting is done from the patient\'s profile. This page is only for finding, viewing and downloading files.'],
    ]],
],
'follow-up-plans' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of patients who need to come back for a follow-up. Each plan is created automatically when a visit is saved, so you can see who is due and whether their next appointment was already booked.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type a patient name or phone in the search box to find a specific person.',
        'Use the "All / With booking / Without booking" buttons to focus on follow-ups that still need an appointment made.',
        'Set the "From" and "Until" dates to see only follow-ups due in that period.',
        'Check the "Auto-book" column: a green tick means an appointment was already created, a dash means there is none yet.',
        'Click the visit code in "Source visit" to open the original visit, or use "Export Excel" to download the list you are viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I add or change a follow-up plan here?', 'a' => 'No, this page only shows them. A plan is set when the visit is saved, so to change it you open and edit that visit.'],
        ['q' => 'A row says "Without booking" - what should I do?', 'a' => 'No appointment exists yet for that patient. Book their follow-up manually so they are not missed.'],
        ['q' => 'What do the three boxes at the top mean?', 'a' => 'They count your follow-ups: Total, how many already have a booking, and how many still need one.'],
    ]],
],
'inpatient-board' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A live map of every bed in every ward. Each bed is a colour-coded card: green means free, red means a patient is in it, blue means it is being cleaned, grey means out of service, and amber means it is being held. This is your starting point for checking in patients and keeping beds up to date.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click a green (Available) bed, or the "Admit patient" button at the top, to open the check-in form for that bed.',
        'In the form, search for the patient by name or phone, pick the Admitting doctor, type the Admission reason, add a Diagnosis if you have one (and pick a Branch if the form asks), then click "Admit".',
        'Click a red (Occupied) bed to open that patient\'s panel, where you can move them to another bed, discharge them, log doctor rounds, and add charges.',
        'Click a blue (Cleaning), grey (Maintenance) or amber (Reserved) bed to put it back to Available once it is ready.',
        'Read the cards along the top for live numbers: how full you are, how many beds are free, and how many patients are currently staying.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens to a bed after I admit someone?', 'a' => 'The bed turns red and shows the patient name, doctor and a reference code. From then on a nightly room charge is added automatically for as long as the patient stays.'],
        ['q' => 'Why can\'t I admit or move a patient?', 'a' => 'Only doctors and admins can check in, move and discharge patients. Reception can view the board and switch free beds between cleaning, maintenance and available, but cannot manage patients.'],
        ['q' => 'A bed is taken but I need it free. What do I do?', 'a' => 'You cannot simply mark an occupied bed free. Open the patient first and either move them to another bed or discharge them. Discharging frees the bed and stops the nightly charge.'],
    ]],
],
'inpatient-admissions' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the list of patients staying in the clinic overnight (inpatients). You can see who is currently admitted, who has been sent home, and open any one to handle their stay.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the "Active", "Discharged" and "All" tabs at the top to choose which patients to see — each tab shows how many there are.',
        'Click any row in the list to open that patient\'s admission panel, where you can see their bed, doctor, and full details.',
        'Inside the panel, switch between the "Overview", "Bed History", "Charges" and "Rounds" tabs; use "Assign bed" or "Transfer" to move the patient to a bed, and "Discharge" to end the stay.',
        'To add a one-off cost, open the "Charges" tab and click "Add manual charge". To note a doctor visit, open the "Rounds" tab and click "Log round".',
        'Click "Export Excel" to download the current list, or "Bed board" to see the visual map of beds.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I discharge a patient?', 'a' => 'It ends the stay, frees up the bed, stops the nightly bed charges, and creates a final bill with the total. You pick a discharge type (Discharged, LAMA, Transferred out, or Expired) and write a short discharge summary.'],
        ['q' => 'How do the bed charges get added?', 'a' => 'The nightly bed charge is added automatically each night while the patient is admitted. You can add any extra one-off cost yourself with "Add manual charge" in the Charges tab.'],
        ['q' => 'How do I print a discharge summary?', 'a' => 'Open the patient and click "Print discharge summary" on the Overview tab. A print-ready page opens that you can print or save as a PDF.'],
    ]],
],
'inpatient-wards' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you set up the wards for patients who stay overnight — the units such as General, ICU, Pediatric, Maternity, VIP and Isolation. Each ward has a nightly price, and you set wards up here before you add beds to them.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New ward" to add one, then fill in its Name, Code, Type, Branch and Daily rate, and save.',
        'Use the search box to find a ward by name or code, or pick a branch or type from the dropdowns to narrow the list.',
        'Click any ward row to open "Edit ward" and change its details, price or whether it is active.',
        'Click the trash bin icon at the end of a row to remove a ward you no longer need.',
        'Click "Clear" to reset the search and filters and see every ward again.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I delete a ward?', 'a' => 'A ward that still has beds in it cannot be deleted. Go to the Beds page, remove its beds first, then come back and delete the ward.'],
        ['q' => 'What does the daily rate do?', 'a' => 'It is the standard nightly charge for the beds in that ward. It is added automatically for each night a patient stays. An individual bed can be given its own price that overrides this.'],
        ['q' => 'My ward has no beds on the bed board — why?', 'a' => 'The bed board only shows wards that are marked Active, and a ward stays empty there until it has beds. Add beds to it on the Beds page and they will appear under the ward.'],
    ]],
],
'inpatient-beds' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of every bed in your wards. Each bed has a code, the ward it belongs to, a status, and an optional nightly price. These are the beds patients are placed on when they stay overnight.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New bed" to add a bed. Give it a code, pick its ward, set its status, and (if you want) type a daily rate. Leave the rate empty to use the ward\'s rate.',
        'Use the search box to find a bed by its code, or use the ward and status dropdowns to narrow the list. Click "Clear" to reset.',
        'Click any bed in the list to open it and change its details, then click "Save".',
        'Click the trash icon at the end of a row to remove a bed you no longer need.',
        'Glance at the chips at the top to see Total, Available, Occupied and Out of service beds at a glance.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I delete a bed?', 'a' => 'A bed that has a patient on it cannot be deleted. Move or discharge the patient first, then remove the bed.'],
        ['q' => 'Should I set a bed to "Occupied" here?', 'a' => 'No. Occupied is set for you automatically when a patient is admitted. Use this screen mostly for setup and for housekeeping states like Maintenance or Cleaning.'],
        ['q' => 'What is the daily rate for?', 'a' => 'If you type a rate, that bed charges its own price per night. Leave it empty and the bed simply uses the ward\'s standard rate.'],
    ]],
],
'inpatient-reports' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A summary screen for the inpatient (admitted-patient) department. It shows how busy the beds are, how long patients stay, how many were admitted, and how much each ward earns.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the row of cards at the top: "ALOS (30d)" is the average stay length, "Admissions this month", "Bed revenue (month)", "Active now" (patients currently admitted), and "Readmission rate (90d)".',
        'Look at the "Summary" box for a short plain-language recap, and at the small up/down arrows on the cards to see how this month compares to last month.',
        'Use the "Bed occupancy (30 days)" chart to spot how full the wards have been day by day.',
        'Check "Discharge outcomes" and "Length-of-stay distribution" to see how patients left and how long they typically stayed.',
        'Compare "Admissions by ward" and "Revenue per ward" to see which wards are busiest and which bring in the most income, then click "Print" for a paper or PDF copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does ALOS mean?', 'a' => 'Average length of stay, in days, worked out from patients discharged in the last 30 days.'],
        ['q' => 'Why is bed revenue lower than I expect?', 'a' => 'The bed revenue and revenue-per-ward figures count only the daily bed charges for this month. One-off charges and other visit fees are not included here.'],
        ['q' => 'Do I need to refresh to see new numbers?', 'a' => 'The figures load by themselves when you open the page. Reopen or refresh the page to pull the latest numbers.'],
    ]],
],
'insurance-insurers' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The list of insurance companies your clinic works with. You keep each company\'s name, code, contact details and payment terms here so they can be used on plans, patient policies and claims.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New insurer", fill in the Name and Code (required), plus any contacts, Tax ID and Payment terms (days), then click "Save".',
        'Click any row in the table to reopen it as "Edit insurer" and update the details.',
        'Find a company fast with the search box (matches name, code or tax ID) or the "All" / "Active" / "Inactive" buttons.',
        'To stop using a company, click the archive button on its row; to bring one back, switch to "Inactive" and click the restore button.',
        'Use "Export Excel" to download the list, or the import button to add many companies from a spreadsheet at once.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the "Plans" number on each row mean?', 'a' => 'It shows how many coverage plans belong to that company. You add those on the Insurance Plans page.'],
        ['q' => 'I archived a company by mistake — is it lost?', 'a' => 'No. Switch the filter to "Inactive", find the row and click the restore button to bring it back.'],
        ['q' => 'What is the "Receivable (AR) account" field for?', 'a' => 'It tells the accounting side which account this company\'s unpaid amounts go to. Leave it on the system default unless your accountant asks you to change it.'],
    ]],
],
'insurance-plans' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of the coverage plans each insurance company offers, such as a Gold or Silver tier. These are the plans you later attach to a patient\'s insurance policy.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New plan" to add one. Pick the "Insurer", choose a "Tier" if it has one, type the plan "Name" and "Code", set "Effective from" and "Effective until" if there are dates, then click "Save".',
        'Click any row in the table to reopen that plan in the "Edit plan" window and change its details.',
        'Find a plan with the search box, the insurer dropdown ("All insurers"), or the "All" / "Active" / "Inactive" buttons; click "Clear" to reset.',
        'The "Rules" and "Policies" columns show how many coverage rules and patient policies use the plan.',
        'Click "Export Excel" to download the list you are viewing, or use the import button to add many plans at once from a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I delete a plan?', 'a' => 'If patient policies or coverage rules already use the plan, deleting is blocked and you will see a message. Open the plan and untick "Active" to retire it instead.'],
        ['q' => 'What do "Effective from" and "Effective until" do?', 'a' => 'They set the date range the plan is valid. The "Effective until" date must be the same as or later than the "Effective from" date.'],
        ['q' => 'Where does a plan get used?', 'a' => 'You choose a plan when adding a patient\'s insurance policy, and from there it flows into pre-authorizations and claims.'],
    ]],
],
'insurance-policies' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of which patients have which insurance, with the company, plan, policy number and card details. The clinic uses these so a patient\'s visit can be billed to the right insurance.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New policy". In "Search a patient…", type the patient\'s name (or policy number) and pick them from the list.',
        'Choose the "Insurer" (insurance company) and the "Plan", then fill in "Policy number", "Member ID" and "Card number".',
        'Set "Holder relationship" to say whose card it is (Self, Spouse, Child, Parent or Other), and tick "Primary policy" if this is the patient\'s main insurance.',
        'To find an existing policy, type in the search box at the top or use the status menu (All statuses, Active, Expired, Suspended, Cancelled). Click "Clear" to reset.',
        'Click any row to edit a policy, the trash icon to remove one, or "Export Excel" to download the list you are viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why does "Primary policy" matter?', 'a' => 'When a visit is finished, the system uses the patient\'s primary, active policy to start the insurance claim. If no policy is marked primary and active, no claim is started automatically.'],
        ['q' => 'I cannot delete a policy. Why?', 'a' => 'A policy that already has claims linked to it cannot be deleted. Instead, open it and change its status to Cancelled or Expired.'],
        ['q' => 'The Plan menu is empty after I pick an insurer.', 'a' => 'Only plans that belong to the insurer you picked are shown. If none appear, add the plan first on the Insurance Plans page.'],
    ]],
],
'insurance-preauth' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you ask an insurance company to approve planned treatment in advance, and where you record their answer. You list the services with their estimated cost and later note how much the insurer approved.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New request", choose the patient\'s "Policy", and if it relates to a booked visit add the "Visit #". Use "Add service" to list each service with a description and its estimated amount — the "Estimated total" adds up on its own.',
        'Use the pencil icon on a row to reopen a request in the "Edit request" form, or the trash icon to delete it.',
        'To find a request, type a reference number or patient name in the search box, or pick a status from the "All statuses" dropdown.',
        'Once the insurer replies, click the check-badge icon ("Record decision") on the row and set Approved, Partially approved or Rejected, fill in the "Approved amount" and any "Decision notes".',
        'Click "Export Excel" to download the list of requests you are currently viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I record a decision on some requests?', 'a' => 'The "Record decision" button only appears once a request is Submitted or Under review. A request still in Draft must be moved forward first.'],
        ['q' => 'Do I type the estimated total myself?', 'a' => 'No. It adds up automatically from the estimated amount you enter on each service line. Just adjust the lines and the total updates.'],
        ['q' => 'Does approving a request here create an insurance claim?', 'a' => 'No. This page only records the insurer\'s advance approval. The actual claim is created separately from the completed visit on the Claims page.'],
    ]],
],
'insurance-claims' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you track money the clinic is owed by insurance companies. Most claims appear here on their own after a visit is finished. The page shows what was billed, what the insurer agreed to pay, what has been paid, and what is still outstanding.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Start on the "Needs action" tab — these are the claims that need you to do something. The other tabs are "Waiting on insurer", "Paid", "Rejected", and "All".',
        'Look at the "Next step" column on the right. It tells you what to do next — for example "Send to insurer" or "Record payment". Click that button to act on the claim straight away.',
        'Click any claim row to open its details. A coloured "Next step" box at the top tells you what is needed, with a button to do it (such as Approve, Reject, or Record payment).',
        'When the insurer pays, open the claim and click "Record payment". Type the "Amount", choose the "Method" (Bank transfer, Cheque, or Cash), and pick the "Deposited to" account so it lands in the right place.',
        'If a visit needs a claim that didn\'t appear by itself, click "New claim from a visit" at the top right, search for the patient or booking code, review the coverage, then click "Create claim".',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Where do the claims come from?', 'a' => 'A claim is created automatically when a visit is completed for a patient who has active insurance. If the patient has no insurance on file, no claim is made and you can add one yourself with "New claim from a visit".'],
        ['q' => 'Why can\'t I see a button I expected?', 'a' => 'You only see the buttons that make sense for where the claim is right now. For example, "Record payment" only appears once the insurer has approved the claim, and some actions like "Void" are limited to managers.'],
        ['q' => 'What does the small number of days next to a claim mean?', 'a' => 'It shows how long the claim has been waiting. It turns amber after about two weeks and red after about a month, so you can chase the insurer on the oldest ones first.'],
    ]],
],
'insurance-followup' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the chasing page for money the insurance companies still owe you. The top half shows each insurer, how much they owe, and how old that money is. The bottom half is your worklist — the individual claims behind those numbers, with a place to record every phone call and promise.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Start on the "Chase now" tab. Those claims are either past the insurer\'s own agreed payment terms, or you asked to be reminded about them today.',
        'Look at the insurer table first. The "0–30 / 31–60 / 61–90 / 90+" columns show how old the money is — anything in the last two columns has been waiting far too long. Click an insurer to see only their claims in the worklist below.',
        'Use the small phone, WhatsApp and email buttons on an insurer row to contact them. The message is written for you with the amount and number of claims already filled in.',
        'After you speak to them, click "Log chase" on the claim. Choose how you contacted them, type what they said, and set "Chase again on" — the claim then moves to the "Scheduled" tab until that date arrives.',
        'In a hurry? The little alarm-clock button pushes a claim one week forward without opening anything.',
        'Click any claim row to see its full follow-up history and any payments already received. Click "Export Excel" to take the chase list with you.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How is "Past agreed terms" worked out?', 'a' => 'It compares how long the claim has been waiting against the "Payment terms (days)" set on that insurer under Insurance → Insurers. If an insurer has no terms recorded, 30 days is assumed.'],
        ['q' => 'Does logging a chase change the claim?', 'a' => 'No. It only records that you followed up and when to follow up again. The claim\'s status, amounts and payments are only ever changed from the Claims page.'],
        ['q' => 'Why is a claim here that was never sent?', 'a' => 'Drafts are money sitting with you, not with the insurer — they show under "Not sent". Send them from the Claims page and they will move to "With insurer".'],
        ['q' => 'A claim disappeared from this page — where did it go?', 'a' => 'This page only lists open claims that still have a balance. Once the insurer pays in full (or the balance is written off), the claim drops off and the payment shows in "Collected (30 days)".'],
    ]],
],
'lab-orders' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The laboratory\'s work list. Every time a doctor orders tests on a visit, the order lands here. You work down the list: take the sample, start the analysis, type the results in, then release the report — which sends it back to the doctor automatically.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'The tabs across the top split the work: "Open" is everything still to do, "New" is waiting for a sample, "Analysing" is on the bench, and "Released" is finished. The number next to each tab is how many are in it.',
        'Urgent orders sit at the top of the list with a red "Urgent" tag. The "Waiting" column turns amber after an hour and red after two, so nothing gets forgotten.',
        'On a new order, click "Collect sample". Once the sample is in, click "Start analysis" — or just click "Enter results" and start typing; the order moves itself along.',
        'On the order page, type each value into the "Result" box. The "Flag" column fills in by itself (Normal / Low / High) from the reference range — change it by hand if you need to, for example when the range is age or sex specific.',
        'Use "Attach file" on the right to add the analyser printout or a photo of the report (PDF, JPG or PNG).',
        'When every test has a value, click "Release report". The doctor gets a notification straight away, and a critical result is flagged red for them.',
        'After releasing, use "Print report" for a paper copy, "Download PDF" / "Download image" to save it, or "Send as PDF" / "Send as image" to WhatsApp it to the patient.',
        'Click "Print requisition" to print the slip you keep with the specimen — it has the order number, the patient and tick boxes for each test.',
        'Use the search box (patient, order number or test), the doctor dropdown, the date boxes and "Urgent only" to narrow the list. "Export" downloads what you are looking at as Excel.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I change a result after releasing the report?', 'a' => 'No. Releasing is the point where the result becomes the patient\'s record and the doctor is told about it. If something was wrong, cancel is not available either — ask an admin, and have the doctor order a repeat test so the correction is traceable.'],
        ['q' => 'Why can\'t I click "Release report"?', 'a' => 'At least one test still has an empty "Result" box. Fill every row (or remove a test that is not being run) and the button becomes available.'],
        ['q' => 'Where do the prices come from, and who gets charged?', 'a' => 'Each test carries its price from the Lab Tests catalogue, and it is added to the patient\'s visit bill the moment the doctor orders it. Cancelling an order takes those charges back off the bill.'],
        ['q' => 'The "Send as PDF" buttons are missing.', 'a' => 'Sending reports over WhatsApp is switched off by default, because every message is billable and a result is private medical information. Ask an admin to turn it on for the clinic.'],
        ['q' => 'Can I add a test the doctor forgot?', 'a' => 'Yes, while the order is still open — use the picker at the bottom of the results table. It is billed like any other ordered test.'],
        ['q' => 'A doctor says they cannot see the result.', 'a' => 'Check the order shows "Released". Until then the doctor sees it as still with the lab. Doctors only see their own patients\' orders.'],
    ]],
],
'lab-tests' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the list of lab tests your clinic offers. Each test has a short code, a name, the kind of sample it needs, its unit, the normal range, and a price. Doctors pick tests from this list when they order labs on a visit.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New test" to add one. Fill in the "Code" (a short code like CBC or GLU), the "Name", and the "Default price (KWD)". You can also add the "Specimen type", "Unit" and "Reference range". Then click "Save".',
        'Click any row in the table to open that test and change its details.',
        'To find a test, type in the search box (by code, name or sample type), choose a branch, or use the "All" / "Active" / "Archived" buttons. Click "Clear" to reset.',
        'To stop offering a test, click the archive icon on its row. To bring it back, switch to "Archived" and click the restore icon.',
        'Click "Export Excel" to download the list, or use the import button to add many tests at once.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'If I archive a test, do I lose old results?', 'a' => 'No. Archiving only hides the test from new orders. Results recorded in the past still show it.'],
        ['q' => 'Why does it say the code is already used?', 'a' => 'Each code must be unique within a branch. The same code can exist in different branches, but not twice in the same one.'],
        ['q' => 'Is this where I order a test for a patient?', 'a' => 'No. This page only manages the list of tests. Doctors order tests from the lab section on the patient\'s visit screen.'],
    ]],
],
'clinic-items' => [
    'what' => ['heading' => 'What is this?', 'body' => 'The catalogue of every item, product and service your clinic uses or charges for. Consumables and products can be made stockable so their quantity is tracked, while services never hold stock.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New item" to add one; pick a "Type" (Consumable, Service or Product), enter the English and Arabic names, and set the "Default cost" and "Default price" in KWD.',
        'Use the search box, the type buttons ("All types" / "Consumable" / "Service" / "Product") and the status buttons ("All" / "Active" / "Inactive") to narrow the list; "Clear" resets every filter.',
        'For a consumable or product, tick "Stockable" to reveal "Inventory settings" (Stock unit, Usage unit, Conversion factor, Consume step) — fill these in so the item can hold stock.',
        'Click any row to edit it, or use the trash icon to delete; when an item already has history, mark it "Inactive" instead of deleting.',
        'Use "Export Excel" to download the filtered list, or the import button to add many items at once.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I make a service use up its own consumables?', 'a' => 'Edit the service and fill in "Consumables used per service" — for example a Botox service uses 2 vials and 3 syringes. When that service is added to a visit, those items are taken out of stock automatically, so you do not have to list them every time.'],
        ['q' => 'Why can\'t I delete an item?', 'a' => 'If the item already has stock or usage history, deleting is blocked to protect those records. Mark it "Inactive" instead so it stays for history but no longer appears for new use.'],
        ['q' => 'What does "Stockable" do?', 'a' => 'Only a stockable item shows up on the Clinic Stock page and has its quantity tracked. Services and non-stockable items can still be charged, but their quantity is never counted.'],
    ]],
],
'clinic-stock' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page shows how much of each supply you have in stock at each branch. Use it to check current amounts, set a low-stock alert level, and record new deliveries.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'To add a delivery, click "Receive stock", pick the branch and item, then type how much arrived in "Qty (stock units)" (for example boxes) or "Qty (base units)". Receiving is the only way the on-hand amount goes up.',
        'To start tracking a new item at a branch, click "New record". It starts at zero until you receive some.',
        'To change an item, click the pencil icon. You can only set the "Alert threshold" (when it should warn you it is running low) and the "Bin location" (where it is kept on the shelf).',
        'To find an item, type its name in the search box, or tick "Low only" to see just the items that are running low. Low items show a "Low" badge.',
        'Use "Export Excel" to download the list, or the import button to upload many stock records at once.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I just type the amount I have?', 'a' => 'The on-hand amount only changes when you receive a delivery or when an item is used during a visit. This keeps the count honest, so it cannot be typed in by hand.'],
        ['q' => 'What is the difference between stock units and base units?', 'a' => 'Stock units are how you buy it (like a box), and base units are the smaller pieces inside (like single doses). Enter just one of the two and the other fills in for you.'],
        ['q' => 'When does an item show as "Low"?', 'a' => 'When the amount you have drops to or below the "Alert threshold" you set for it. If you leave the threshold blank, it will never be flagged as low.'],
    ]],
],
'stock-movements' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A history list of every change to your stock — every item received, used up, or adjusted. It fills in by itself, so you only look here; you cannot add or change rows.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type an item name in the search box, or pick one from the "All items" dropdown, to see that item\'s full history.',
        'Use the type buttons ("All types", "Restock", "Consume", "Adjustment") to show only one kind of change.',
        'Set the "From" and "To" dates (and pick a person in "All users") to narrow the list to a time range or a staff member.',
        'Read the "Change" column: green numbers are stock coming in, red numbers are stock going out. "After" shows the balance left over.',
        'Click "Clear" to reset all filters, or "Export Excel" to download the list you are viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I add, edit, or delete a row here?', 'a' => 'No. This page is only a record. Rows appear on their own when you receive stock, or when items get used during a visit.'],
        ['q' => 'Where do the "Consume" rows come from?', 'a' => 'They are added automatically when a visit uses up a stock item, which lowers the amount on hand.'],
        ['q' => 'Why does a row show a branch?', 'a' => 'Stock is counted separately for each branch, so every change notes which branch it affected. You only see changes for branches you can access.'],
    ]],
],
'stock-requests' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A worklist of the items a doctor or nurse asked for during a visit. For each request you can see how much is in stock right now, hand the items over, and confirm what the patient actually received.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the tabs at the top to find work: "Pending" (waiting to be handed out), "Awaiting receipt" (handed out, waiting for the doctor to confirm), "Received" (done), and "Cancelled". Each tab shows how many requests are in it.',
        'On a "Pending" request, check each item line — it shows the amount needed next to what is available, with a red "short!" tag if there is not enough in stock.',
        'Click "Fulfil" to hand the items out, pick whether the visit goes back to "Awaiting doctor" or "In progress", add any notes, then "Confirm issue".',
        'Once the doctor has the items, the request moves to "Awaiting receipt". Click "Receive" on it, confirm the amounts actually used — lower a line if some came back — then "Confirm receipt". You can also use "Confirm all as issued" if everything was used.',
        'To turn down a request, click "Cancel" and type a reason. Click the visit code to open the visit, or "Export Excel" to download the list you are viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'When does the patient get charged for these items?', 'a' => 'Not when you hand them out. The patient is only billed after you click "Receive" and confirm the amounts the doctor actually used. Anything you reduce on that screen goes back into stock and is not charged.'],
        ['q' => 'Can I still hand out items when it says "short!"?', 'a' => 'Yes. The red "short!" tag is only a warning that stock may not cover the request; the choice to hand them out is yours. The "Fulfil" button only appears on requests in the "Pending" tab.'],
        ['q' => 'Where do these requests come from?', 'a' => 'They are created from the visit screen when a doctor or nurse needs items for a patient. This page is where the pharmacy or store hands them out or cancels them.'],
    ]],
],
'purchase-orders' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Where you order supplies from a vendor, receive them into a branch\'s stock, and record what you pay. Stock levels and what you owe the vendor update on their own.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New PO", choose the vendor and the branch that will receive the goods, then add each item with its quantity and unit cost. The total adds up as you go and the order is saved as a Draft.',
        'Open the order and click "Submit for approval" to send it on; a manager then clicks "Approve" to confirm it. You can edit an order while it is a draft, and cancel it any time before the goods have been fully received.',
        'When the goods arrive, open the order and click "Receive", then type how much actually came in for each line. The items are added to stock right away, and you can receive in more than one delivery.',
        'Click "Pay" to record money paid to the vendor: it suggests the amount still owed, and you choose the payment method and add a reference if you have one.',
        'Use the status tabs along the top (Draft, Approved, Received, etc.) and the search box to find an order, then click any row to open its full details.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'When does my stock go up?', 'a' => 'Only when you click "Receive" and enter what arrived. Creating or approving the order does not change stock — that happens at the receiving step.'],
        ['q' => 'Can I receive only part of an order?', 'a' => 'Yes. Enter just what came in for each line. The order shows as "Partially received" and remembers the rest so you can receive it when it arrives.'],
        ['q' => 'What does the "Outstanding" amount mean?', 'a' => 'It is what you still owe the vendor for goods you have received but not yet fully paid for. It drops to zero once the order is paid in full.'],
    ]],
],
'clinic-packages' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A package is a ready-made bundle of items sold for one price that a doctor can add to a visit in a single tap. Use it for common combinations, like a consultation plus its supplies. A package can also be put on offer at a lower price and published on your public website, where patients see what they save.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New package", type the name in English and Arabic, and set the "Main price" — what the bundle normally costs.',
        'To run an offer, fill in "Discount price" (it must be lower than the main price). A green line shows exactly what the patient saves, in money and as a percentage, before you save.',
        'Optionally set an offer period with "Starts" and "Ends" — outside those dates the package quietly goes back to its main price. Leave both empty to run the offer indefinitely.',
        'Click "Add item" for each thing the package includes, pick it from the list, and set its "Qty (base)" (how many).',
        'On any item line, tick the box if it should be taken out of stock when the package is used; leave it off for items that should not affect stock.',
        'Leave "Branch" empty to offer the package at every branch, or pick one branch to make it available there only. Keep "Active" ticked so staff can use it.',
        'To advertise it, tick "Show this package as an offer on the website", then add a short description in both languages and an image link. It appears on your website\'s Offers page with the before and after price.',
        'Use the search box, branch picker and the All / Active / Inactive buttons to find a package; click a row to edit it, or use the trash icon to delete it. "Export Excel" downloads the list and the import button bulk-loads packages.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can a package include a service, not just stock items?', 'a' => 'Yes. The item list shows services, products and supplies together, with services listed first. Add the service as a line; the supplies that service normally uses come from the service itself and are taken out of stock automatically.'],
        ['q' => 'If I edit a package, what happens to its items?', 'a' => 'When you save, the package matches exactly the items shown in the editor at that moment, so add or remove lines before saving to get the bundle you want.'],
        ['q' => 'How is the price related to the items inside?', 'a' => 'The "Main price" is the single price charged for the whole bundle. It is set on its own and does not have to match the individual items\' own prices.'],
        ['q' => 'What does the patient actually pay when a package is on offer?', 'a' => 'The discount price. When the package is added to a visit, the bill shows the main price with the saving taken off as a discount line, so the patient can see both what it normally costs and what they saved.'],
        ['q' => 'Does the offer show on the website automatically?', 'a' => 'Only if you tick "Show this package as an offer on the website". Without that tick the discount still applies to visits, but the package stays private and is not advertised.'],
        ['q' => 'What if a package is also covered by a promotion?', 'a' => 'The offer price is applied first, then the promotion is worked out on the already-reduced price — so a deal is never counted twice.'],
    ]],
],
'leaves' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page is where staff ask for time off and see whether it was approved. If you are an HR manager you see everyone\'s requests; otherwise you see only your own.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "Request leave", choose the type (annual, sick, maternity, unpaid, emergency or other), pick the From and To dates, add a reason if you like, then click "Submit".',
        'Watch the status: "Pending" means it is waiting for a decision, "Approved" means it was granted, and "Rejected" means it was turned down.',
        'HR managers approve a pending request with the green tick or turn it down with the red cross, and may add a note explaining the decision.',
        'Narrow the list with the status and type dropdowns; HR managers also get a search box and can pick one person from the "All staff" list.',
        'Click "Export Excel" to download the list you are looking at, and use the edit or delete icons on a request to change or remove it.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can I only see my own requests?', 'a' => 'Regular staff see only their own leave and can request time off only for themselves. The search box and "All staff" picker show up only for HR managers.'],
        ['q' => 'Can I change a request after sending it?', 'a' => 'Yes, while it is still "Pending" you can edit or delete your own request. Once it has been approved or rejected, ask an HR manager to make any changes.'],
        ['q' => 'What do the numbers at the top mean?', 'a' => 'The "Total", "Pending" and "Approved" chips are live counts of your leave (or all staff leave for HR managers) so you can see what is still waiting at a glance.'],
    ]],
],
'attendance' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page keeps track of when staff start and finish their shifts each day, and adds up the hours worked. You log your own shifts here; HR managers can also see and fix everyone\'s records.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'At the start of your shift, click "Clock me in". The card then shows "Clock me out" so you can close the shift and add up your hours when you leave.',
        'The two tiles show your total hours for this week and this month.',
        'Narrow the list with the "From" and "To" date pickers, then click "Clear" to reset. HR managers also get a staff name search box and an "All staff" picker to look up other people.',
        'For a shift that is open but not closed, click "Clock me out" on that row; an HR manager can use the edit pencil to correct a wrong date or time.',
        'Click "Export Excel" to download the list you are currently viewing.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'It says "You haven\'t clocked in today." What do I do?', 'a' => 'It just means you have not started a shift yet today. Click "Clock me in" to begin. The card then changes to "Clock me out" for when you finish.'],
        ['q' => 'Why can\'t I see other people\'s attendance?', 'a' => 'Regular staff only see their own records. The staff search, the "All staff" box and the delete action are only available to HR managers.'],
        ['q' => 'Can a wrong time be fixed?', 'a' => 'Yes. An HR manager can use the edit pencil to change the date and the clock-in or clock-out time, and the hours worked are worked out again automatically.'],
    ]],
],
'doctors' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is your list of doctors. Each doctor has a consultation fee, license number, clinic, branch and an active or archived status. The list is used when picking a doctor for visits, bookings and earnings.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New doctor" and fill in the name, specialty, phone, "Email", "License #", "Consultation fee (KWD)", then pick the "Clinic", "Branch" and (optionally) a "Room".',
        'Click any row in the list to open "Edit doctor" and change the details, then click "Save".',
        'Find a doctor using the search box (name, phone, email, license or specialty), the branch picker, or the "All", "Active" and "Archived" buttons; click "Clear" to reset.',
        'To hide a doctor you no longer use, click the archive button on their row; click the restore button to bring an archived doctor back. Tick several rows to archive them all at once.',
        'Click "Export Excel" to download the current list as a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Does the doctor get a login?', 'a' => 'Yes. When you add a doctor, the "Email" you enter becomes their login account and a temporary password is shown once on screen, so write it down or share it with them right away. The email cannot be changed after the doctor is created.'],
        ['q' => 'Does archiving delete the doctor?', 'a' => 'No. Archiving only hides the doctor from active lists. The record and its history stay and you can bring it back any time with the restore button.'],
        ['q' => 'Why can I only pick certain branches or rooms?', 'a' => 'The branch list only shows branches that belong to the clinic you chose, and the room list only shows free rooms in that branch. Pick the clinic first, then the branch, then the room.'],
    ]],
],
'users' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you create and manage logins for your staff. Each person gets their own account, and the roles you give them decide what parts of the system they can see and use. Only managers can open this page.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New user" to add a staff login. Enter their Name, Email, Phone and a Password, then tick one or more Roles to set what they can do.',
        'Click any name in the list to open it, change their details, and Save. When editing, leave the "New password (leave empty to keep current)" box blank unless you want to change their password.',
        'Use the search box (name, email, or phone), the role selector, and the All / Active / Inactive buttons to find a person quickly.',
        'To stop someone from logging in (for example, when they leave), open their row and set the Status to Inactive, or use the small button at the end of their row to deactivate them. This keeps their past records.',
        'Click "Export Excel" to download the full staff list as a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What do roles do?', 'a' => 'A role decides which screens and actions a person can use, like reception, nurse, or accountant. You can give one person more than one role, and you can change their roles any time.'],
        ['q' => 'How do I give a doctor a login?', 'a' => 'You don\'t create it here. On the Doctors page, add the doctor with their email and a login account is created for them automatically; a temporary password is shown once.'],
        ['q' => 'Should I delete a user who left?', 'a' => 'No. Set them to Inactive instead. This blocks their login but keeps everything they did in the system, which is safer than deleting the account.'],
    ]],
],
'doctor-comp' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page sets how each doctor gets paid — a fixed salary, or a share (percentage) of fees or of net profit. The clinic uses these settings to work out what each doctor earns on every finished visit.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New profile", pick the "Doctor", then choose the "Type": "Salary" for a fixed pay, or "Percentage" for a share.',
        'Choose the "Basis" — "Fees only" (a share of the service fees) or "Net profit" (a share of what is left after costs).',
        'If you picked "Percentage", type the "Percentage rate (%)" the doctor gets, then click "Save".',
        'Keep "Active" ticked so the setting counts; untick it to pause it. Click any row in the list to change it later.',
        'Use the search box or the "All types" / "Salary" / "Percentage" tabs to find a doctor, "Export Excel" to download the list, or the trash icon to remove a setting.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the difference between "Fees only" and "Net profit"?', 'a' => '"Fees only" gives the doctor a share of the service or consultation fee. "Net profit" gives a share of what is left after the clinic\'s costs are taken out, so it is usually a smaller amount.'],
        ['q' => 'Why can\'t I change the doctor when editing?', 'a' => 'Each setting belongs to one doctor, so the "Doctor" field is locked once it is saved. To set up a different doctor, click "New profile" and create a fresh one.'],
        ['q' => 'When does this start affecting a doctor\'s pay?', 'a' => 'Each time a visit is completed, the doctor\'s active setting here is used to record their earnings for that visit. You can see the results on the Doctor Earnings page.'],
    ]],
],
'doctor-earnings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of how much each doctor earned, one line per completed visit. Each line shows the visit fees, the profit, and the doctor\'s share for that visit.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick a name in the "All doctors" box to see just one doctor\'s earnings.',
        'Set the "From" and "Until" dates to see a specific time range.',
        'Type in the search box to find a doctor, visit, or type quickly.',
        'Look at the chips up top — "Total doctor cut", "Total fees" and "Total profit" — for the totals of whatever you have filtered.',
        'Click "Export Excel" to download the filtered list as a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I change a number on this page?', 'a' => 'No. This page only shows earnings; you cannot edit them here. Each line is locked in when the visit is completed. To change how a doctor is paid going forward, update their compensation profile.'],
        ['q' => 'How is the "Doctor cut" worked out?', 'a' => 'It is based on the doctor\'s pay setup — the rate and whether they earn on fees or on net profit. That setup is applied to each visit automatically when it completes.'],
        ['q' => 'Why is a recent visit not showing?', 'a' => 'A line only appears once the visit is fully completed. Visits that are still open or in progress will show up here after they finish.'],
    ]],
],
'accounts' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the Chart of Accounts: the full list of money "buckets" the clinic uses to track everything it owns, owes, earns and spends. Each row shows the account\'s code, name, type and current balance.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'To add an account, click "New account", then fill in the "Code", "Name" and "Type". You can also pick a "Parent account" to nest it under a bigger group.',
        'To find an account, type in the search box ("Search by code or name…"), pick a type from the "All types" dropdown, or use the "All" / "Active" / "Inactive" buttons. Click "Clear" to reset.',
        'On any row, use the pencil icon to edit the account, or the document icon to open its "Account statement" and see every entry behind the balance.',
        'To stop using an account, edit it and turn off "Active" — it stays in your records but is hidden from new entries. Use the trash icon only if the account was never used.',
        'A small lock icon means it is a "System account". Its code, name and type are fixed, but you can still update its description and turn it on or off.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why is a new account showing a balance of zero?', 'a' => 'The "Balance" only counts entries that have actually been posted. A brand-new account stays at zero until money is recorded against it.'],
        ['q' => 'Why can\'t I delete an account?', 'a' => 'An account can\'t be deleted once it has any entries or accounts nested under it, and locked "System account" rows can never be deleted. Turn off "Active" to retire it instead.'],
        ['q' => 'Why are the details greyed out on some accounts?', 'a' => 'Those are system accounts that the clinic\'s automatic bookkeeping relies on, so their code, name and type stay locked. You can still change the description and turn them on or off.'],
    ]],
],
'journal-entries' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page is the accounting record book. Each entry moves money between accounts and lists what was added and what was taken out, so the totals on both sides always match.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'To add a record, click "New entry", pick the "Date", write a short "Narration" explaining it, then add the account lines with the amounts.',
        'Make sure each entry shows the green "Balanced" tag before you finish — the two money columns (Debit and Credit) must add up to the same total.',
        'A new record is saved as a "Draft" first. While it is a draft you can click "Edit" to change it or the trash icon to "Delete" it.',
        'When the draft is correct, click "Post" to lock it in. Use the search box or the status filter at the top to find any entry later.',
        'A posted entry cannot be changed. If you need to fix one, click "Reverse" and type the reason — this creates an opposite entry that cancels it out.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I edit or delete an entry?', 'a' => 'Only drafts can be edited or deleted. Once you click "Post" the entry is locked. To undo a posted entry, use "Reverse" instead.'],
        ['q' => 'It won\'t let me post — what\'s wrong?', 'a' => 'The entry must show the green "Balanced" tag, and its date must fall in a period that is still open. Fix the amounts so both columns match, or ask an admin about the date.'],
        ['q' => 'What is the difference between a Draft and a Posted entry?', 'a' => 'A draft is a work in progress you can still change. A posted entry is final and counts in the clinic\'s books and reports.'],
    ]],
],
'expenses' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page is where you record money the clinic spends, like rent, supplies or bills. Each expense starts as a draft, then you post it so it counts in the books.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New expense", then fill in the date, the amount, and which expense account it was for. You can also choose the vendor (the person or shop you paid).',
        'Say how it was paid: pick a cash or bank account, or leave it blank to record it as money still owed.',
        'Save it. While a row still shows the "Draft" badge you can reopen it with the pencil button to fix anything.',
        'When the details are right, click "Post" on the row to lock it into the books.',
        'Use the search box and the "All statuses" dropdown (Draft / Posted / Void) to find a past expense. You can also "Export Excel" or import a batch with the import button.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'I posted an expense by mistake. What do I do?', 'a' => 'Find it in the list and click "Void" on its row. This cancels its effect on the books but keeps a record. If needed, create a fresh expense with the correct details.'],
        ['q' => 'Why can\'t I change a posted expense?', 'a' => 'Once an expense is posted it is locked so the books stay accurate. Only drafts can be edited. To correct a posted one, void it and add a new one.'],
        ['q' => 'My expense would not post. Why?', 'a' => 'This usually means an account it needs is missing or switched off. Check that the expense account and the cash, bank or owed account are set up and active, then try again.'],
    ]],
],
'vendors' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of the suppliers and companies the clinic pays money to, with their contact details. Set a default account on a vendor so that logging an expense for them is quick.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New vendor", type at least a "Name", then add a "Phone", "Email", "Contact name" or "Tax / Commercial Reg. No." if you have them, and click "Save".',
        'On the vendor form, pick a "Default expense account" (and "Default payable account" if needed) so those accounts fill in automatically next time you record an expense for this vendor.',
        'Use the search box to find a vendor by name, code or phone, and the "All" / "Active" / "Inactive" buttons to narrow the list.',
        'Click any row, or the pencil icon, to open and edit a vendor. Use the archive icon to hide a vendor you no longer use.',
        'Use "Export Excel" to download the list, or "Import" to add many vendors at once from a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the default account do?', 'a' => 'When you record an expense and choose this vendor, the account you set here is filled in for you on the Expenses page, so you do not have to pick it every time.'],
        ['q' => 'What happens when I archive a vendor?', 'a' => 'The vendor is hidden from the list and from new expense forms, but past expenses linked to them stay exactly as they were. Nothing is deleted.'],
        ['q' => 'Why can\'t I find a vendor when adding an expense?', 'a' => 'Only active vendors show up there. Open the vendor, tick the "Active" box back on, and save.'],
    ]],
],
'reconciliation' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page checks that your own records for a bank account match the statement the bank gives you. You tick off each line that appears in both, so any missing or extra amounts show up.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New reconciliation", choose the bank "Account", set the "Period start" and "Period end" dates, and type the "Opening balance" and "Closing balance" from your bank statement.',
        'Open it from the list, then click "Import statement" to upload the lines from your bank (a CSV or Excel file).',
        'Click "Auto-match" to let the system pair up matching lines for you, then use "Match" or "Unmatch" to fix any that are wrong.',
        'Watch the "Difference" figure — keep matching until it reaches zero, which means your records and the bank agree.',
        'When the difference is zero, click "Complete" to lock it. Use the status filter at the top to find ones still "In progress".',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What do the "Diff" and "Matched" columns mean?', 'a' => '"Diff" is how far your records are from the bank balance (zero means they agree). "Matched" shows how many statement lines you have ticked off out of the total, like 8/10.'],
        ['q' => 'I finished a reconciliation by mistake. Can I change it?', 'a' => 'A completed reconciliation is locked. Open it and click "Reopen" to unlock it, make your changes, then "Complete" it again.'],
        ['q' => 'The difference will not go to zero. What now?', 'a' => 'Check for a bank line with no match (a fee or interest the books are missing) or a record the bank does not show. Recheck the opening and closing balances you typed against the statement.'],
    ]],
],
'periods' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page lists each month of accounting (one per month). When a month is finished, you close it here so its numbers are locked and can no longer be changed.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the "All" / "Open" / "Closed" buttons and the year picker to find the month you want.',
        'Each row shows the month\'s code, its date range, and whether it is "Open" or "Closed".',
        'When a month is fully done, click "Close" on that row to lock it so no more entries can be dated in that month.',
        'If you closed a month by mistake or need to add something, click "Reopen" to unlock it again.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I close a month?', 'a' => 'The month is locked. No new entries or edits can be dated inside it, so anything you try to record on a closed date will be refused until you reopen the month.'],
        ['q' => 'Can I undo a close?', 'a' => 'Yes. Click "Reopen" on the closed month and it will accept entries again.'],
        ['q' => 'How do I add a new month? I don\'t see a button.', 'a' => 'You don\'t need to. Each month is created for you automatically, so this page is only for opening and closing months.'],
    ]],
],
'trial-balance' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A summary that lists every account and shows what was paid into it (credit) and out of it (debit) for the dates you choose. It is a quick health check: when the two totals match, your books are in balance.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the dates you want to look at using the "From" and "To" boxes. It opens on this month so far.',
        'If you run more than one branch, choose one in the "Branch" box, or leave it on "All branches".',
        'Read down the list: each account shows its "Code", name, and amounts under "Debit" and "Credit" (a dash means nothing there).',
        'Check the "Total" row at the bottom; the Debit and Credit totals should be the same.',
        'Look at the badge at the bottom: green "Balanced" means all is well, red "Out of balance" means something needs checking. Use "Print" to print or save a copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why does it say "Out of balance"?', 'a' => 'The Debit and Credit totals do not match for the dates you picked. This is unusual and means an entry somewhere needs to be reviewed. Tell your accountant so they can find and fix it.'],
        ['q' => 'Can I change the numbers on this page?', 'a' => 'No, this page only shows figures, you cannot edit them here. Any corrections are made elsewhere in the accounting records and will then appear here automatically.'],
        ['q' => 'Why is an account I expected not showing?', 'a' => 'Only accounts that had activity during your chosen dates appear. If an account had no movement in that period, it is left out, and you will see "No activity in this period" when nothing matches.'],
    ]],
],
'general-ledger' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A view-only history of one account. It lists every entry that touched the account during the dates you choose and shows a running balance, so you can see exactly what moved the money.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the account from the "Account" box (type to search by code or name). Nothing shows until you pick one.',
        'Set the dates with "From" and "To". It starts on this month so far.',
        'Optional: choose one location in the "Branch" box, or leave it on "All branches".',
        'Look at the summary cards on top: "Opening balance", "Total debits", "Total credits", "Period activity" and "Closing balance".',
        'Read the table for each line by "Date", "Entry", "Description", "Debit", "Credit" and "Balance". Use the search box to find a line, or "Print" for a paper copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What are the small grey words under a description?', 'a' => 'They show where the line came from, plus the branch, doctor or patient when there is one, so you can trace it back to the visit or document that created it.'],
        ['q' => 'How is the "Balance" column worked out?', 'a' => 'It begins at the "Opening balance" before your start date, then adds each line up in date order until it reaches the "Closing balance" at the bottom.'],
        ['q' => 'Why do I see nothing?', 'a' => 'Either no account is chosen, or that account had no activity in the dates you picked. Choose an account and widen the dates.'],
    ]],
],
'profit-loss' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A simple report that shows whether the clinic made or lost money over a chosen period. It lists what came in (revenue), what it cost, and the profit left over at the bottom.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the period using the "From" and "To" date boxes at the top. It opens showing this month so far.',
        'If you have more than one branch, choose one in the "Branch" box, or leave it on "All branches" to see everything together.',
        'Read down the page: "Revenue" at the top leads to "Net revenue", then costs are taken off to give "Gross profit" and "Net profit" at the bottom.',
        'The bold "Net profit" line is the bottom line: green means a profit, red means a loss. Amounts taken away show in red with a minus sign.',
        'Click "Print" if you need a paper or PDF copy. Nothing on this page can be changed here.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Where do these numbers come from?', 'a' => 'They are added up automatically from the clinic\'s recorded income and spending for the dates you pick. You do not enter anything here.'],
        ['q' => 'What does "Revenue contra" mean?', 'a' => 'It is money taken back off revenue, such as discounts or refunds. It only appears when there is some, and it lowers the "Net revenue" figure.'],
        ['q' => 'Why is the net profit red?', 'a' => 'A red net profit means costs and expenses were more than the income for that period, so the clinic made a loss. Try a different date range to compare.'],
    ]],
],
'balance-sheet' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A snapshot of what the clinic owns (assets), what it owes (liabilities) and what is left over (equity) on one chosen day. It shows the clinic\'s overall financial position. You can only view and print it; nothing is entered here.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Choose the day you want to look at using the "As of" date box (it starts on today). This is a single-day picture, not a range.',
        'If you have more than one branch, use the "Branch" box to look at one branch or pick "All branches (group)" for the whole clinic.',
        'The left card shows "Assets". The right card shows "Liabilities" and then "Equity", ending in "Total liabilities & equity".',
        'Look at the badge in the bottom-right: "Balanced" means everything adds up; "Out of balance" shows the gap as a Δ amount.',
        'Click "Print" if you need a paper or PDF copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does "Retained earnings (period)" mean?', 'a' => 'It is the profit the clinic has built up by the chosen date, added into equity automatically. You do not type it in.'],
        ['q' => 'Why does it say "Out of balance"?', 'a' => 'On that day the total assets did not match liabilities plus equity, and the Δ shows the gap. Ask your accountant to review the day\'s entries.'],
        ['q' => 'Why might a single branch not balance?', 'a' => 'Equity and capital are kept at the whole-clinic level, so one branch on its own may not add up. Pick "All branches (group)" for the full balanced view.'],
    ]],
],
'cash-flow' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A simple summary of how cash came in and went out of the clinic over a chosen period. It shows where money moved (day-to-day running, buying equipment, owner funds) and how your starting cash became your ending cash.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the period using the "From" and "To" date boxes at the top; the report updates by itself.',
        'Read the "Operating activities" section first. It starts at "Net income" and adjusts for things like receivables, inventory and amounts you owe, ending in "Net cash from operations".',
        'Below that, "Investing activities" shows money spent on equipment and "Financing activities" shows owner money added or taken out.',
        'At the bottom, "Net change in cash" added to "Cash, beginning" gives "Cash, ending". Green numbers brought cash in; red numbers (with a minus sign) used cash up.',
        'Check the small badge near the bottom: "Reconciles" means the numbers tie out. Use the "Print" button to print or save a copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why is a number sometimes shown in red?', 'a' => 'Red means that line used up cash. For example, buying more inventory or waiting on unpaid bills ties up money, so it shows as a negative.'],
        ['q' => 'What does "Doesn\'t reconcile" mean?', 'a' => 'It means the report\'s ending cash did not perfectly match the actual cash movement, and it shows the gap as a small "Δ" amount. This usually points to a missing or unusual entry that the accountant should review.'],
        ['q' => 'Can I change any of these figures?', 'a' => 'No. This page only shows you the totals; it is a view-only summary built from the clinic\'s recorded activity for the dates you picked.'],
    ]],
],
'reports' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A view-only overview of how the clinic is doing over a date range you choose: how many visits, money taken in, profit, doctor pay, and your best doctors and items. Nothing here can be edited.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Set the period with the "From" and "To" date boxes, then narrow it if you like using the "All branches" and "All doctors" dropdowns. The page updates by itself when you change any of these.',
        'Read the top cards for the headline numbers: "Visits", "Revenue", "Profit", "Avg visit value", "Doctor cut" and "Outstanding" (money still owed). A small arrow shows whether each is up or down versus the period before.',
        'Use the charts to spot patterns: "Profit trend" over time, "How you got paid" (cash, card, KNET, etc.), and "Visits by day of week".',
        'Scroll down to the "Top doctors" and "Top items" tables to see who and what brought in the most.',
        'Click "Print" at the top right to print or save the report as a PDF.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why do my numbers change when I pick a different branch or doctor?', 'a' => 'Every figure on the page only counts what matches the dates, branch and doctor you have selected. Change a filter and the whole page recalculates for that selection.'],
        ['q' => 'What does "Outstanding" mean?', 'a' => 'It is the money still owed for visits that have not been fully paid yet, shown in red with the number of unpaid visits underneath.'],
        ['q' => 'Can I change anything on this page?', 'a' => 'No. Reports is for looking only. To fix a figure, correct the original visit or payment on its own page, then come back here.'],
    ]],
],
'daily-closing' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A one-page summary of a single day: how many bookings came in, the money from visits, what was collected, what is still owed, and how each doctor did. Use it to wrap up the day.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the day in the "Date" box (it starts on today).',
        'Under "Branches", tap "All branches" to see everything, or tap one or more branch buttons to focus on just those.',
        'Read the cards: "Bookings" (totals and "Checked in" / "Auto no-show"), "Financials" for visits, "Cash collected" by method, and "Outstanding" for money still owed.',
        'Scroll down for the "Hourly bookings" chart and the doctors table showing each doctor\'s visits, revenue and profit.',
        'Press "Print" (top right) to print or save a copy of the day\'s summary.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I see more than one branch at once?', 'a' => 'Yes. Tap as many branch buttons as you like, or tap "All branches" to include them all.'],
        ['q' => 'What does "Auto no-show" mean?', 'a' => 'Bookings the system marked as no-show because the patient never checked in.'],
        ['q' => 'What is "Outstanding"?', 'a' => 'Money from the day\'s visits that has not been paid yet, with a count of the unpaid visits. It turns red when there is a balance still due.'],
    ]],
],
'daily-reconciliation' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A simple end-of-day cash-up. It lists every payment you took on one day, adds up the total, and shows how much came in by each payment type and by each staff member.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the day in "Date" (it starts on today) and, if you work across sites, choose a "Branch" or leave it on "All my branches".',
        'Read the top bar for the "Total collected", the number of "Payments", the "Avg payment", any "Refunds", and the "Outstanding" amount still owed.',
        'Check the "By payment method" ring to see how much was Cash, Card, KNET or Link.',
        'Check the "By collector" list to see how much each staff member took.',
        'Scroll to the table for every single payment, then tap "Print" to print or save a copy for your records.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Which payments show up here?', 'a' => 'Every completed payment dated on the day you picked, for the branches you can see. If you are a doctor, you only see payments from your own visits.'],
        ['q' => 'Why does a payment show "System (online)" as the collector?', 'a' => 'That payment was made online by the patient (for example through a payment link), so no staff member rang it up and the system is shown instead.'],
        ['q' => 'Can I change or fix a payment from this page?', 'a' => 'No. This page is only for viewing and printing. To correct a payment, open the patient\'s visit record.'],
    ]],
],
'executive' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A one-page overview of how the business is doing: money earned, profit, visits, and how branches, doctors and treatments are performing. It only shows numbers, you cannot change anything here.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the time range using the buttons at the top: Today, Week, Month, Quarter or Year. Choose Custom to set your own start and end dates.',
        'To look at just one location, pick it from the All branches box; leave it as is to see every branch together.',
        'Read the six cards at the top for the headline numbers: Revenue, Profit, Margin %, Avg transaction, Visits and Show rate %. The small arrow shows if each one went up or down compared to the period before.',
        'Scroll down to see the charts: Revenue trend over time, Payment mix, Booking sources, New vs returning patients, money owed grouped by age, and the Follow-up funnel (how many planned follow-ups turn into bookings).',
        'Check the tables lower down for Branch performance, Doctor performance, Item profitability and Cancellation analysis. Use the Print button (top right) to print or save the page.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does the little up or down arrow on a card mean?', 'a' => 'It compares the number you are looking at now to the same length of time just before it. A green up arrow means it grew, a red down arrow means it fell.'],
        ['q' => 'What is the Show rate?', 'a' => 'It is the share of booked patients who actually came in for their appointment, instead of not showing up.'],
        ['q' => 'Can I click a doctor or treatment to see more detail?', 'a' => 'Not here, this page is just a summary. For detailed lists, open the Clinic Reports page or the specific record instead.'],
    ]],
],
'clinics' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the list of clinics that own everything in the system. Each clinic has its own branches, staff, and specialties, and its name and footer text print on prescriptions and invoices.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New clinic" to add one. Fill in "Name (English)", "Name (Arabic)", and a short "Code / slug" that is used in links.',
        'Add the "MOH / commercial license" number and the "Print footer / disclaimer" text, since that footer shows at the bottom of prescriptions and invoices.',
        'Tick the "Medical specialties" you want, then click "Save".',
        'Use the search box or the "All" / "Active" / "Inactive" buttons to find a clinic, or click "Export Excel" to download the list.',
        'Click any clinic row to open and edit it. To turn one off, click the eye-off button on its row.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the "Default revenue account" for?', 'a' => 'It tells the system which income account this clinic\'s earnings go to. Leave it on "System default" unless your accountant asks you to change it.'],
        ['q' => 'Why can\'t I turn off a clinic?', 'a' => 'A clinic that still has branches cannot be turned off. Move or remove its branches on the Branches page first, then try again.'],
        ['q' => 'Where do the clinic name and footer appear?', 'a' => 'They print on the prescriptions and invoices created under that clinic, so patients see the right clinic details.'],
    ]],
],
'branches' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the list of your clinic locations. Each branch has its own address, phone, and how far ahead patients can book.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New branch", choose the owning "Clinic (owner)", and fill in the name, phone, city, and license number.',
        'Set "Max advance booking days" to control how far ahead patients can book appointments at this branch.',
        'Tick "Available for booking" so the branch accepts appointments; leave "Slug" empty and it fills in by itself.',
        'Click "Save". To change a branch later, click its row in the table to open it.',
        'Use the search box or the "All" / "Available" / "Unavailable" buttons to find a branch quickly.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I hide a branch?', 'a' => 'The eye-off button marks it "Unavailable" so it stops taking bookings, but nothing is deleted and its records are kept.'],
        ['q' => 'Why do I have to pick a clinic?', 'a' => 'Every branch belongs to one clinic. If the clinic is not in the list yet, add it first on the Clinics page.'],
        ['q' => 'Do I need to type the Slug?', 'a' => 'No. Leave it blank and it is created automatically from the branch name.'],
    ]],
],
'gateways' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the list of payment options your clinic can offer when collecting money. Each option is either a manual method you take in person (cash, KNET, card, payment link) or an online gateway that lets a patient pay by card over a link.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New account" to add a payment option, then choose "Manual / POS method" for in-person payments or "Online gateway" for online card payments.',
        'For a manual option, pick the "Manual payment method"; for an online one, pick the "Gateway" from the list.',
        'Type a "Display name" staff will recognise, choose the "Currency", tick "Active" to switch it on, and tick "Default" if it should be the option chosen first.',
        'Use "Owner" to decide where it appears: leave it as System for everywhere, or limit it to one Clinic, Branch, or Service.',
        'Click any row to edit it, or use the trash button to remove an option you no longer use.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the difference between manual and gateway?', 'a' => 'A manual option is for a payment you collect yourself at the desk (cash, KNET, card, or a link). An online gateway lets the patient pay by card online through a connected provider.'],
        ['q' => 'Why would I mark one as Default?', 'a' => 'The Default option is the one suggested first when staff take a payment, so picking the most-used method saves a step.'],
        ['q' => 'What are the Credentials fields for?', 'a' => 'These are connection settings for an online gateway, usually given to you by the provider. Leave them blank for normal manual methods.'],
    ]],
],
'roles' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A role is a job type (like reception or doctor) with a set of things it is allowed to do. Each staff member is given a role, and they only see the pages and buttons their role allows.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New role", type a "Role name", then tick the boxes for everything that role should be allowed to do.',
        'Permissions are grouped by area; use the "Filter permissions…" box to find one fast, or "Expand all" / "Collapse all" to open or close the groups.',
        'Inside any group, use "All" to tick everything in it at once, or "None" to clear it.',
        'Click a role in the list to open and change it; the small counter shows how many boxes are "selected".',
        'Click "Save" to apply your changes, or "Delete" to remove a role that has no staff assigned.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I rename or delete some roles?', 'a' => 'Roles marked "Protected" are built-in and cannot be renamed or deleted, but you can still change what they are allowed to do.'],
        ['q' => 'Why can\'t I delete a role?', 'a' => 'A role still given to one or more staff members cannot be deleted. Move those people to another role first, then the delete button will appear.'],
        ['q' => 'What happens when I change a role\'s permissions?', 'a' => 'Everyone with that role is affected right away. Pages and menu items they are no longer allowed to use will disappear for them, and newly allowed ones will show up.'],
    ]],
],
'settings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page holds the clinic\'s system settings, including your logo and the WhatsApp connection details. These settings apply across the whole clinic.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Look through the settings, which are sorted into groups such as "General", "WhatsApp API", and "WhatsApp Templates".',
        'Change a value by typing in its box, ticking or unticking a checkbox, or entering a number.',
        'To set the clinic logo, click "Upload" (or "Change" to replace it), and use "Remove" to clear it.',
        'For a password-style field that is already saved, leave it blank to keep what you have; only type a new value if you want to replace it.',
        'When you are done, click "Save settings" to apply your changes.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I see the saved value of a password or token field?', 'a' => 'For safety these values are hidden and never shown again. The box just tells you whether one is saved. Leave it blank when saving to keep the current value.'],
        ['q' => 'Do these settings apply to just one branch?', 'a' => 'No. They apply to the whole clinic, so a change here affects every branch.'],
        ['q' => 'The fields are greyed out and there is no Save button. Why?', 'a' => 'Your account can view settings but not change them. Ask an administrator if you need something updated.'],
    ]],
],
'activity' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A running history of everything that happens in the system — who added, changed, or deleted something, and when. You can only read it; nothing here can be edited.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type a name or keyword in the Search box at the top to find a specific entry.',
        'Use the "All types" and "All actions" pickers to show only one kind of record (like patients) or one kind of action (Added, Updated, Deleted, Restored).',
        'Set the "From" and "Until" dates to look at a certain time period.',
        'Read the "What changed" column to see the old value crossed out and the new value next to it.',
        'Click "Clear" to remove all filters and see the full list again.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I change or remove an entry here?', 'a' => 'No. This page is for viewing only — there is nothing to add, edit, or delete.'],
        ['q' => 'What does the "By" column mean?', 'a' => 'It shows the person who made the change. If the change happened automatically, it shows "System" instead of a name.'],
        ['q' => 'Why is my search showing nothing?', 'a' => 'A date range or one of the pickers may be hiding results. Click "Clear" to reset everything and try again.'],
    ]],
],
'wa-triggers' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page sets up the automatic replies your WhatsApp bot sends. Each rule (called a trigger) connects a customer message to a ready-made answer the bot sends back on its own.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New trigger". Choose a "Trigger type": Keyword (replies when a customer types a certain word), Welcome (greets a new chat), Finale (sent after a booking), or Fallback (replies when the bot does not understand).',
        'For a Keyword trigger, type each word in the "Keywords" box and press Enter to add it. Add as many as you like.',
        'Pick a "Response type" for how the bot answers: Text, Link, Image, Document, Buttons, List, Template, or Flow. The matching fields appear below for you to fill in.',
        'Fill in the fields that appear. For Text, fill the "Response (English)" and "Response (Arabic)" boxes so the bot can reply in the customer\'s language; for Image or Document, choose the file to upload.',
        'Turn on "Active" and click "Save". To change a rule later, click its row in the list; to remove one, click the trash icon.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the difference between the four trigger types?', 'a' => '"Keyword" fires when the customer sends a matching word. "Welcome" greets the start of a new chat. "Finale" is sent after a booking is completed. "Fallback" answers when the bot cannot understand the message.'],
        ['q' => 'Does turning off "Active" delete the rule?', 'a' => 'No. An inactive trigger is saved but the bot stops using it until you turn "Active" back on. To delete it for good, use the trash icon.'],
        ['q' => 'How is a "Template" reply different from a Campaign?', 'a' => 'A Template trigger sends an approved WhatsApp template as an automatic reply inside a live chat. Campaigns send a template to many phone numbers at once.'],
    ]],
],
'wa-campaigns' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page lists your bulk WhatsApp campaigns and lets you start a new one. A campaign sends the same approved WhatsApp message to many phone numbers at once. Admin-only.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New campaign", type a "Campaign name", pick a "Meta template" (the approved message wording), set "Max sends / minute", optionally set "Schedule at", then click "Create".',
        'After creating, the campaign opens. Add your phone numbers and any message details there, then send a test and launch it from that page.',
        'Back on this list, use the search box or the status dropdown ("All statuses", Draft, Scheduled, Running, etc.) to find a campaign.',
        'Click any row in the table to open that campaign and see its details or continue working on it.',
        'The "Total" and "Active/Scheduled" chips at the top give you a quick count of all campaigns and the ones currently sending or waiting to send.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Does sending cost money?', 'a' => 'Yes. Every WhatsApp message delivered to a customer is charged, so a big list costs more. Always send a test and check your numbers before launching.'],
        ['q' => 'What do the colored status labels mean?', 'a' => 'They show where each campaign is: Draft (not sent yet), Scheduled (set to send later), Running (sending now), Completed (finished), Failed, or Paused.'],
        ['q' => 'Why can I only see a "Template name (manual)" box instead of a dropdown?', 'a' => 'That happens when the approved message templates could not be loaded. You can still type the template name by hand and continue.'],
    ]],
],
'wa-commands' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page sets up the special words a customer can type in a WhatsApp chat to control the conversation, such as words that restart the chat, show the menu, or jump to a certain step. Only admins can change these.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New command", type the word a customer might send in "Keyword", and pick its "Language" (English or Arabic).',
        'Pick what should happen from "Action" (for example restart the chat, start it, or show the menu).',
        'If the action jumps the chat to a step, fill in the "Target state (for jump)" box with that step.',
        'Set a "Priority" number (when two words could match, the higher number wins) and tick "Enabled" so the bot uses it.',
        'Click "Save". To change a command later, click its row; to remove one, click the trash icon.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How is this different from Triggers?', 'a' => 'Commands are shortcut words that steer the chat (restart, menu, jump to a step). Triggers decide the actual reply the bot sends back.'],
        ['q' => 'Why is there a Language for each command?', 'a' => 'A command only works in chats of that language, so add both an English and an Arabic version if your customers write in both.'],
        ['q' => 'What does the Enabled checkbox do?', 'a' => 'When it is ticked the bot listens for that word; untick it to switch the command off without deleting it.'],
    ]],
],
'wa-messages' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you write the ready-made messages the WhatsApp bot sends to patients automatically. Each message has a short name and a language. The {token} parts are filled in with real details like the patient\'s name when the message is sent.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New template", type a short name in "Key" so you can recognise the message, and pick its "Language".',
        'Write the wording in the "Text" box. Where you want the bot to drop in a real detail (like a name or a date), leave the {token} part exactly as it is.',
        'Tick "Enabled" so the bot is allowed to send it, then click "Save".',
        'To change a message later, click its row; to remove one, click the trash icon on its row.',
        'Use the search box and the language menu at the top to find a message, and "Export Excel" to download the list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What are the {token} parts in curly brackets?', 'a' => 'They are placeholders the bot swaps for real information (such as the patient name or appointment time) when it sends the message. Keep them exactly as written so the right detail appears.'],
        ['q' => 'What happens if I untick "Enabled"?', 'a' => 'The bot stops sending that message until you tick it again. The message stays saved in the list, it just isn\'t used.'],
        ['q' => 'Can I write the same message in both languages?', 'a' => 'Yes. Create one entry in English and another in Arabic; the bot picks the version that matches the patient\'s language.'],
    ]],
],
'wa-texts' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A library of short pieces of wording the system reuses in different places. Each entry has a short name (the Key) and a language, so the right wording shows up automatically. This page is for admins.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New string", type a short name in "Key", and pick the language under "Locale" (English or Arabic).',
        'Type the wording you want in the "Text" box, then click "Save".',
        'Click any row in the list to open it and change the wording, then "Save" again.',
        'To remove an entry, click the small trash icon at the end of its row and confirm.',
        'Use the "Search key or text…" box and the language dropdown to find an entry quickly; click "Clear" to reset.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the "Key" for?', 'a' => 'It is a short name the system uses to find the right wording. Keep it the same when you add the English and Arabic versions so they stay linked.'],
        ['q' => 'I need the same wording in English and Arabic. How?', 'a' => 'Create two entries with the same Key: one with Locale set to English and one set to Arabic. The system shows each person the version that matches their language.'],
        ['q' => 'Why can\'t I find an entry I expected?', 'a' => 'Check the language dropdown at the top. It hides entries in other languages, so an Arabic entry may be hidden while you are viewing English. Set it to "All languages" to see everything.'],
    ]],
],
'wa-logs' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A history of every WhatsApp message the clinic has sent or received. For each one you can see the phone number, whether it was delivered, and the time. It is view-only, so nothing here can be changed.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type a phone number or message ID into the "Search phone or message id…" box to find a specific message.',
        'Use the "All statuses" dropdown to show only messages with a certain delivery state (for example, only failed ones).',
        'Click any row to open the details panel and see the full message contents.',
        'Press "Clear" to remove your search and filter and see the whole list again.',
        'Click "Export Excel" to download the messages you are looking at as a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I send or resend a message from here?', 'a' => 'No. This page only shows what already happened. To send a new message or run a campaign, use the WhatsApp messaging pages.'],
        ['q' => 'A message shows as failed. How do I find out why?', 'a' => 'Click the row to open its details panel, where the reason for the failure is usually shown.'],
        ['q' => 'What is the "Message ID"?', 'a' => 'It is the unique reference WhatsApp gives each message. You can use it to match a message here with a WhatsApp delivery report.'],
    ]],
],
'wa-sessions' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A view-only list of WhatsApp chats happening right now, one line per phone number. It shows where each person is in the automated conversation, their language, and when they last replied.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type a phone number in the "Search phone…" box to jump to one person\'s chat.',
        'Use the "All statuses" dropdown to show only chats in a certain state.',
        'Read the "Screen" column to see where someone is in the chat, and "Last interaction" to see when they last replied.',
        'Click any row to open the "Context" panel and see the details saved for that chat.',
        'Press "Clear" to remove your search and filter and show every chat again.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I send a message or restart someone\'s chat from here?', 'a' => 'No, this page is only for viewing. It shows you what is going on; to change how chats behave, use the Commands and Triggers pages.'],
        ['q' => 'What does the "Screen" column mean?', 'a' => 'It is the current step the person has reached in the automated chat, so you can tell, for example, if they are still booking or already finished.'],
        ['q' => 'Why does "Last interaction" matter?', 'a' => 'WhatsApp only lets you reply freely for 24 hours after the customer\'s last message. The "Last interaction" time tells you whether that window is still open.'],
    ]],
],
'wa-audience' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A view-only list of your contacts and how they engage by phone number: how many bookings each has made, how many were confirmed, their last branch and last contact. Use it to pick who to message in a WhatsApp campaign.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Type a number in the "Search phone…" box to find one contact.',
        'Set "Min bookings" to show only people who have booked at least that many times.',
        'Use the "From" and "To" date boxes to show only people whose last booking falls in that range.',
        'Read the "Bookings", "Confirmed", "Last branch" and "Last interaction" columns to decide who is worth contacting. The chips at the top show the "Total" contacts and how many have a booking.',
        'Click "Export Excel" to download the list you are currently looking at as a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can I send messages from this page?', 'a' => 'No, this page only shows information. Use it to decide who to reach, then send the messages from the WhatsApp campaign pages.'],
        ['q' => 'Why can\'t I change the numbers in the table?', 'a' => 'They are counted automatically from real booking activity, so they are always view-only and kept up to date for you.'],
        ['q' => 'Does "Export Excel" give me everyone?', 'a' => 'It gives you exactly the list shown after your search and filters. Set the filters first, then export to get just those contacts.'],
    ]],
],
'stock-transfers' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Move stock from one branch to another. The main store (the hub) sends items to a branch that is running low. This just moves stock between your branches — it has no effect on your money or accounts.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New transfer", leave "Source branch" as the hub (or pick another), and choose the "Destination branch" that will receive the stock.',
        'Under "Items", pick an item, type the "Qty" to send, and click "Add". Repeat for every item, then click "Create transfer" — it saves as "Pending" and nothing has moved yet.',
        'When the stock is ready to go, click "Dispatch" on the pending row. The items now leave the source and arrive at the destination.',
        'Click "Cancel" on a pending transfer you no longer need.',
        'Use the "All / Pending / Dispatched / Cancelled" tabs at the top to filter the list.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'It says "No hub set for this clinic" — what do I do?', 'a' => 'You first need a main store to send from. Open a branch in its settings and mark it as the hub. Until then the "New transfer" button stays limited.'],
        ['q' => 'When does the stock actually move?', 'a' => 'Only when you click "Dispatch". Creating a transfer just saves a "Pending" request and does not change any quantities yet.'],
        ['q' => 'Can I send more than the branch has?', 'a' => 'No. You can only send what is in stock at the source. The item list shows how much is on hand for each item.'],
    ]],
],
'payroll-runs' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you run staff salaries each month. One run gathers all your active staff into a single batch of payslips, then lets you approve it and pay everyone.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New payroll run", pick the Year and Month (and a Branch and Pay date if you want), then click "Create". The payslips are filled in for you from each person\'s saved salary details.',
        'Open the new run to review it. Each row is one staff member; click a row to see the breakdown of their pay and deductions. Use "Regenerate" to refresh the figures if you changed a salary, loan or leave while the run is still a Draft.',
        'When the figures look right, click "Approve & post". This locks the run so the totals can no longer change.',
        'Click "Mark paid", choose the cash or bank account you are paying from in "Pay from account", and click "Confirm payment".',
        'Use "Delete draft" to throw away a run you have not approved yet, or "Export Excel" to download all the payslips.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Where do the amounts on each payslip come from?', 'a' => 'They are built from each person\'s saved salary setup (basic pay plus allowances, minus regular deductions), plus any doctor commission earned, minus any loan installment and unpaid-leave days for that month.'],
        ['q' => 'Can I change a payslip after I approve the run?', 'a' => 'No. Approving locks the run. Make your corrections while it is still a Draft, click "Regenerate", and then approve.'],
        ['q' => 'I made a mistake and the run is still a Draft. What do I do?', 'a' => 'Fix the staff member\'s salary, loan or leave, come back to the run, and click "Regenerate" to pull the corrected figures. If you want to start over, use "Delete draft".'],
    ]],
],
'salary-profiles' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A salary card for each staff member: their basic salary, any monthly allowances and deductions, and their annual leave days. The monthly payroll uses these cards to work out everyone\'s pay.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "Add profile", choose the staff member, then type their "Basic salary" and "Annual leave days".',
        'Add any extra monthly pay under "Allowances" (e.g. Housing) and anything taken off under "Deductions" — give each a label and an amount.',
        'Optionally set the "Hire date" and a branch, tick "Active" for staff currently working, then click Save.',
        'To change a card later, click the pencil icon on its row; to remove one, click the trash icon.',
        'Use the search box and the "All / Active / Inactive" filter to find someone, or "Export Excel" / "Import" to download or upload many at once.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Can one person have two salary cards?', 'a' => 'No — each staff member has just one. If you import the same person again, their existing card is updated instead of a second one being created.'],
        ['q' => 'What is the hire date for?', 'a' => 'It marks when their service started, which is used to work out their end-of-service gratuity later on.'],
        ['q' => 'How do I add everyone\'s salaries quickly at the start?', 'a' => 'Click "Import", download the template, fill in the staff and amounts, then upload it and preview before confirming.'],
    ]],
],
'staff-loans' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A place to give staff a loan or a salary advance and have it paid back automatically. Each payroll run takes a set instalment out of their pay until the loan is fully repaid.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New loan", pick the staff member, choose "Loan" or "Advance", and fill in the "Principal amount" (total given), the "Installment amount" to take from each payroll run, and the "Issued on" date. Save it — it starts as Pending.',
        'When you are ready to actually pay it out, click the green tick (Approve). This hands over the money and starts the repayments. You can only edit a loan while it is still Pending.',
        'From then on, every payroll run quietly takes the instalment off the person\'s pay, and the "Outstanding" balance drops. The loan marks itself "Settled" once it reaches zero.',
        'Click the ban icon (Cancel) to stop a loan that should not go ahead, or the trash icon (Delete) to remove one that was never paid out.',
        'Use the search box and the type and status filters to find a loan; use "Export Excel" to download the list or "Import" to bring in many loans at once.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'When does the money actually get paid out?', 'a' => 'Only when you click Approve (the green tick). A Pending loan has not been paid out yet, so you can still edit or delete it.'],
        ['q' => 'How does the staff member pay it back?', 'a' => 'Automatically. Each payroll run takes the instalment amount out of their pay and lowers the outstanding balance until the loan is settled. You do not record repayments by hand.'],
        ['q' => 'Can I delete a loan that has already been paid out?', 'a' => 'No. Once it is paid out it is kept for the records. Use Cancel instead, or let the repayments finish it off.'],
    ]],
],
'leave-balances' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This page shows how many annual-leave days each staff member has for a chosen year. The remaining days are worked out for you: days they are entitled to, plus any days carried over from last year, minus the leave they have already taken.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the year box at the top to choose the year you want to see; the table lists every staff member with their Entitled, Carried over, Used (approved), Pending and Remaining days.',
        'Click the pencil (Edit entitlement) on a person\'s row to set their "Entitled days" and any "Carried-over days", add a note if you like, then click "Save".',
        'Click "Seed year from profiles" to quickly create starting balances for everyone who does not have one yet, based on the leave days in their staff profile.',
        'Type in the search box to find a person by name or email, and use "Import" to load many balances at once from a spreadsheet.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I change the "Used" days?', 'a' => 'Used days are added up automatically from the leave requests that have been approved, so the balance always stays correct. You only set how many days a person is entitled to.'],
        ['q' => 'What are "Carried-over" days?', 'a' => 'These are unused days brought forward from the previous year. They are added on top of this year\'s entitlement when working out the remaining days.'],
        ['q' => 'What is the difference between "Pending" and "Used"?', 'a' => 'Pending days are leave that has been requested but not yet approved, shown so you are aware of it. Only approved leave counts as used against the balance.'],
    ]],
],
'settlements' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is where you work out the final pay for a staff member who is leaving. It calculates their end-of-service gratuity under Kuwait labour law, adds any unused leave, takes off any loans they still owe, and shows the net amount to pay them.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New settlement", choose the "Staff member", set their "Last working day", and pick the "Reason for leaving" — "Termination / non-renewal (full)" or "Resignation (reduced)".',
        'The form fills in years of service, gratuity and leave automatically. Type into "Other additions" or "Other deductions" if you need to, then check the "Net settlement" figure and click "Save draft".',
        'On the saved row, click the green tick to "Approve" it — this records it in the books and clears the person\'s loans.',
        'Once approved, click the banknote icon to "Pay", choose the "Pay from account", and click "Confirm payment".',
        'Use the dropdown at the top (All / Draft / Approved / Paid) to filter the list, and the trash icon to delete a draft you no longer need.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How is the gratuity worked out?', 'a' => 'From the staff member\'s hire date, last working day and basic salary, following Kuwait labour-law rules. Resignation gives a reduced amount; termination or non-renewal gives the full amount.'],
        ['q' => 'It says the staff member has no salary profile — what do I do?', 'a' => 'The gratuity needs a basic salary and hire date to calculate. Add a salary profile for that person first, then come back and create the settlement.'],
        ['q' => 'Can I change a settlement after approving it?', 'a' => 'No. While it is still a draft you can delete it and start again, but once you approve it the figures are locked and recorded in the books — so review them carefully before you approve.'],
    ]],
],
'visit-console' => [
    'what' => ['heading' => 'What is this?', 'body' => 'This is the one screen for a single patient visit. The doctor writes the notes and prescription here, and reception adds the services, applies any discount, and takes payment.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the tabs near the top to move between Overview (doctor notes), Items (services and packages), Payments, and Notes.',
        'On Overview, type into the boxes such as Chief complaint, Diagnosis, Prescriptions, Lab requests and Follow-up date. Each box saves on its own and shows a "Saved" message, so there is no separate save button.',
        'On the Items tab press Add item to add a service or product, or Add package for a bundle. Use the Discount & coupon panel to lower the bill.',
        'On the Payments tab press Record payment, then enter the Amount, choose the Kind and Method, and add a Reference # if needed. The "Visit balance after this payment" line shows what is still owed. Use Apply insurance when the patient has a policy.',
        'Use the blue button at the top right to move the visit forward. It changes with the stage: Start treatment, then Complete visit, then Discharge to payment.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens when I press Complete visit?', 'a' => 'It hands the visit to reception for payment and moves it to the "Awaiting payment" stage. The doctor\'s notes lock at this point, so finish writing before you press it. The visit is only fully closed later, when reception presses Discharge to payment.'],
        ['q' => 'I recorded a payment by mistake. How do I fix it?', 'a' => 'On the Payments tab use the trash button on that line to void the payment. The amount stops counting, so the visit balance goes back up. You can only void a payment you collected, unless you are an admin.'],
        ['q' => 'What does setting a Follow-up date do?', 'a' => 'It plans the patient to come back and can automatically book the follow-up appointment for that day.'],
    ]],
],
'my-earnings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Your personal earnings from the visits you completed on a chosen day. Use it to check your share when the day is closed.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Pick the day you want to review using the Date box at the top.',
        'Read the cards across the top: My earnings, My share rate, Avg / visit, Visits, Total fees and Total profit.',
        'Look down the table to see each visit, the patient, your cut, and the running total.',
        'A visit marked Unpaid means the money has not been collected from the patient yet.',
        'Press Print to get a paper copy for reconciling at day close.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why is a visit marked Unpaid?', 'a' => 'It means the patient has not paid for that visit yet. It still counts toward your earnings once it is collected.'],
        ['q' => 'My earnings look low for today.', 'a' => 'Make sure the Date box shows today. Only completed visits for the selected day appear here.'],
        ['q' => 'What does My share rate mean?', 'a' => 'It is the average percentage of the profit that goes to you across the day\'s visits.'],
    ]],
],
'coupons' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Discount codes that a patient or the reception can apply to a visit at checkout to lower the bill.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Press New coupon to create a code.',
        'Type the Code, choose the Discount type (Amount in KWD or Percent), and enter the Discount value.',
        'Optionally set a Min visit subtotal, a Starts at / Ends at date, a Branch, and Max uses.',
        'Tick Active so the code can be used, then press Save.',
        'Use the search box and the All / Active / Inactive buttons to find existing coupons; click any row to edit it.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I stop a coupon from being used?', 'a' => 'Open the coupon and untick Active, then Save. To remove it completely, use the trash icon on its row.'],
        ['q' => 'What is Max discount for?', 'a' => 'For percent coupons only, it caps how much money can be taken off, no matter how large the bill.'],
        ['q' => 'Can a coupon and a promotion both apply?', 'a' => 'Only if the coupon has Stacks with promotions ticked. Otherwise the coupon will not combine with promotions.'],
    ]],
],
'promotions' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Automatic discounts on chosen items or services for a set period. They apply on their own when the item is added to a visit, with no code needed.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Press New promotion and give it a Name.',
        'Choose the Discount type (Amount or Percent) and enter the Discount value.',
        'Under Applies to, pick the scope: All items, By type, Specific items, All packages or Specific packages, then choose the items or packages if asked.',
        'Optionally set a Branch, Starts at / Ends at dates and a Priority, tick Active, and press Save.',
        'Use the search box and the All / Active / Inactive buttons to find promotions; click any row to edit it.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Do I need to give patients a code?', 'a' => 'No. Promotions apply automatically when a matching item or service is added to a visit.'],
        ['q' => 'What does Priority do?', 'a' => 'When more than one promotion could apply, the one with the higher priority is used.'],
        ['q' => 'How do I end a promotion?', 'a' => 'Open it and untick Active, or set an Ends at date. You can also delete it with the trash icon.'],
    ]],
],
'posting-accounts' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Lets you choose which accounting account the system uses automatically for each kind of event, such as sales or payments.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read each row to see the event and the account it currently uses; a Default tag means the built-in EVA account is used.',
        'To change one, open its account picker and choose a different account; the row will show a Custom tag.',
        'To go back, press Reset to default on that row.',
        'When you are done, press Save changes at the top.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does Default mean?', 'a' => 'The event uses the standard account EVA set up. You only change it if your accountant wants a different account.'],
        ['q' => 'When is the best time to change a mapping?', 'a' => 'At the start of an accounting period. If you change mid-month the balance splits between the old and new account, so ask your accountant to make a correcting entry.'],
        ['q' => 'Will changing an account affect past records?', 'a' => 'No. Past entries are never changed, and reports count both accounts together.'],
    ]],
],
'fixed-assets' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A register of the clinic\'s long-term assets, such as equipment, and how their value is spread out over time (depreciation).'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the top cards for Total cost, Accumulated depreciation and Net book value.',
        'Press New asset to add an item, fill in its details, and save.',
        'Click any row to open and edit that asset.',
        'Once a month, press Run depreciation (this month) to record this month\'s value drop for all assets.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is Net book value?', 'a' => 'It is what the asset is worth now: its original cost minus the depreciation recorded so far.'],
        ['q' => 'How often should I run depreciation?', 'a' => 'Once each month. Running it records the monthly amount for every active asset.'],
        ['q' => 'What does Fully depreciated mean?', 'a' => 'The asset has been written down over its full life, so no more depreciation is recorded for it.'],
    ]],
],
'prepaid-schedules' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A register of expenses paid in advance, such as a yearly rent or subscription, that are spread out evenly over the months they cover.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the top cards for Total prepaid, Amortized and Remaining.',
        'Press New prepayment, enter the amount, term and dates, and save.',
        'Click any row to open and edit that prepayment.',
        'Once a month, press Run amortization (this month) to record this month\'s portion as an expense.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does Amortized mean?', 'a' => 'It is the part of the prepaid amount that has already been turned into an expense over time.'],
        ['q' => 'How often should I run amortization?', 'a' => 'Once each month. It moves one month\'s slice of every active prepayment into expenses.'],
        ['q' => 'What is Remaining?', 'a' => 'The amount still sitting as prepaid that has not yet been expensed.'],
    ]],
],
'aging' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A report showing money owed to the clinic by patients and insurers, or money the clinic owes vendors, grouped by how long it has been outstanding.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Use the Accounts Receivable Aging / Accounts Payable Aging buttons to switch between money owed to you and money you owe.',
        'Set the As of date to choose the cut-off day for the report.',
        'If you have more than one branch, pick a Branch or leave it on All branches.',
        'Read each row across the age columns: 0-30 days, 31-60, 61-90 and 90+; the 90+ amounts are the most overdue.',
        'Press Print for a paper copy.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the difference between Receivable and Payable?', 'a' => 'Receivable is money owed to the clinic. Payable is money the clinic owes to vendors.'],
        ['q' => 'Why are the 90+ amounts in red?', 'a' => 'They are the oldest, most overdue balances and usually need the most urgent follow-up.'],
        ['q' => 'What does As of mean?', 'a' => 'It is the date the balances are measured up to, so you can see how things stood on any chosen day.'],
    ]],
],
'wap-dashboard' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A one-glance overview of your WhatsApp activity, with totals for templates, contacts, campaigns and messages, plus a box to send a quick message.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the colored tiles at the top to see how many templates, contacts, campaigns, conversations and messages you have.',
        'Check the green "Connected" badge at the top — it tells you the WhatsApp number is online and ready.',
        'To send a fast one-off message, type the phone number in the "Phone" box and your text in the "Message" box, then press "Send".',
        'Look at "Recent messages" to see the latest texts going in and out.',
        'Click "Open settings" to change the bot replies or check the number\'s health.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'It says WhatsApp is not configured. What now?', 'a' => 'The number has not been connected yet. Ask whoever set up the system to finish connecting WhatsApp before you can send.'],
        ['q' => 'What does the "Quick send" box do?', 'a' => 'It sends a single plain message to one phone number right away. For sending to many people at once, use Campaigns instead.'],
        ['q' => 'Why are some counts zero?', 'a' => 'You simply have not created any templates, contacts or campaigns yet. The numbers grow as you start using each page.'],
    ]],
],
'wap-inbox' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A shared WhatsApp chat screen where your team can read and reply to every customer conversation in one place.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click any chat in the left list to open it, or use the search box at the top to find a person.',
        'Use the "All", "Open" and "Resolved" tabs to filter which chats you see.',
        'Type your reply at the bottom and press Enter or the green send button.',
        'To start a brand-new chat, click the message-plus icon, enter the "Phone number" and an optional "First message", then press "Start".',
        'If the chat shows a 24-hour window warning, click "Send template" and pick an approved template to reach the customer.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why can\'t I type a free reply sometimes?', 'a' => 'WhatsApp only allows free messages within 24 hours of the customer\'s last message. After that you must send an approved template, using the "Send template" button.'],
        ['q' => 'What do the grey and blue ticks mean?', 'a' => 'One tick means sent, a double blue tick means the customer has read your message.'],
        ['q' => 'Can two staff use the inbox at once?', 'a' => 'Yes, it is shared. Use the refresh icon to pull in the newest messages.'],
    ]],
],
'wap-templates' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Your library of pre-written, WhatsApp-approved messages. Templates are required for campaigns and for messaging people outside the 24-hour window.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New template" to write a normal message template, or "Carousel" for one with multiple sliding cards.',
        'Use the search box to find a template by name.',
        'After creating one, open its menu (the three dots) and choose "Submit for review" to send it to WhatsApp for approval.',
        'Watch the colored status badge: green is Approved, orange is Pending, red is Rejected.',
        'Use "Sync from Meta" to pull the latest approval statuses, or "Refresh status" on a single template.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why must templates be approved?', 'a' => 'WhatsApp reviews every template to stop spam. Only approved ones can be used in campaigns or sent outside the 24-hour window.'],
        ['q' => 'What is "Toggle auto-reply"?', 'a' => 'It marks a template so the bot can send it automatically as a reply. Turn it on or off from the template menu.'],
        ['q' => 'A template was rejected — what do I do?', 'a' => 'Open it, edit the wording to follow WhatsApp\'s rules, and submit it for review again.'],
    ]],
],
'wap-media' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A library for images, videos and documents you can reuse in templates and campaigns.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Drag files onto the upload box, or click "browse" to choose files from your computer.',
        'Allowed files are JPG, PNG, MP4 and PDF, up to 16MB each.',
        'Hover over a file and click the link icon to "Copy URL" so you can paste it into a template.',
        'Click the trash icon on a file to "Delete" it when you no longer need it.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'My upload failed. Why?', 'a' => 'Check the file is a JPG, PNG, MP4 or PDF and under 16MB. Other types or larger files are rejected.'],
        ['q' => 'How do I use a file in a message?', 'a' => 'Copy its URL with the link icon, then paste it where the template asks for a media link.'],
        ['q' => 'Can I upload several files at once?', 'a' => 'Yes — select or drop multiple files and they upload one after another.'],
    ]],
],
'wap-contacts' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Your phone-number directory. Save people, sort them into groups, and target them in campaigns.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "Contact" to add one person, filling in their "Phone", "Name" and "Locale".',
        'Click "Group" to make a folder of contacts, then assign people using the "Add to group" dropdown on each row.',
        'Use "Import" to upload many contacts from a CSV file (columns: phone, name, locale), or "Export" to download them.',
        'Click "Smart group" to auto-build a group from a rule like "Active (last 30 days)" — press "Refresh engagement" first so the numbers are current.',
        'Search by phone or name at any time using the search box.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What do the engagement icons mean?', 'a' => 'They show how a contact responds: a green dot for active, a tick for delivered, an eye for read, a reply arrow for replied and a cross for failed.'],
        ['q' => 'What is the difference between a normal and a smart group?', 'a' => 'A normal group is filled by hand. A smart group fills itself automatically based on a rule you pick.'],
        ['q' => 'My import did nothing — why?', 'a' => 'Make sure the file is a CSV with phone, name and locale columns, and tick "Has header row" only if the first line is column titles.'],
    ]],
],
'wap-campaigns' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Send one approved template message to many contacts at once, then track how it was delivered and read.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Click "New campaign" to pick a template and choose who receives it.',
        'Before sending, click "Send test" on a campaign to try it on your own phone first.',
        'Open the campaign menu (three dots) and choose "Validate & send" to start sending, or "Pause" and "Resume" to control it.',
        'Watch the delivery breakdown bar and the Delivered, Read and Failed tiles to see how it is going.',
        'Use "Deep dive" or "Analytics" for full details, and "Export failed CSV" to get the list of numbers that did not receive it.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'Why won\'t my campaign send?', 'a' => 'You likely have too few WhatsApp points. The warning at the top links to the Points page where you can top up.'],
        ['q' => 'Do I need an approved template?', 'a' => 'Yes. Campaigns can only use templates that WhatsApp has approved.'],
        ['q' => 'What does "Failed / limited" mean?', 'a' => 'Those messages could not be delivered — for example a wrong number or a sending limit was reached. Use "Export failed CSV" to review them.'],
    ]],
],
'wap-points' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Your sending balance. Every template message uses points, so you top up here to keep campaigns running.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Check the "Current balance" tile to see how many points you have left.',
        'To add points, set the "Points" amount in "Top up points" and click "Add points".',
        'Use the "Quick add" buttons (+1,000, +5,000 and so on) to top up common amounts in one click.',
        'Review the "Top-ups" and "Usage log" tables to see what was added and what was spent.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What happens if my balance hits zero?', 'a' => 'Campaigns stop sending until you top up. A red warning will appear at the top of the page.'],
        ['q' => 'Do incoming messages cost points?', 'a' => 'No. Points are used for template messages you send, mainly through campaigns.'],
        ['q' => 'What are the "Amount paid" and "Note" fields for?', 'a' => 'They are optional records so you can note how much a top-up cost and why, for your own bookkeeping.'],
    ]],
],
'wap-logs' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A full record of every WhatsApp message sent and received, useful for checking what happened and finding failures.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Read the top tiles for quick totals of Total, Inbound, Outbound and Failed messages.',
        'Use the search box to find a message by its text or phone number.',
        'Filter the list with the "All", "Inbound" and "Outbound" buttons.',
        'Click any row to open its full details, including the complete message and any error.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'How do I find why a message failed?', 'a' => 'Filter or look for red "failed" rows, then click the row to read the error message in the detail popup.'],
        ['q' => 'What do the status colors mean?', 'a' => 'They follow the message journey: sent, delivered, read, pending or failed.'],
        ['q' => 'Can I reply from here?', 'a' => 'No, this page is for viewing history only. Use the Inbox to reply to customers.'],
    ]],
],
'wap-sessions' => [
    'what' => ['heading' => 'What is this?', 'body' => 'A list of the people currently chatting with your automated bot, showing where each one is in the conversation flow.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Scan the table to see each person\'s phone, name, current status and last interaction time.',
        'Open a row\'s menu (the three dots) and choose "Block" to stop the bot from responding to that number.',
        'Choose "Unblock" from the same menu to allow that number again.',
        'Choose "Delete" to remove a session and reset that person\'s place in the flow.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What does "Block" do?', 'a' => 'It stops the automatic bot from replying to that number. Use it for spam or unwanted contacts.'],
        ['q' => 'Is deleting a session the same as deleting the contact?', 'a' => 'No. It only clears their current conversation state with the bot; their contact record stays.'],
        ['q' => 'Why is the list empty?', 'a' => 'No one has interacted with the bot yet, or sessions have been cleared.'],
    ]],
],
'wap-settings' => [
    'what' => ['heading' => 'What is this?', 'body' => 'Where you set the bot\'s greeting and reply messages and check the health of your WhatsApp number.'],
    'how' => ['heading' => 'How to use it', 'items' => [
        'Check the "Number health" box at the top for the quality rating, phone number and messaging tier.',
        'Pick how the bot starts a chat using "Entry mode" — Flow (interactive menu), List, or Keyword auto-reply.',
        'Fill in the "Greetings" and "Canned replies" boxes in both English and Arabic so customers get the right message.',
        'List any "Stop keywords" (like agent or human) that should switch off the auto-reply and hand the chat to a person.',
        'Click "Save settings" when you are done.',
    ]],
    'faq' => ['heading' => 'Common questions', 'items' => [
        ['q' => 'What is the quality rating?', 'a' => 'It is WhatsApp\'s score for your number — High (green), Medium (orange) or Low (red). Keep it high by sending wanted, relevant messages.'],
        ['q' => 'Why are there English and Arabic boxes for each message?', 'a' => 'So the bot can reply in the customer\'s own language. Fill both for the best experience.'],
        ['q' => 'What is "Keyword auto-reply" mode?', 'a' => 'In this mode the bot answers based on keywords, using the canned replies you set on this page.'],
    ]],
],

    ],
];
