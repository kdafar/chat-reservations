<?php

namespace Database\Seeders;

use App\Models\WhatsappTrigger;
use Illuminate\Database\Seeder;

class WhatsappTriggerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear the table first
        WhatsappTrigger::query()->delete();

        // Define all the triggers
        $triggers = [
            // 1. Welcome Message
            [
                'type' => 'welcome',
                'keyword' => null,
                'response_message_en' => "Welcome to BARDEFRES! 🇰🇼\nWe blend authentic, high-end Japanese cuisine with a modern, casual experience.\n\nYou can type 'menu', 'about', or 'location'. To make a reservation, please type 'book'.",
                'response_message_ar' => "أهلاً بك في بارفريز! 🇰🇼\nنحن نمزج بين المطبخ الياباني الأصيل والراقي والأجواء العصرية المريحة.\n\nيمكنك كتابة 'القائمة'، 'عنا'، أو 'الموقع'. لبدء الحجز، يرجى كتابة 'حجز'.",
                'is_active' => true,
            ],

            // 2. Fallback Message
            [
                'type' => 'fallback',
                'keyword' => null,
                'response_message_en' => "Sorry, I didn't quite understand that. I can help with 'menu', 'about', 'location', or 'book'.",
                'response_message_ar' => "عفواً، لم أفهم ذلك. يمكنني المساعدة بـ 'القائمة'، 'عنا'، 'الموقع'، أو 'حجز'.",
                'is_active' => true,
            ],

            // 3. Finale Message (After Booking)
            [
                'type' => 'finale',
                'keyword' => null,
                'response_message_en' => 'Your table is confirmed! We look forward to seeing you at BARDEFRES. You will receive a confirmation message shortly.',
                'response_message_ar' => 'تم تأكيد حجزك! نتطلع لرؤيتك في بارفريز. ستصلك رسالة تأكيد بعد قليل.',
                'is_active' => true,
            ],

            // 4. Keyword: About / Philosophy
            [
                'type' => 'keyword',
                'keyword' => 'about', // You can also add 'philosophy' by duplicating this block
                'response_message_en' => "Our Philosophy: Authentic Craftsmanship, Casual Setting.\n\nBARDEFRES was established to bridge the gap between high-end Japanese cuisine and the modern, casual dining experience in Kuwait. Our philosophy is rooted in authenticity—using classic techniques and superior ingredients—while providing an atmosphere that is contemporary, relaxed, and welcoming.",
                'response_message_ar' => "فلسفتنا: حرفية أصيلة، أجواء مريحة.\n\nتأسس بارفريز لسد الفجوة بين المطبخ الياباني الفاخر وتجربة الطعام العصرية في الكويت. فلسفتنا متجذرة في الأصالة—باستخدام التقنيات الكلاسيكية والمكونات الفائقة في جو معاصر ومريح ومرحب.",
                'is_active' => true,
            ],

            // 5. Keyword: Menu / Food
            [
                'type' => 'keyword',
                'keyword' => 'menu', // You can also add 'food'
                'response_message_en' => "Our menu is a deliberate balance of tradition and creativity, inspired by the Japanese 'Washoku' tradition (seasonality, harmony, and umami).\n\nPopular items include:\n• Seared Tuna with Truffle Salt\n• Spicy Tuna Roll\n• Sustainable Seafood Mini Bowl",
                'response_message_ar' => "قائمتنا توازن بين التقاليد والإبداع، مستوحاة من 'واشوكو' اليابانية (الموسمية، التناغم، والأومامي).\n\nمن أطباقنا المميزة:\n• تونة مشوية بملح الكمأة\n• سبايسي تونة رول\n• ميني بول المأكولات البحرية المستدامة",
                'is_active' => true,
            ],

            // 6. Keyword: Principles / Washoku
            [
                'type' => 'keyword',
                'keyword' => 'principles', // You can also add 'washoku'
                'response_message_en' => "Our menu draws deep inspiration from Washoku, the renowned Japanese culinary tradition. This philosophy emphasizes:\n\n1. *Seasonality:* Serving ingredients at their absolute peak of flavor.\n2. *Harmony & Balance:* Appealing to all five senses with textural diversity.\n3. *Umami:* Leveraging natural savory flavors from dashi and soy sauce.",
                'response_message_ar' => "قائمتنا مستوحاة بعمق من 'واشوكو'، تقليد الطهي الياباني الشهير. تؤكد هذه الفلسفة على:\n\n١. *الموسمية:* تقديم المكونات في ذروة نكهتها.\n٢. *التناغم والتوازن:* جذب جميع الحواس الخمس بتنوع القوام.\n٣. *الأومامي:* الاستفادة من النكهات اللذيذة الطبيعية من الداشي وصلصة الصويا.",
                'is_active' => true,
            ],

            // 7. Keyword: Sustainable
            [
                'type' => 'keyword',
                'keyword' => 'sustainable', // You can also add 'sustainability'
                'response_message_en' => "We are committed to sustainable practices. Our specialized 'Sustainable Seafood Mini Bowl' utilizes off-cuts of high-quality Tuna, Salmon, Shrimp, and Crab to minimize waste while offering great flavor.",
                'response_message_ar' => "نحن ملتزمون بالممارسات المستدامة. 'ميني بول المأكولات البحرية المستدامة' الخاص بنا يستخدم قطع عالية الجودة من التونة، السلمون، الروبيان، والسلطعون لتقليل الهدر وتقديم نكهة رائعة.",
                'is_active' => true,
            ],
        ];

        // Create each trigger
        foreach ($triggers as $trigger) {
            WhatsappTrigger::create($trigger);
        }
    }
}
