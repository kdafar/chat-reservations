<?php

return [
    'modal' => [
        'heading' => 'How to use: :page',
        'description' => 'Quick guide for this page',
    ],

    'pages' => [

        'waiting_patients' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Live queue of patients who have checked in for their booking and are waiting to be seen, currently in treatment, or waiting on stock. This is the doctor/nurse room console — reception uses Bookings instead.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click Accept on a row to move a patient from Waiting → In Progress.',
                    'Click Items / Packages to add charges to the visit as you go.',
                    'Click Finish Treatment when done — the patient moves to Awaiting Payment for reception.',
                    'Rows refresh automatically every 10 seconds; no need to reload.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => "Don't see a patient who just arrived?", 'a' => 'Reception must Check-in the booking first (from the Bookings page). Only checked-in bookings appear here.'],
                    ['q' => 'Why is a row marked Awaiting Stock?', 'a' => 'Doctor requested a stock item that is not currently in inventory. Pharmacy/store handles the request, then the row returns to Awaiting Doctor.'],
                    ['q' => 'What is the Awaiting Payment counter?', 'a' => 'Patients you have already finished treatment for; reception is collecting payment. They do not block your queue.'],
                ],
            ],
        ],

        'nurse_station' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => "Today's visit list for nurses. Shows every patient whose visit was checked in, queued, or created today across all doctors and rooms — useful for nursing tasks that span the whole clinic floor.",
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Use the filters at the top to narrow by status, doctor, or hide non-today rows.',
                    'Click Open Visit to view or edit the full visit record.',
                    'Click Items to jump straight into the items/charges section of a visit.',
                    'Click Follow-up to record a follow-up plan for a patient.',
                    'The list refreshes every 10 seconds automatically.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'How is this different from Room Console (Waiting Patients)?', 'a' => 'Room Console is for the doctor seeing patients in their room. Nurse Station is a broader view of today\'s clinic activity for nurses supporting all doctors.'],
                    ['q' => 'A patient is missing — why?', 'a' => 'Only patients with a visit row that was checked in, queued, or created today appear. Bookings that have not been checked in yet show in the Bookings page instead.'],
                    ['q' => 'Can I change a visit status from here?', 'a' => 'No, this page is read-only for status. Open the visit to update treatment, items, or status.'],
                ],
            ],
        ],

        'check_in_scanner' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Quick check-in tool for reception. Scan the QR code on the patient\'s booking confirmation (sent by WhatsApp or shown on their phone) to instantly mark the booking as checked in and start a visit.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click the scanner area and allow camera access on first use.',
                    'Hold the patient\'s booking QR code up to the camera until it beeps.',
                    'Confirm the result panel shows the right patient, room, and time.',
                    'If the camera will not start, paste the booking link or token into the manual entry field.',
                    'After a successful scan, the patient is queued for the doctor automatically.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => '"Invalid QR code" error?', 'a' => 'The scanned image is not a clinic booking pass. Make sure the patient is showing their booking confirmation, not a payment receipt or another QR.'],
                    ['q' => 'Patient lost their QR — can I still check them in?', 'a' => 'Yes. Open the booking from the Bookings page and use the Check In action there instead.'],
                    ['q' => 'Why did check-in fail with a "window" message?', 'a' => 'Check-in is only allowed within a few minutes before or after the appointment time. Outside that window, the booking must be rescheduled or checked in manually by a manager.'],
                ],
            ],
        ],

        'doctor_schedule' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'A doctor-centric view of upcoming bookings. Lets you see one doctor\'s schedule for today, tomorrow, this week, or all upcoming days, optionally filtered by morning, afternoon, or evening slots.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick a doctor from the "Filter by Doctor" dropdown — by default the first active doctor is shown.',
                    'Use the Period buttons (Today / Tomorrow / This week / All upcoming) to change the date range.',
                    'Optionally narrow by time of day: Morning, Afternoon, or Evening.',
                    'Click WhatsApp on a row to message the patient.',
                    'Click Check In to mark the patient as arrived (only available within the configured check-in window).',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Check In button is hidden — why?', 'a' => 'It only shows for confirmed bookings that have not been checked in yet. Pending or cancelled bookings cannot be checked in here.'],
                    ['q' => 'I get a "Check-in not allowed" message?', 'a' => 'Check-in is only allowed within a configured window around the appointment time (default 60 minutes before/after). Outside that, the appointment must be rescheduled.'],
                    ['q' => 'Where do I see past bookings?', 'a' => 'This page focuses on the upcoming schedule. Use the Bookings page for historical bookings.'],
                    ['q' => 'No bookings show up?', 'a' => 'Check the Period filter — by default it shows Today only. Switch to "All Upcoming" to see future days.'],
                ],
            ],
        ],

        'clinic_dashboard' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'The main landing page for clinic staff. Shows the day\'s key numbers at a glance — bookings, patients waiting, completed visits, money collected today, pending payments — plus a short list of today\'s upcoming appointments.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Glance at the KPI tiles to see how the clinic is doing right now.',
                    'Scroll the "Today\'s Appointments" list to see who is coming in and whether they have checked in yet.',
                    'Use the sidebar to jump into Bookings, Visits, Reports, or other modules.',
                    'Numbers are a snapshot taken when the page loads — refresh the page to update them.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'What does "Awaiting Now" count?', 'a' => 'Patients whose visit is currently awaiting the doctor, in progress, awaiting stock, or awaiting payment — anyone not yet finished.'],
                    ['q' => 'Why is Revenue Today different from the daily reports?', 'a' => 'This figure sums today\'s "paid" payments only. For reconciliation and breakdowns by method or collector, open the Daily Reconciliation or Daily Closing reports.'],
                    ['q' => 'Numbers look stale?', 'a' => 'The dashboard does not auto-refresh. Reload the page to recalculate.'],
                ],
            ],
        ],

        'clinic_reports' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'A filterable reporting view for clinic management. Lets you pick a date range, branch, and doctor, then explore profit, trends, top doctors, and top items through interactive widgets.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick a date range using the From / To pickers — defaults to start of this month through today.',
                    'Optionally filter by Branch and/or Doctor.',
                    'Switch tabs (Overview / Trends / Doctors / Items) to see different angles on the same data.',
                    'Widgets update automatically when you change a filter.',
                    'For a fixed-date end-of-day report instead, use Daily Closing.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'A widget is empty?', 'a' => 'Usually means no completed visits or paid payments fall inside your filter. Widen the date range or clear the branch/doctor filter.'],
                    ['q' => 'How is profit calculated?', 'a' => 'Profit = revenue (fees + packages + items − discounts) minus cost of goods and doctor commission. It does not include fixed overheads.'],
                    ['q' => 'Why are the numbers different from Daily Closing?', 'a' => 'Daily Closing covers a single calendar day per branch using closing logic. Clinic Reports aggregates over the range you pick.'],
                ],
            ],
        ],

        'clinic_reporting_dashboard' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'A widget-driven overview of clinic performance. Shows profit overview, profit and margin trends, doctor commission trends, and the top doctors and items — all in one place as a snapshot of the current period.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Scroll through the widgets to see each report card.',
                    'Hover or tap chart elements to see exact values.',
                    'For filterable date ranges and per-branch/per-doctor reports, use the "Clinic Reports (Filters)" page instead.',
                    'Refresh the browser page to recalculate the snapshot.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Can I filter this page by date?', 'a' => 'Not on this dashboard — filters are not available in this build. Use the "Clinic Reports (Filters)" page when you need date, branch, or doctor filters.'],
                    ['q' => 'A widget shows nothing?', 'a' => 'There is no data for the period the widget covers. This usually means no completed visits or paid payments in that window.'],
                ],
            ],
        ],

        'daily_business_report' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'A one-page intelligence report for a single day. Shows different views depending on your role: doctors see their own patients and commission, reception sees their collections, and owners/managers see the full revenue and profit picture.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick a date in the filter at the top — the report refreshes automatically.',
                    'Read the financial section for revenue, costs, and net profit (owners) or your own earnings (doctors).',
                    'Use the booking charts to see where bookings came from (source) and their statuses.',
                    'Use the payments breakdown to see what was collected and by which method.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'I do not see financials?', 'a' => 'Doctors and reception staff only see their own slice. Full P&L is visible to admins, clinic managers, and partner owners.'],
                    ['q' => 'Why does net profit subtract a fixed amount for staff?', 'a' => 'A flat daily overhead estimate (default 200 KD) is subtracted as a proxy for fixed staff/utilities cost. It is an estimate, not actual payroll.'],
                    ['q' => 'My doctor earnings look wrong?', 'a' => 'Doctor matching is by name. If your user name does not exactly match the Doctor record, ask an admin to relink your user.'],
                ],
            ],
        ],

        'daily_closing_report' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'The end-of-day closing snapshot for a clinic branch. Use this at the end of every shift to see what was booked, completed, collected, and how much each doctor earned for the day. Designed to be printed or saved as PDF.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick the Closing Date (defaults to today).',
                    'Optionally select one or more branches — leave blank for all branches.',
                    'The report rebuilds automatically when filters change.',
                    'Click Refresh Data if you want to re-run the report without changing filters.',
                    'Click Print PDF to print or save a copy for the cashbox.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Which timezone is used?', 'a' => 'The clinic timezone shown next to the filters (Asia/Kuwait by default). "Today" means the local calendar day.'],
                    ['q' => 'My branch is not in the list?', 'a' => 'You can only pick branches you have access to. Ask an admin to grant access if needed.'],
                    ['q' => 'How is this different from Daily Reconciliation?', 'a' => 'Daily Closing is the full end-of-day report (bookings, visits, payments, doctor cuts). Daily Reconciliation focuses only on cash/KNET/link collections per collector.'],
                ],
            ],
        ],

        'daily_reconciliation_report' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'A shift reconciliation tool for reception and management. Shows every paid payment for the selected day, broken down by payment method and by collector, so you can match cash in the drawer and KNET/link totals against the system.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick the Report Date — defaults to today.',
                    'Optionally pick a single Branch — leave blank to include every branch you have access to.',
                    'Compare the "By Method" totals (Cash / KNET / Link) with your terminal slips and physical cash.',
                    'Use the "By Collector" totals to confirm what each receptionist took in during their shift.',
                    'Only payments with status "paid" are included — pending or refunded payments are excluded.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'A payment is missing?', 'a' => 'Only payments marked paid on the selected date show up. Check the Visit and confirm the payment was confirmed and not left pending.'],
                    ['q' => 'Who is "System (Online)"?', 'a' => 'Payments collected through the online gateway (no staff involved) — they have no human collector.'],
                    ['q' => 'I am a doctor — why do I only see some payments?', 'a' => 'Non-admin users with a doctor profile only see payments tied to their visits. Admins and super-admins see everything within the branches selected.'],
                ],
            ],
        ],

        'reservations_dashboard' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'A visual booking funnel — shows how bookings flow from creation through confirmation, check-in, and completion. Useful for spotting drop-offs (e.g. lots of pending bookings that never get confirmed).',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Read each step of the funnel from top to bottom — wider bars mean more bookings at that stage.',
                    'Hover or tap a step to see the exact count.',
                    'Use this to spot bottlenecks: a big drop between Confirmed and Checked-in usually means a no-show problem.',
                    'Refresh the page to recalculate the numbers.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Which date range does this cover?', 'a' => 'The widget uses its own built-in default window (typically recent bookings). For date-filtered reports, use Clinic Reports or Daily Closing.'],
                    ['q' => 'Funnel is empty?', 'a' => 'No bookings exist yet, or none fall inside the widget\'s built-in window. Try creating a booking and reloading.'],
                ],
            ],
        ],

        'list_bookings' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Bookings are the front-desk schedule of appointments. Each booking represents a patient slot with a doctor at a branch on a specific date and time. The default view shows confirmed bookings; use filters to see other statuses.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New booking" to schedule an appointment for an existing or walk-in patient.',
                    'Use the "When" filter (Today / Tomorrow / This week / Past) to narrow the schedule.',
                    'Use the "Time of day" filter for morning, afternoon, or evening slots.',
                    'Toggle the "No-show" filter to see confirmed bookings whose end time has passed without check-in.',
                    'Use the Check-in action on a confirmed booking to start the visit — this moves the patient into the doctor queue.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why don\'t I see all bookings by default?', 'a' => 'The list defaults to confirmed bookings only. Open the Status filter and select other states (pending, cancelled, completed, etc.) to see them.'],
                    ['q' => 'What happens after I check a patient in?', 'a' => 'A Visit is created and the patient appears on the Room Console (Waiting Patients) for the doctor to accept.'],
                    ['q' => 'How do I mark a patient as no-show?', 'a' => 'Enable the No-show filter to find expired confirmed bookings, then use the per-row action to flag them.'],
                ],
            ],
        ],

        'list_visits' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Visits are the clinical encounter record — one row per patient consultation. Visits are created automatically when reception checks a booking in, and track status from awaiting doctor through to payment and closure.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Open a visit to see items charged, packages applied, payments, and follow-up plans.',
                    'Filter by status (awaiting doctor, in progress, awaiting stock, awaiting payment, completed) to find what you need.',
                    'Reception collects payment from the visit page once the doctor finishes treatment.',
                    'Admins can create a visit manually; normally visits come from booking check-in.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why can\'t I see the "New visit" button?', 'a' => 'Only admins can create visits manually. Reception should check in the booking instead, which creates the visit with the correct linkage.'],
                    ['q' => 'Where do I add items or packages?', 'a' => 'Doctors add charges from the Room Console (Waiting Patients) while the patient is in the room. The visit page shows the resulting lines.'],
                    ['q' => 'What is "awaiting payment"?', 'a' => 'The doctor finished treatment but reception has not collected payment yet. Open the visit to record payments.'],
                ],
            ],
        ],

        'list_patients' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'The patient master list. Each row is one person registered with the clinic, holding contact details, civil ID, and history. Patients are linked to bookings and visits.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New patient" to register a walk-in or new caller.',
                    'Search by name or phone to find an existing patient before creating a duplicate.',
                    'Open a patient to see their booking and visit history.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'A patient called but I can\'t find them — should I create a new record?', 'a' => 'First try searching by phone (with and without country code) and by partial name. Only create a new record if you are sure they are not in the system.'],
                    ['q' => 'Can I merge duplicate patients?', 'a' => 'Not from this screen. Ask an admin to handle merges so booking and visit history stay intact.'],
                ],
            ],
        ],

        'list_doctors' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'The list of doctors who can be assigned to bookings and visits. Each doctor record holds their profile, default consultation fee, and links to a user account for system login.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New doctor" to onboard a clinician — link them to a user account so they can log in to the Room Console.',
                    'Edit a doctor to update their consultation fee or specialty.',
                    'Set up a Doctor Compensation Profile separately if you need to track how the doctor is paid per visit.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Where do I set how a doctor is paid?', 'a' => 'On the Doctor Compensation Profile screen. That is where percentage splits and per-service rules live.'],
                    ['q' => 'Why can\'t a doctor log in?', 'a' => 'They must be linked to a User account (with the doctor role) and that user must have a password set.'],
                ],
            ],
        ],

        'list_clinic_items' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'The clinic catalog: every item that can be charged on a visit or held in stock — consumables, medications, supplies, and chargeable services. Items can be stockable (tracked in inventory) or service-only.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New" to add an item with its base unit, price in KWD, and whether it is stockable.',
                    'Mark an item inactive to hide it from booking and visit forms without deleting history.',
                    'Stockable items show up in Clinic Stocks and feed the Room Console stock request flow.',
                    'Group related items into a Package for one-click application during a visit.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'What is the difference between stockable and service items?', 'a' => 'Stockable items have a quantity tracked in Clinic Stocks and decrement on each visit. Service items are charge-only and never affect inventory.'],
                    ['q' => 'I changed the price — does it apply to past visits?', 'a' => 'No. Visit lines snapshot the price at the time they were added, so historical charges stay accurate.'],
                ],
            ],
        ],

        'list_clinic_item_stocks' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Current stock on hand for each clinic item, broken down by branch. This is the read-out you check when wondering "do we have enough of X?" Stock levels are adjusted automatically by visit issues and stock movements.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Filter by branch to see only the stock at one location.',
                    'Use the Stock Movements page to record receipts, transfers, or adjustments — do not edit on-hand quantities directly here.',
                    'A new row is created automatically the first time an item exists at a branch.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'My on-hand looks wrong — how do I correct it?', 'a' => 'Record a Stock Movement of type "adjustment" with the correct delta. That keeps a proper audit trail instead of silently editing the balance.'],
                    ['q' => 'Why does an item show 0 when I just received some?', 'a' => 'The receipt must be posted via Stock Movements. Movements update on-hand here automatically.'],
                ],
            ],
        ],

        'list_clinic_packages' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Packages bundle multiple clinic items into a single offering — for example a "Facial Cleanup" that includes several consumables and a service charge. Doctors apply packages to visits in one click instead of adding each item.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New" to define a package: set its name, price, and the items it contains with their quantities.',
                    'Mark a package inactive to retire it without breaking history on past visits.',
                    'Restrict a package to one branch by setting its branch field; leave blank for all branches.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'If I change the items in a package, do past visits update?', 'a' => 'No. Visit lines snapshot what the package contained when it was applied.'],
                    ['q' => 'Can a package contain non-stockable services?', 'a' => 'Yes. Mix any clinic items — stockable consumables and pure services can coexist in one package.'],
                ],
            ],
        ],

        'list_clinic_stock_movements' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'The audit log of every change to clinic stock — receipts from suppliers, transfers between branches, adjustments, and issues to visits. Every row is permanent; on-hand balances on the Clinic Stocks page are derived from these movements.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New" to record a manual movement: receipt, transfer, or adjustment.',
                    'Filter by branch, item, or movement type to investigate a discrepancy.',
                    'Visit-driven issues appear here automatically when doctors apply items in the Room Console.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Can I edit or delete a movement?', 'a' => 'Avoid it. The correct fix is to post a counter-movement so the audit trail stays intact.'],
                    ['q' => 'I received supplier stock — how do I record it?', 'a' => 'Create a movement of type "receipt" at the receiving branch with the item and quantity. The Clinic Stocks balance updates automatically.'],
                ],
            ],
        ],

        'list_visit_stock_requests' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Pending and historical stock requests from visits. When a doctor needs an item that is not available at the branch, a stock request is raised here and the visit is paused as "awaiting stock" until pharmacy or store fulfills it.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Open a request to see which items are needed and for which visit/patient.',
                    'Filter by status to see pending requests that still need action.',
                    'Fulfill a request once stock is available; the visit automatically resumes on the Room Console.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Who creates stock requests?', 'a' => 'Doctors and nurses from the Room Console (Waiting Patients) when needed inventory is short. They are not created from this page.'],
                    ['q' => 'What happens after I fulfill?', 'a' => 'The system issues stock against the visit, the patient moves back to awaiting doctor (or in progress), and the movement is logged in Stock Movements.'],
                ],
            ],
        ],

        'list_doctor_compensation_ledgers' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Per-visit accruals of what each doctor has earned. Rows are posted automatically when a visit is charged, based on the rules in the doctor\'s Compensation Profile. Amounts are in KWD.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Filter by doctor and date range to compute payout for a given period.',
                    'Open a row to see which visit and which charges produced the accrual.',
                    'If a row looks wrong, fix the underlying Compensation Profile or visit charges and re-run; do not edit ledger rows directly.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'A doctor changed rate mid-month — which rate applies?', 'a' => 'Accruals snapshot the profile at the time the visit was charged, so old visits keep the old rate and new ones use the new rate.'],
                    ['q' => 'I see no ledger rows for a doctor.', 'a' => 'Confirm they have a Compensation Profile attached and that their completed visits have charges. Missing profile means no accruals.'],
                ],
            ],
        ],

        'list_doctor_compensation_profiles' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Configuration of how each doctor is paid: percentage of revenue, flat fee, or per-service rules. Profiles drive the per-visit rows you see in the Doctor Compensation Ledger.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Create one profile per doctor with their pay rules.',
                    'Edit a profile to change the rate; future visits will accrue at the new rate.',
                    'Use the ledger page to verify the rules produce the expected payouts.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Will editing a profile change past payouts?', 'a' => 'No. Past ledger rows are snapshots and stay at the old rate. Only new visits use the updated profile.'],
                    ['q' => 'A doctor has no profile — what happens?', 'a' => 'No compensation accruals are posted for their visits. Create a profile before they start seeing patients.'],
                ],
            ],
        ],

        'list_branches' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'The list of clinic branches (locations). Each branch has its own address, schedule, stock, and bookings. Most other records — bookings, visits, stock, packages — can be scoped to a branch.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New" to add a branch with its address and contact details.',
                    'Use the language switcher (top-right) to edit Arabic and English copy in their own tabs.',
                    'Set up Schedule Rules and Clinic Closures for each branch so the booking engine knows when it is open.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Can I deactivate a branch instead of deleting?', 'a' => 'Yes. Toggle its active flag so it stops appearing in booking forms but its history stays intact.'],
                    ['q' => 'Why does the branch not show on the public site?', 'a' => 'Check that it is active, geocoded (lat/lng set), and has at least one schedule rule covering current dates.'],
                ],
            ],
        ],

        'list_branch_availability_rules' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'The weekly working hours for each branch and doctor. Schedule rules tell the booking engine which time slots are bookable on which days. Without a rule covering a date and time, no booking can be made.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New" to add a rule: pick a branch (and optionally a doctor), the weekday, start and end times.',
                    'Add multiple rules per branch to cover split shifts (morning + evening).',
                    'Combine with Clinic Closures to block specific dates (holidays, maintenance).',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Patients can\'t find an available slot on the website.', 'a' => 'Make sure a rule exists for the branch and weekday in question, and that no Clinic Closure overlaps the desired date.'],
                    ['q' => 'A doctor only works some days — how do I model that?', 'a' => 'Create doctor-scoped rules just for those weekdays. The booking engine merges branch and doctor rules.'],
                ],
            ],
        ],

        'list_branch_blackouts' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Closures and blocked time windows for a branch — public holidays, maintenance days, training events, or any time the branch should not accept bookings. Closures override schedule rules.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click "New" to add a closure: pick the branch, start, end, and optional reason.',
                    'Set a closure for a single day to block one date, or a range for vacations.',
                    'Closures appear immediately to the booking engine — no need to disable schedule rules.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'A patient already has a booking on a day I want to close.', 'a' => 'Existing bookings are not auto-cancelled. You must reach out and reschedule them manually before adding the closure.'],
                    ['q' => 'Can I close just one doctor instead of the whole branch?', 'a' => 'No — closures here are branch-wide. To take a single doctor offline, remove or edit their schedule rules.'],
                ],
            ],
        ],

        'list_follow_up_plans' => [
            'what' => [
                'heading' => 'What is this?',
                'body' => 'Follow-up plans scheduled after a visit — call-backs, review appointments, treatment continuations. Each plan links to the originating visit and patient and has a due date for reception to act on.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Filter by status (pending, done, cancelled) and by due date to see what reception should action today.',
                    'Open a plan to see notes from the doctor and mark it done once the patient is contacted or rebooked.',
                    'Follow-ups are usually created from inside the visit by the doctor — not from this list.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'A follow-up is overdue — what should I do?', 'a' => 'Open it, call the patient using the linked phone, and either rebook them (creating a new booking) or mark the plan as done/cancelled with a note.'],
                    ['q' => 'Where do I add a new follow-up?', 'a' => 'Inside the visit page for the patient, so it stays linked to the encounter. This list is mainly for reviewing and closing out plans.'],
                ],
            ],
        ],

        'list_journal_entries' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Journal Entries are the core record of double-entry accounting. Every JE has at least one debit and one credit line, and total debits must equal total credits. Most JEs are auto-created by the system when a visit is paid, an expense is posted, stock moves, or a doctor is paid — you rarely create them manually.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Filter by Status to find Drafts (editable) vs Posted (locked) vs Reversed entries.',
                    'Filter by Date range to inspect a specific period.',
                    'Click a row to view its lines (account, debit, credit, branch, doctor/patient tags).',
                    'For Draft entries you can edit, post, or delete from the row actions.',
                    'For Posted entries you can only Reverse — this auto-creates an offsetting JE.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why can I not edit or delete a posted entry?', 'a' => 'Posted entries are immutable by accounting rule. To fix a mistake, use Reverse (which creates an offsetting JE) then post a new corrected entry.'],
                    ['q' => 'Why does posting fail with "period closed"?', 'a' => 'The entry date falls inside a closed Accounting Period. Either reopen the period or change the entry date to fall in an open period.'],
                    ['q' => 'Where do these entries come from?', 'a' => 'Most are auto-posted by the system: visit payments, expenses, stock issues, doctor payouts. The Source column shows the originating record (e.g. VisitPayment #123).'],
                    ['q' => 'My debit and credit totals don\'t match — can I save?', 'a' => 'No. The form blocks save until the entry is balanced. Add or fix a line so debits equal credits.'],
                ],
            ],
        ],

        'list_chart_of_accounts' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'The Chart of Accounts is the master list of every account the clinic uses for bookkeeping. Each account has a code, a type (Asset, Liability, Equity, Revenue, Expense, etc.) and a normal-balance direction. Every line of every journal entry is posted against one of these accounts.',
            ],
            'numbering' => [
                'heading' => 'Account numbering convention',
                'items' => [
                    '1000s — Assets (cash, bank, receivables, inventory, fixed assets)',
                    '2000s — Liabilities (AP, doctor payable, accruals)',
                    '3000s — Equity (owner capital, retained earnings)',
                    '4000s — Revenue (consultations, treatments, packages)',
                    '5000s — Expenses (operating costs)',
                    '6000s — Other expenses (depreciation, interest, etc.)',
                    'Per-branch sub-accounts use the dash convention: e.g. 1010-3 = Cash for branch 3.',
                ],
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Filter by Type or Active/System to narrow the list.',
                    'The Balance column shows current natural-direction balance for each account.',
                    'Click an account to drill into its postings via the General Ledger.',
                    'Use Create to add a new account; set a parent if it is a sub-account.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why can I not delete a "System" account?', 'a' => 'System accounts are required by auto-posting (e.g. AR, AP, Cash). Deleting them would break visit payments, expenses, and stock postings. You can deactivate non-system accounts that are no longer used.'],
                    ['q' => 'Should I create a new account for every branch?', 'a' => 'No — for cash/AR/inventory, use the existing parent (e.g. 1010 Cash) and add branch-scoped sub-accounts (1010-3). The system resolves the correct sub-account automatically based on the visit\'s branch.'],
                    ['q' => 'What is the difference between debit-normal and credit-normal?', 'a' => 'Assets and Expenses naturally carry a debit balance; Liabilities, Equity, and Revenue naturally carry a credit balance. The Balance column already shows the value in the correct direction.'],
                ],
            ],
        ],

        'list_accounting_periods' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Accounting Periods are the monthly buckets that govern when journal entries can be posted. A period is either Open (entries with a date inside it can be posted) or Closed (locked — no new posts, no edits). Closing the month finalises that month\'s books.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Filter by Year to find a specific month\'s period.',
                    'Use Close Period when month-end is finalised — this locks the month against further posts and (optionally) creates a closing JE.',
                    'Use Reopen Period if you need to amend something — it unlocks the month again.',
                    'View Closing JE shows the entry that was generated when the period was closed.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'I tried to post a JE but got "period closed". What now?', 'a' => 'Either change the entry date to fall in an open period, or reopen the closed period briefly, post, then close it again.'],
                    ['q' => 'When should I close a period?', 'a' => 'Once you have finished reviewing the month — all visit payments reconciled, expenses posted, bank reconciliation done. Closing prevents anyone (including the system) from back-dating activity into that month.'],
                    ['q' => 'What happens if a period does not exist yet?', 'a' => 'The system auto-creates monthly periods as needed when an entry is posted. You usually do not need to create them manually.'],
                    ['q' => 'Does closing affect reports?', 'a' => 'No. Trial Balance, P&L, Balance Sheet and General Ledger all read posted entries regardless of period status. Closing is purely a write-lock.'],
                ],
            ],
        ],

        'list_bank_reconciliations' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Bank Reconciliation matches the lines on your bank statement against the journal-entry lines hitting the bank/cash account in the books. The goal is to confirm the bank says the same thing the books say, and to surface any timing differences (uncleared deposits, outstanding cheques, missing entries).',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Create a reconciliation: pick the bank or cash account, the period, and enter the statement opening + closing balance.',
                    'Inside the reconciliation, upload or enter the statement lines.',
                    'Match each statement line to its corresponding GL line — the Diff total should be zero when fully reconciled.',
                    'Same-side matching only: a deposit on the statement matches a debit on the cash GL line; a withdrawal matches a credit.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why can I not match the same GL line twice?', 'a' => 'Each GL line can match at most one bank-statement line (and vice versa). If your statement double-counts something, leave the extras unmatched and adjust later.'],
                    ['q' => 'The Diff is not zero — what next?', 'a' => 'Look at unmatched lines on either side. Common causes: a deposit-in-transit (book entry exists, statement not yet showing it), or a bank fee that hasn\'t been recorded in the books yet — post a JE for the fee, then come back.'],
                    ['q' => 'What is "Book" vs statement?', 'a' => 'Book Opening/Closing = the cash account balance per your journal entries for that period. Statement Opening/Closing = what the bank says. After reconciliation they should agree.'],
                ],
            ],
        ],

        'list_expenses' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Expenses are vendor bills and operating costs (rent, utilities, supplies, doctor commissions paid outside payroll, etc.). Each expense, once posted, creates a journal entry that debits the chosen Expense account and credits either Cash (if paid immediately) or Accounts Payable (if it accrues for later payment).',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click Create to log a new expense — pick the vendor, date, branch, expense account, amount in KWD.',
                    'Set "Paid From" (a cash/bank account) to record an immediate payment — system books Dr Expense / Cr Cash.',
                    'Leave "Paid From" empty to accrue to Accounts Payable — system books Dr Expense / Cr AP, and the bill stays open until paid.',
                    'Attach the receipt file for audit trail.',
                    'Use Post on a draft to lock the entry; use Void to cancel a posted expense (reverses the JE).',
                    'Filter by Vendor, Account, Status, or Date range.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'What is the difference between Draft and Posted?', 'a' => 'Draft = saved but no journal entry yet. Posted = JE created and balances updated. Only Posted expenses appear in the P&L and Trial Balance.'],
                    ['q' => 'How do I record a payment for an unpaid (AP) expense?', 'a' => 'For now, create a manual journal entry: Dr Accounts Payable / Cr Cash. A dedicated "Pay Bill" workflow is on the roadmap.'],
                    ['q' => 'Why is my expense rejected with "period closed"?', 'a' => 'The expense date falls in a closed accounting period. Change the date or reopen the period.'],
                    ['q' => 'Should I attach a receipt?', 'a' => 'Yes when possible. Auditors and the clinic owner will thank you. The file is stored against the expense record.'],
                ],
            ],
        ],

        'list_vendors' => [
            'what' => [
                'heading' => 'What is this page?',
                'body' => 'Vendors are the suppliers, landlords, service providers, and contractors you record Expenses against. Maintaining a vendor list lets you group spend by supplier, set default accounts, and produce per-vendor reports.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Click Create to add a new vendor — enter name, contact details, and (optional) tax/commercial registration number.',
                    'Set a Default Expense Account to pre-fill that account whenever you log an expense for this vendor.',
                    'Set a Default Payable Account if this vendor uses a non-standard AP sub-account.',
                    'Deactivate (uncheck Active) for vendors no longer used — they stay in history but disappear from create-expense dropdowns.',
                    'Filter by Active to hide deactivated vendors.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Can I delete a vendor?', 'a' => 'Only if they have no expenses against them. Otherwise, deactivate — this preserves history while hiding them from new-expense pickers.'],
                    ['q' => 'Do I need to fill in tax / commercial reg. number?', 'a' => 'Optional, but recommended for compliance and so vendor invoices line up cleanly with your books.'],
                    ['q' => 'What is the Default Expense Account for?', 'a' => 'When you create an Expense and pick this vendor, the system pre-selects this account so you don\'t have to choose every time. You can still override it per expense.'],
                ],
            ],
        ],

        'balance_sheet_report' => [
            'what' => [
                'heading' => 'What is this report?',
                'body' => 'The Balance Sheet is a point-in-time snapshot of what the clinic owns (Assets), what it owes (Liabilities), and the residual Equity. The fundamental identity Assets = Liabilities + Equity must always hold — if it doesn\'t, the books are out of balance.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick an "As of date" — the report shows balances of every account up to and including that date.',
                    'Equity includes a "Retained Earnings (current period)" line: this is net income from Jan 1 of the same year through the as-of date. We don\'t auto-close revenue/expense to retained earnings until year-end, so this line bridges the gap so the balance sheet still balances.',
                    'The verification panel at the bottom shows the delta between Assets and Liabilities + Equity. Anything other than zero indicates a problem.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why is the balance sheet "out of balance"?', 'a' => 'Almost always one of: an unposted closing JE, a manual JE with debit ≠ credit (should be impossible — flag IT), or a recently-modified account type. Run the Trial Balance for the same date — if Trial Balance is balanced but Balance Sheet isn\'t, an account type is mis-classified.'],
                    ['q' => 'What are "Contra Assets"?', 'a' => 'Accounts that reduce a related asset, e.g. Accumulated Depreciation reducing Fixed Assets. They show as a negative line under Assets.'],
                    ['q' => 'Why is this different from the Trial Balance?', 'a' => 'Trial Balance lists every account by debit/credit. Balance Sheet groups them into Assets vs Liabilities+Equity and only includes balance-sheet accounts (not revenue/expense). The two reports are different views of the same posted data.'],
                ],
            ],
        ],

        'cash_flow_report' => [
            'what' => [
                'heading' => 'What is this report?',
                'body' => 'The Cash Flow Statement explains how cash moved during a period. It starts from Net Income, adjusts for non-cash items and working-capital changes (Operating), subtracts fixed-asset purchases (Investing), and adds owner contributions (Financing). The final number must equal the actual change in cash and bank accounts over the period.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick a From/To range — typically a month or quarter.',
                    'Read each section: Operating (day-to-day cash impact), Investing (asset purchases), Financing (owner capital).',
                    'The verification row at the bottom compares Cash@start + Net Change vs Cash@end. They must match — if not, a posting bypassed normal flow.',
                    'Cash@start and Cash@end are the sums of all cash-on-hand (1010*) and bank (1020*) account balances at those dates.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why does Net Income not equal Net Change in Cash?', 'a' => 'Net Income is accrual-based — it includes revenue you billed but haven\'t collected, and expenses you incurred but haven\'t paid. The working-capital adjustments (ΔAR, ΔAP, ΔInventory) convert accrual to cash.'],
                    ['q' => 'What does "ΔAR" mean?', 'a' => 'Change in Accounts Receivable. Up = customers owe more than before (we didn\'t collect what we billed → reduces cash). Down = customers paid down → adds cash.'],
                    ['q' => 'Why is the report "not reconciling"?', 'a' => 'A non-cash account was posted in a way that affects cash without going through the categorised accounts (rare). Check Trial Balance for the period and look for unusual postings to 1010*/1020*.'],
                ],
            ],
        ],

        'general_ledger' => [
            'what' => [
                'heading' => 'What is this report?',
                'body' => 'The General Ledger is the per-account drilldown: for one chosen account and a date range, it lists every posted journal-entry line that hit that account, in chronological order, with a running balance in the account\'s natural direction.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick an Account (required) — type to search by code or name.',
                    'Set From/To dates — defaults to the current month.',
                    'Optional: filter by Branch to see only postings tagged to a specific clinic.',
                    'Each row links to its parent Journal Entry — click the JE code to view the full balanced entry.',
                    'The Opening Balance row shows the balance carried over from before the From date; Closing Balance = Opening + Period Activity.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'Why does the running balance go negative for a Cash account?', 'a' => 'Either the date range cuts mid-history (Opening Balance is below the activity), or there is a posting error — a cash account should never go truly negative. Extend the From date back to start-of-life to see the full picture.'],
                    ['q' => 'I don\'t see a payment I just took. Why?', 'a' => 'Only Posted journal entries appear here. Drafts are excluded. Check the JE list and post the entry if needed.'],
                    ['q' => 'What is the difference between Debit and Credit columns?', 'a' => 'Raw amounts from the journal-entry line. The running balance applies the sign correctly based on whether the account is debit-normal (assets/expenses) or credit-normal (liabilities/equity/revenue).'],
                    ['q' => 'Can I export this?', 'a' => 'Not yet from this page; use the source JE list for export. Direct GL export is on the roadmap.'],
                ],
            ],
        ],

        'profit_and_loss_report' => [
            'what' => [
                'heading' => 'What is this report?',
                'body' => 'The Profit & Loss (Income Statement) shows how much the clinic earned and spent over a period. Revenue minus Contra-Revenue minus Cost of Goods Sold minus Operating Expenses equals Net Profit. Sub-accounts are rolled up under their parent so you see the same hierarchy as the Chart of Accounts.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick a From/To range — defaults to the current month.',
                    'Read top-down: Revenue → Net Revenue (after refunds/discounts) → Gross Profit (after COGS) → Net Profit (after operating expenses).',
                    'Click into an account in the General Ledger page to drill into specific transactions for that line.',
                    'All values are in KWD with 3-decimal precision.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'What is "Contra-Revenue"?', 'a' => 'Reductions to revenue — typically refunds and discounts. They sit under revenue with the opposite sign so the Net Revenue line is what actually hit the books.'],
                    ['q' => 'What is "COGS" for a clinic?', 'a' => 'Cost of Goods Sold — primarily the cost of consumables, drugs, and items dispensed during visits. When stock is issued to a visit the system books Dr COGS / Cr Inventory.'],
                    ['q' => 'Why does my P&L look low this month?', 'a' => 'Only Posted journal entries are counted. Check that all visit payments and expenses are posted (not Draft). Also check the date range — defaults to the current month-to-date, not the full month.'],
                    ['q' => 'Should this match the Balance Sheet?', 'a' => 'Net Profit from P&L flows into Retained Earnings on the Balance Sheet for the same period. They should agree when both are run for the same end date.'],
                ],
            ],
        ],

        'trial_balance' => [
            'what' => [
                'heading' => 'What is this report?',
                'body' => 'The Trial Balance is the foundational integrity check of double-entry bookkeeping. For a chosen date range it lists every account that had activity, the total debits and total credits posted to it, and the net balance. Across all accounts, total debits MUST equal total credits — that is the proof that the books are mathematically consistent.',
            ],
            'how' => [
                'heading' => 'How to use it',
                'items' => [
                    'Pick a From/To range — defaults to the current month.',
                    'Each row shows one account: code, name, type, raw debit/credit sums for the period, and the net in its natural direction.',
                    'Look at the bottom totals: total Debit must equal total Credit. The "Balanced" indicator confirms this.',
                    'Use this report as your starting point whenever a downstream report (P&L, Balance Sheet) looks wrong.',
                ],
            ],
            'faq' => [
                'heading' => 'Common questions',
                'items' => [
                    ['q' => 'What does it mean if Trial Balance is NOT balanced?', 'a' => 'Almost impossible in normal use — every JE is validated to have debit=credit before posting. If it ever happens, escalate to IT: it indicates either a corrupted journal entry or a direct database edit that bypassed validation.'],
                    ['q' => 'Why does an account I expected to see not appear?', 'a' => 'Trial Balance only lists accounts with activity in the selected period. Accounts with zero movement in the range are omitted. For a complete balance view, use the Balance Sheet instead.'],
                    ['q' => 'What\'s the difference between Trial Balance and Balance Sheet?', 'a' => 'Trial Balance shows period-activity debits vs credits for ALL account types (including revenue/expense). Balance Sheet only shows balance-sheet accounts (assets, liabilities, equity) as point-in-time net balances.'],
                ],
            ],
        ],

    ],
];
