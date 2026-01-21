<?php

namespace App\Services;

class WhatsAppMessageBuilder
{
    public static function text(string $to, string $body): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ];
    }

    public static function template(string $to, string $templateName, string $lang = 'en'): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $lang],
                // add components if you need variables
            ],
        ];
    }

    public static function quickReplies(string $to, string $body, array $buttons): array
    {
        // $buttons: [ ['id'=>'size:2','title'=>'2'], ... ]
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => array_map(function ($b) {
                        return ['type' => 'reply', 'reply' => ['id' => $b['id'], 'title' => $b['title']]];
                    }, $buttons),
                ],
            ],
        ];
    }

    public static function list(string $to, string $body, string $buttonLabel, array $sections): array
    {
        // $sections: [['title'=>'Branches','rows'=>[['id'=>'branch:1','title'=>'Salmiya','description'=>'...'], ...]]]
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $body],
                'action' => [
                    'button' => $buttonLabel,
                    'sections' => $sections,
                ],
            ],
        ];
    }
}
