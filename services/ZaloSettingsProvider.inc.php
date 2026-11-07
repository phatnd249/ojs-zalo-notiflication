<?php

/** Đọc cấu hình gateway theo context mà không phụ thuộc request hiện tại. */
class ZaloSettingsProvider
{
    private const PLUGIN_NAME = 'zalonotificationplugin';

    public static function getForContext(int $contextId): array
    {
        $pluginSettingsDao = \DAORegistry::getDAO('PluginSettingsDAO');
        $botId = trim((string) $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'botId'));
        $apiKey = trim((string) $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'apiKey'));
        $groupsJson = $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'recipientGroups');
        $groups = $groupsJson ? json_decode((string) $groupsJson, true) : [];

        if (!is_array($groups)) {
            $groups = [];
        }

        $beforeDaysRaw = $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'reviewReminderBeforeDays');
        $beforeDays = ($beforeDaysRaw === null) ? 4 : max(0, min(30, (int) $beforeDaysRaw));
        $onDueDate = $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'reviewReminderOnDueDate');
        $overdueFreq = (int) $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'reviewReminderOverdueFrequency');
        $maxOverdue = (int) $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'reviewReminderMaxOverdueCount');
        $sendReviewer = $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'reviewReminderSendReviewer');
        $sendEditor = $pluginSettingsDao->getSetting($contextId, self::PLUGIN_NAME, 'reviewReminderSendEditor');

        return [
            'botId' => $botId,
            'apiKey' => $apiKey,
            'recipientGroups' => $groups,
            'reviewReminderBeforeDays' => $beforeDays,
            'reviewReminderOnDueDate' => ($onDueDate === null) ? true : (bool) $onDueDate,
            'reviewReminderOverdueFrequency' => ($overdueFreq > 0) ? $overdueFreq : 2,
            'reviewReminderMaxOverdueCount' => ($maxOverdue > 0) ? $maxOverdue : 3,
            'reviewReminderSendReviewer' => ($sendReviewer === null) ? true : (bool) $sendReviewer,
            'reviewReminderSendEditor' => ($sendEditor === null) ? true : (bool) $sendEditor,
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
