<?php

namespace App\Services;

use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\Action;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\Body;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\Footer;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\Header;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\HeaderType;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\Row;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\Section;
use Netflie\WhatsAppCloudApi\Message\InteractiveMessage\InteractiveMessage as IM;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

class WhatsAppSender
{
    public function __construct(protected WhatsAppApiService $api) {}

    public function sendTextMessage(string $to, string $text): void
    {
        $this->api->sendTextMessage($to, $text);
    }

    public function sendButtons(string $to, string $question, array $buttons): void
    {
        // buttons: [['id'=>'confirm','label'=>'Confirm'], ...] (max 3)
        $btns = array_map(fn($b)=>['id'=>$b['id'],'label'=>$b['label']], $buttons);
        $this->api->sendButtonMessage($to, $question, $btns);
    }

    public function sendListMessage(string $to, string $title, string $body, array $rows): void
    {
        // rows: [['id'=>'123','label'=>'Branch A','desc'=>'Salmiya'], ...]
        $sectionRows = array_map(fn($r)=> new Row($r['id'], $r['label'], $r['desc'] ?? ''), $rows);
        $section = new Section('Options', $sectionRows);

        $action = new Action(list_title: $title, sections: [$section]);
        $im = new IM($action, new Body($body), footer: new Footer('Select one'), header: new Header(HeaderType::TEXT, $title));
        $this->api->client()->sendInteractiveMessage($to, $im); // expose client() or add sendInteractiveMessage to WhatsAppApiService
    }

    public function sendTemplate(string $to, string $templateName, string $language, ?Component $components = null): void
    {
        $this->api->sendTemplate($to, $templateName, $language, $components);
    }

    public function makeTemplateVars(array $pairs): Component
    {
        $bodyParams = [];
        foreach ($pairs as $v) {
            $bodyParams[] = ['type'=>'text', 'text'=>(string)$v];
        }
        return new Component([], $bodyParams, []);
    }
}
