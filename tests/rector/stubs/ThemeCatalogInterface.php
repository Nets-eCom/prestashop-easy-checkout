<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\MailTemplate;

if (!interface_exists(ThemeCatalogInterface::class)) {
    interface ThemeCatalogInterface
    {
        public const LIST_MAIL_THEMES_HOOK = 'actionListMailThemes';

        public function listThemes();

        public function getByName($theme);
    }
}
