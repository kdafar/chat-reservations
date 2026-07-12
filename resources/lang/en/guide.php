<?php

/**
 * Short, plain-language blurbs for the System Guide page (Guide/Index.vue).
 * One line per sidebar link, keyed by nav-item id. No jargon — written so a new
 * staff member understands what the page is for in one sentence.
 */
return [
    'items' => [
        // Operations
        'dashboard'        => 'Your daily snapshot — today\'s money, number of visits, no-shows and waiting time, all on one screen.',
        'waiting'          => 'The live queue of patients who have checked in and are waiting to be seen. Start a visit straight from here.',
        'checkin'          => 'Mark a patient as "arrived" when they reach the clinic so the doctor knows they are waiting.',
        'bookings'         => 'The appointment calendar — create, move, confirm or cancel bookings for any doctor.',
        'visits'           => 'The full history of patient visits — search past visits and open any one to see what was done and paid.',
        'doctor-schedule'  => 'See each doctor\'s working hours and how busy their day is, so you can place new appointments wisely.',
        'my-earnings'      => 'For doctors — your own earnings for the day from the visits you handled.',

        // Patients
        'patients'         => 'The patient directory — add new patients and open a patient to see their profile, history and contact details.',
        'patient-files'    => 'Upload and keep documents for a patient: scans, reports, ID copies and consent forms.',
        'follow-up-plans'  => 'Track patients who need to come back, with their next follow-up date and whether it has been booked.',

        // Inpatient
        'inpatient-board'      => 'A visual map of every ward and bed showing which are free and which are occupied right now.',
        'inpatient-admissions' => 'Admit a patient to a bed, follow their stay, and discharge them when ready.',
        'inpatient-wards'      => 'Set up the wards (rooms/sections) of the clinic where patients can be admitted.',
        'inpatient-beds'       => 'Set up the individual beds inside each ward.',
        'inpatient-reports'    => 'Occupancy and stay reports for the inpatient department — how full the beds are over time.',

        // Insurance
        'insurance-insurers'  => 'The list of insurance companies you work with.',
        'insurance-plans'     => 'The specific plans each insurer offers, with what they cover and the patient\'s share of the bill (co-pay).',
        'insurance-policies'  => 'Link a patient to their insurance plan so the system knows what their insurance covers.',
        'insurance-preauth'   => 'Request and record approvals from the insurer before a treatment is done.',
        'insurance-claims'    => 'Send treatment costs to the insurer for payment and track each claim until it is paid.',

        // Laboratory
        'lab-tests'        => 'The catalogue of lab tests the clinic offers, with their prices.',

        // Pharmacy & Stock
        'clinic-items'     => 'The catalogue of everything you sell or use — services, medicines and supplies, with prices.',
        'clinic-stock'     => 'How much of each item is left in stock at each branch.',
        'stock-movements'  => 'The history of every stock change — what came in, what went out and why.',
        'stock-requests'   => 'Requests from a visit to take items out of stock, waiting to be approved.',
        'stock-transfers'  => 'Move stock from one branch (or the central store) to another and track it on the way.',
        'purchase-orders'  => 'Order items from your suppliers, receive them into stock, and record paying the supplier.',
        'clinic-packages'  => 'Bundles of services or items sold together at one price (for example a treatment course).',

        // Discounts & Promotions
        'coupons'          => 'Discount codes a patient can use to get money off their bill.',
        'promotions'       => 'Time-limited offers and price drops you set up for certain services or periods.',

        // HR
        'leaves'           => 'Request your own time off and (for managers) review and approve the team\'s leave.',
        'attendance'       => 'Your check-in / check-out records and (for managers) the whole team\'s attendance.',
        'doctors'          => 'Add and manage doctors — their specialty, branch, room and consultation fee.',
        'users'            => 'Add and manage staff logins and what role each person has.',
        'doctor-comp'      => 'Set how each doctor is paid — fixed amount or a percentage of what they bring in.',
        'doctor-earnings'  => 'A running record of what each doctor has earned from their visits.',

        // Payroll
        'payroll-runs'     => 'Prepare and approve monthly salaries for all staff in one run.',
        'salary-profiles'  => 'Each employee\'s salary make-up — basic pay plus any allowances.',
        'staff-loans'      => 'Record loans or advances given to staff and deduct them automatically from salary.',
        'leave-balances'   => 'How many leave days each employee has earned and used.',
        'settlements'      => 'Calculate an employee\'s final pay and end-of-service payout (gratuity) when they leave.',

        // Accounting
        'accounts'          => 'The list of all accounts the clinic\'s money is tracked in (the chart of accounts).',
        'posting-accounts'  => 'Choose which account each automatic entry (like a payment or sale) is recorded into.',
        'fixed-assets'      => 'Track big purchases like equipment and spread their cost over time (depreciation).',
        'prepaid-schedules' => 'Spread costs you paid up front (like rent or insurance) over the months they cover.',
        'general-ledger'    => 'Every accounting entry in one place — the complete money trail.',
        'journal-entries'   => 'Record or adjust accounting entries by hand when needed.',
        'expenses'          => 'Record what the clinic spends — rent, salaries, supplies and bills.',
        'vendors'           => 'The suppliers and service providers you buy from, and what you owe them.',
        'reconciliation'    => 'Match your records against the bank statement to make sure they agree.',
        'periods'           => 'Open or close accounting months so finished months can\'t be changed by accident.',
        'trial-balance'     => 'A quick check that the books balance — total debits equal total credits.',
        'profit-loss'       => 'Income statement — money earned minus money spent over a period.',
        'balance-sheet'     => 'A snapshot of what the clinic owns and owes at a point in time.',
        'cash-flow'         => 'Where cash came from and where it went over a period.',
        'aging'             => 'Who owes you money (and who you owe), grouped by how overdue it is.',

        // Reports
        'reports'              => 'Ready-made clinic reports — revenue, visits, doctors and more.',
        'daily-closing'        => 'End-of-day summary of all money taken, to close out the cash drawer.',
        'daily-reconciliation' => 'Check that the cash and card totals collected match what was recorded for the day.',
        'executive'            => 'A high-level overview for owners and managers — the key numbers at a glance.',

        // Platform
        'clinics'          => 'Manage the clinics (companies) on the system.',
        'branches'         => 'Manage the branches (locations) of each clinic.',
        'gateways'         => 'Set up online payment accounts so patients can pay by link or card.',
        'roles'            => 'Define roles and choose exactly what each role is allowed to see and do.',
        'settings'         => 'System-wide settings — logo, contact details and feature switches.',
        'activity'         => 'A log of who changed what and when, across the whole system.',

        // WhatsApp (automation)
        'wa-triggers'      => 'Rules that send a WhatsApp message automatically when something happens (like a new booking).',
        'wa-campaigns'     => 'Send a WhatsApp message to many patients at once.',
        'wa-commands'      => 'Keywords a patient can text to get an automatic reply or action.',
        'wa-messages'      => 'The approved WhatsApp message templates you can send.',
        'wa-texts'         => 'The library of message wording used around the app, in both languages.',
        'wa-logs'          => 'A record of every WhatsApp message sent and whether it was delivered.',
        'wa-sessions'      => 'The connected WhatsApp numbers the system sends from.',
        'wa-audience'      => 'Numbers that show how your WhatsApp audience is growing and engaging.',

        // WhatsApp Platform (full inbox)
        'wap-dashboard'    => 'The home screen of the WhatsApp chat tool — your messaging activity at a glance.',
        'wap-inbox'        => 'A shared WhatsApp inbox — read and reply to patient chats like a team mailbox.',
        'wap-templates'    => 'Reusable WhatsApp message templates for the chat tool.',
        'wap-media'        => 'Images and files you can attach to WhatsApp messages.',
        'wap-contacts'     => 'The list of people you can message on WhatsApp.',
        'wap-campaigns'    => 'Plan and send bulk WhatsApp campaigns from the chat tool.',
        'wap-points'       => 'A loyalty-points view tied to WhatsApp engagement.',
        'wap-logs'         => 'The detailed send/delivery log for the WhatsApp chat tool.',
        'wap-sessions'     => 'The WhatsApp numbers connected to the chat tool.',
        'wap-settings'     => 'Settings for the WhatsApp chat tool.',
    ],
];
