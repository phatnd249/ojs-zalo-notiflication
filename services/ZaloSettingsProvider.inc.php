<?php

/** Đọc cấu hình gateway theo context mà không phụ thuộc request hiện tại. */
class ZaloSettingsProvider
{
    private const PLUGIN_NAME = 'zalonotificationplugin';
    private const DEFAULT_BOT_ID = '7ddde23b-a9c2-40c1-bc89-7a308ba00466';

    public static function getForContext(int $contextId): array
    {
        $pluginSettingsDao = \DAORegistry::getDAO('PluginSettingsDAO');
        $botId = trim((string) $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'botId'));
        $apiKey = trim((string) $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'apiKey'));
        $groupsJson = $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'recipientGroups');
        $groups = $groupsJson ? json_decode((string) $groupsJson, true) : [];

        if ($botId === '') {
            $botId = self::DEFAULT_BOT_ID;
        }
        if (!is_array($groups)) {
            $groups = [];
        }

        return [
            'botId' => $botId,
            'apiKey' => $apiKey,
            'recipientGroups' => $groups,
        ];
    }

    public static function getCurrentContextId(): int
    {
        try {
            $request = \Application::get()->getRequest();
            $context = $request ? $request->getContext() : null;
            return $context ? (int) $context->getId() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
