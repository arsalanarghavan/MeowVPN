<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;

trait AdminHandlerTrait
{
    public function matchesNavText(string $text, SvpUser $user): bool
    {
        $label = $this->navLabel($user);

        return $label !== '' && trim($text) === $label;
    }

    public function handleNav(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        if (! $this->matchesNavText($text, $user)) {
            return false;
        }
        $this->send($ctx, $chatId, $this->sectionIntro($user));

        return true;
    }

    protected function navLabel(SvpUser $user): string
    {
        return '';
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return $this->texts->getForUser('msg.admin.section', $user, static::class);
    }
}
