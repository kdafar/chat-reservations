<?php

return [

    'journal_entry' => [
        // Sections
        'section_entry' => 'القيد',
        'section_lines' => 'البنود',

        // Fields
        'date' => 'التاريخ',
        'branch' => 'الفرع',
        'currency' => 'العملة',
        'narration' => 'البيان',
        'account' => 'الحساب',
        'debit' => 'مدين',
        'credit' => 'دائن',
        'description' => 'الوصف',
        'balance' => 'الرصيد',
        'code' => 'الرمز',
        'source' => 'المصدر',
        'posted_by' => 'رحّله',
        'status' => 'الحالة',

        // Repeater
        'add_line' => 'إضافة بند',

        // Balance placeholder texts
        'balance_balanced' => 'مدين: :debit | دائن: :credit — ✓ متوازن',
        'balance_off' => 'مدين: :debit | دائن: :credit — ⚠ فرق بمقدار :diff',

        // Filters
        'filter_from' => 'من',
        'filter_to' => 'إلى',

        // Actions
        'reverse' => 'عكس',
        'reverse_modal_description' => 'سيتم إنشاء قيد عكسي مقابل. ويتم وضع علامة "معكوس" على القيد الأصلي. لا يمكن التراجع عن هذا الإجراء.',
        'reverse_reason' => 'السبب',
        'post_draft' => 'ترحيل المسودة',
        'post_modal_description' => 'سيتحقق الترحيل من توازن القيد وتجميده. لا يمكن التراجع عن هذا الإجراء.',

        // Notifications
        'entry_reversed' => 'تم عكس القيد',
        'reversal_body' => 'القيد العكسي: :code',
        'entry_posted' => 'تم ترحيل القيد',
        'failed' => 'فشل',
        'cannot_post' => 'تعذر الترحيل',

        // Placeholders
        'placeholder_system' => 'النظام',
        'placeholder_dash' => '—',
    ],

    'chart_of_account' => [
        // Sections
        'section_account' => 'الحساب',

        // Fields
        'code' => 'الرمز',
        'code_helper' => 'رمز رقمي، مثل 1010 أو 4020.',
        'name' => 'الاسم',
        'type' => 'النوع',
        'parent_account' => 'الحساب الأب',
        'branch_optional' => 'الفرع (اختياري)',
        'branch_helper' => 'تخصيص هذا الحساب لفرع محدد (مثلًا "النقدية - الفرع 4").',
        'currency' => 'العملة',
        'is_active' => 'نشط',
        'description' => 'الوصف',
        'branch' => 'الفرع',
        'balance_kwd' => 'الرصيد (د.ك)',
        'system' => 'نظامي',

        // Account types
        'type_asset' => 'أصول',
        'type_liability' => 'خصوم',
        'type_equity' => 'حقوق ملكية',
        'type_revenue' => 'إيرادات',
        'type_cogs' => 'تكلفة المبيعات',
        'type_expense' => 'مصروفات',
        'type_contra_asset' => 'أصل مقابل',
        'type_contra_liability' => 'خصم مقابل',
        'type_contra_revenue' => 'إيراد مقابل',

        // Tooltips
        'system_tooltip' => 'يُستخدم في الترحيل التلقائي؛ لا يمكن حذفه',
        'user_managed_tooltip' => 'يديره المستخدم',

        // Placeholders
        'placeholder_dash' => '—',
    ],

    'accounting_period' => [
        // Sections
        'section_period' => 'الفترة',

        // Fields
        'code' => 'الرمز',
        'status' => 'الحالة',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'closed_at' => 'تاريخ الإغلاق',
        'closed_by' => 'أغلقها',
        'closing_je' => 'قيد الإقفال',
        'notes' => 'ملاحظات',
        'year' => 'السنة',

        // Actions
        'close_period' => 'إغلاق الفترة',
        'close_modal_description' => 'سيؤدي الإغلاق إلى إنشاء قيد محاسبي يصفّر جميع حسابات الإيرادات وتكلفة المبيعات والمصروفات في حساب الأرباح المحتجزة. يجب ترحيل أو حذف المسودات في هذه الفترة أولًا.',
        'reopen_period' => 'إعادة فتح الفترة',
        'reopen_modal_description' => 'ستؤدي إعادة الفتح إلى عكس قيد الإقفال. يمكن ترحيل/تعديل أي قيود مؤرخة في هذه الفترة مرة أخرى.',
        'view_closing_je' => 'عرض قيد الإقفال',

        // Notifications
        'period_closed_title' => 'تم إغلاق الفترة :code',
        'closing_je_body' => 'قيد الإقفال: :code',
        'cannot_close_period' => 'تعذر إغلاق الفترة',
        'period_reopened_title' => 'تم إعادة فتح الفترة :code',
        'cannot_reopen_period' => 'تعذر إعادة فتح الفترة',

        // Placeholders
        'placeholder_dash' => '—',
    ],

    'bank_reconciliation' => [
        // Sections
        'section_reconciliation' => 'التسوية',

        // Fields
        'bank_cash_account' => 'حساب البنك / النقدية',
        'bank_cash_helper' => 'النقدية بالخزينة (1010، 1010-{branch}) أو الحسابات البنكية (1020، 1021، 1022).',
        'period_start' => 'بداية الفترة',
        'period_end' => 'نهاية الفترة',
        'opening_balance_statement' => 'الرصيد الافتتاحي (حسب الكشف)',
        'closing_balance_statement' => 'الرصيد الختامي (حسب الكشف)',
        'notes' => 'ملاحظات',
        'code' => 'الرمز',
        'account' => 'الحساب',
        'period' => 'الفترة',
        'opening' => 'افتتاحي',
        'closing' => 'ختامي',
        'book_opening' => 'افتتاحي دفتري',
        'book_closing' => 'ختامي دفتري',
        'diff' => 'الفرق',
        'matched_total' => 'المرتبط/الإجمالي',

        // Status options
        'status_in_progress' => 'قيد التنفيذ',
        'status_completed' => 'مكتملة',

        // Filters
        'filter_from' => 'من',
        'filter_to' => 'إلى',
        'filter_period_start' => 'بداية الفترة',

        // Actions
        'recompute' => 'إعادة احتساب الأرصدة الدفترية',
        'auto_match' => 'ربط تلقائي',
        'auto_match_modal_description' => 'يقوم بربط بنود الكشف غير المرتبطة تلقائيًا ببنود القيود غير المرتبطة عند تطابق المبالغ والتواريخ (±2 يوم).',
        'mark_complete' => 'إنهاء التسوية',
        'mark_complete_modal_description' => 'تجميد هذه التسوية. لن يكون بالإمكان تعديل بنود الكشف. يمكن للمشرفين إعادة فتحها لاحقًا.',
        'reopen' => 'إعادة فتح',
        'reopen_modal_description' => 'إعادة فتح هذه التسوية المكتملة. ستصبح البنود قابلة للتعديل مرة أخرى.',

        // Notifications
        'book_balances_refreshed' => 'تم تحديث الأرصدة الدفترية',
        'closing_amount_body' => 'الرصيد الختامي: :amount د.ك',
        'failed' => 'فشل',
        'auto_matched_title' => 'تم الربط تلقائيًا لـ :count بند(بنود)',
        'reconciliation_completed' => 'اكتملت التسوية',
        'reconciliation_reopened' => 'تم إعادة فتح التسوية',
    ],

    'expense' => [
        // Sections
        'section_expense' => 'المصروف',
        'section_payment' => 'الدفع',

        // Fields
        'code' => 'الرمز',
        'code_placeholder' => 'يتم إنشاؤه تلقائيًا عند الحفظ',
        'date' => 'التاريخ',
        'vendor' => 'المورد',
        'branch' => 'الفرع',
        'expense_account' => 'حساب المصروف',
        'expense_account_helper' => 'فئة المصروف المدينة (مثلًا 6030 إيجار).',
        'amount_kwd' => 'المبلغ (د.ك)',
        'description' => 'الوصف',
        'paid_from' => 'الدفع من',
        'paid_from_helper' => 'اتركه فارغًا إذا كان مفوترًا على الذمم الدائنة.',
        'reference_invoice' => 'المرجع / رقم الفاتورة',
        'reference' => 'المرجع',
        'receipt' => 'الإيصال',
        'account' => 'الحساب',
        'status' => 'الحالة',
        'on_account' => 'على الحساب',

        // Status options
        'status_draft' => 'مسودة',
        'status_posted' => 'مُرحَّل',
        'status_void' => 'ملغى',

        // Filters
        'filter_from' => 'من',
        'filter_to' => 'إلى',

        // Actions
        'post' => 'ترحيل',
        'post_modal_description' => 'ترحيل هذا المصروف إلى دفتر الأستاذ العام. لا يمكن تعديله بعد ذلك.',
        'void' => 'إلغاء',
        'void_modal_description' => 'يعكس القيد المحاسبي ويضع علامة "ملغى" على هذا المصروف. يتم الحفاظ على سجل التدقيق.',

        // Notifications
        'expense_posted' => 'تم ترحيل المصروف',
        'je_body' => 'القيد: :code',
        'posting_failed' => 'فشل الترحيل',
        'posting_failed_body' => 'تأكد من ضبط جميع الحسابات المطلوبة.',
        'failed' => 'فشل',
        'expense_voided' => 'تم إلغاء المصروف',

        // Placeholders
        'placeholder_dash' => '—',
    ],

    'vendor' => [
        // Sections
        'section_vendor' => 'المورد',
        'section_defaults' => 'الإعدادات الافتراضية',
        'section_defaults_description' => 'الحسابات المقترحة عند إنشاء مصروف لهذا المورد.',
        'section_other' => 'أخرى',

        // Fields
        'name' => 'الاسم',
        'code' => 'الرمز',
        'code_helper' => 'مرجع قصير اختياري (مثلًا LANDLORD-A).',
        'contact_name' => 'اسم جهة الاتصال',
        'contact' => 'جهة الاتصال',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني',
        'tax_number' => 'الرقم الضريبي / السجل التجاري',
        'address' => 'العنوان',
        'default_expense_account' => 'حساب المصروف الافتراضي',
        'default_payable_account' => 'حساب الذمم الدائنة الافتراضي',
        'default_payable_account_helper' => 'يُستخدم عند مفوترة هذا المورد على الحساب (عادةً 2010 الذمم الدائنة).',
        'default_account' => 'الحساب الافتراضي',
        'is_active' => 'نشط',
        'notes' => 'ملاحظات',

        // Filters
        'filter_active' => 'نشط',

        // Placeholders
        'placeholder_dash' => '—',
    ],

];
