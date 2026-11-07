<?php

import('lib.pkp.classes.core.JSONMessage');

/**
 * Controller xử lý hiển thị các tab Cài đặt API, Cài đặt nhóm và Mẫu tin nhắn.
 */
class ZaloSettingsController
{
    /**
     * Modal chứa 3 tab cài đặt chính (API, Nhóm nhận tin, Mẫu tin nhắn).
     */
    public static function handleSettingsModal(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'apiTabUrl' => $plugin->getManageUrl($request, 'apiTab'),
            'settingsTabUrl' => $plugin->getManageUrl($request, 'settingsTab'),
            'templatesTabUrl' => $plugin->getManageUrl($request, 'templatesTab'),
        ]);

        return new JSONMessage(true, $templateMgr->fetch($plugin->getTemplateResource('settingsTabs.tpl')));
    }

    /**
     * Tab Cấu hình API Zalo (Bot ID, API Key, Thời gian lưu log & outbox, Queue-first dispatch).
     */
    public static function handleApiTab(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $contextId = 0;
        $context = $request->getContext();
        if ($context) {
            $contextId = (int) $context->getId();
        }

        $saveUrl = $plugin->getManageUrl($request, 'saveApiSettings');

        $botId = $plugin->getSetting($contextId, 'botId');
        $savedApiKey = trim((string) ($plugin->getSetting($contextId, 'apiKey') ?? ''));
        $logRetentionDays = (int) ($plugin->getSetting($contextId, 'logRetentionDays') ?: 90);
        if ($logRetentionDays <= 0) {
            $logRetentionDays = 90;
        }
        $outboxRetentionDays = (int) ($plugin->getSetting($contextId, 'outboxRetentionDays') ?: 30);
        if ($outboxRetentionDays <= 0) {
            $outboxRetentionDays = 30;
        }
        $queueFirstSetting = $plugin->getSetting($contextId, 'queueFirstMode');
        $queueFirstMode = ($queueFirstSetting === null) ? true : (bool) $queueFirstSetting;

        $beforeDaysRaw = $plugin->getSetting($contextId, 'reviewReminderBeforeDays');
        $reviewReminderBeforeDays = ($beforeDaysRaw === null) ? 4 : max(0, min(30, (int) $beforeDaysRaw));

        $reviewReminderOnDueDate = $plugin->getSetting($contextId, 'reviewReminderOnDueDate');
        $reviewReminderOnDueDate = ($reviewReminderOnDueDate === null) ? true : (bool) $reviewReminderOnDueDate;

        $reviewReminderOverdueFrequency = (int) ($plugin->getSetting($contextId, 'reviewReminderOverdueFrequency') ?: 2);
        if ($reviewReminderOverdueFrequency <= 0) {
            $reviewReminderOverdueFrequency = 2;
        }

        $reviewReminderMaxOverdueCount = (int) ($plugin->getSetting($contextId, 'reviewReminderMaxOverdueCount') ?: 3);
        if ($reviewReminderMaxOverdueCount <= 0) {
            $reviewReminderMaxOverdueCount = 3;
        }

        $reviewReminderSendReviewer = $plugin->getSetting($contextId, 'reviewReminderSendReviewer');
        $reviewReminderSendReviewer = ($reviewReminderSendReviewer === null) ? true : (bool) $reviewReminderSendReviewer;

        $reviewReminderSendEditor = $plugin->getSetting($contextId, 'reviewReminderSendEditor');
        $reviewReminderSendEditor = ($reviewReminderSendEditor === null) ? true : (bool) $reviewReminderSendEditor;

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'botId' => $botId,
            'apiKeyConfigured' => $savedApiKey !== '',
            'logRetentionDays' => $logRetentionDays,
            'outboxRetentionDays' => $outboxRetentionDays,
            'queueFirstMode' => $queueFirstMode,
            'reviewReminderBeforeDays' => $reviewReminderBeforeDays,
            'reviewReminderOnDueDate' => $reviewReminderOnDueDate,
            'reviewReminderOverdueFrequency' => $reviewReminderOverdueFrequency,
            'reviewReminderMaxOverdueCount' => $reviewReminderMaxOverdueCount,
            'reviewReminderSendReviewer' => $reviewReminderSendReviewer,
            'reviewReminderSendEditor' => $reviewReminderSendEditor,
            'saveUrl' => $saveUrl,
        ]);

        return new JSONMessage(true, $templateMgr->fetch($plugin->getTemplateResource('apiSettingsForm.tpl')));
    }

    /**
     * Tab Cài đặt Nhóm nhận tin nhắn Zalo.
     */
    public static function handleSettingsTab(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $contextId = 0;
        $context = $request->getContext();
        if ($context) {
            $contextId = (int) $context->getId();
        }

        $saveUrl = $plugin->getManageUrl($request, 'saveSettings');

        $groupsJson = $plugin->getSetting($contextId, 'recipientGroups');
        $groups = $groupsJson ? json_decode($groupsJson, true) : [];
        if (!is_array($groups)) {
            $groups = [];
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $plugin->getName(),
            'recipientGroupsJson' => json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'saveUrl' => $saveUrl,
        ]);

        return new JSONMessage(true, $templateMgr->fetch($plugin->getTemplateResource('settingsForm.tpl')));
    }

    /**
     * Tab Cấu hình Mẫu tin nhắn Zalo cho từng vai trò và sự kiện.
     */
    public static function handleTemplatesTab(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $contextId = 0;
        $context = $request->getContext();
        if ($context) {
            $contextId = (int) $context->getId();
        }

        $saveUrl = $plugin->getManageUrl($request, 'saveTemplates');

        $templates = $plugin->getMessageTemplatesForContext($contextId);
        $defaultTemplates = $plugin->getDefaultTemplates();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $plugin->getName(),
            'templatesJson' => json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'defaultTemplatesJson' => json_encode($defaultTemplates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'saveUrl' => $saveUrl,
        ]);

        return new JSONMessage(true, $templateMgr->fetch($plugin->getTemplateResource('notificationTemplatesForm.tpl')));
    }

    public static function handleSaveApiSettings(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $contextId = 0;
        $context = $request->getContext();
        if ($context) {
            $contextId = (int) $context->getId();
        }

        $botId = trim($request->getUserVar('botId') ?? '');
        $apiKey = trim($request->getUserVar('apiKey') ?? '');
        $clearApiKey = (bool) $request->getUserVar('clearApiKey');
        $logRetentionDays = max(1, min(730, (int) ($request->getUserVar('logRetentionDays') ?: 90)));
        $outboxRetentionDays = max(1, min(365, (int) ($request->getUserVar('outboxRetentionDays') ?: 30)));
        $queueFirstMode = (bool) $request->getUserVar('queueFirstMode');

        $plugin->updateSetting($contextId, 'botId', $botId);
        if ($clearApiKey) {
            $plugin->updateSetting($contextId, 'apiKey', '');
        } elseif ($apiKey !== '') {
            $plugin->updateSetting($contextId, 'apiKey', $apiKey);
        }
        $plugin->updateSetting($contextId, 'logRetentionDays', $logRetentionDays);
        $plugin->updateSetting($contextId, 'outboxRetentionDays', $outboxRetentionDays);
        $plugin->updateSetting($contextId, 'queueFirstMode', $queueFirstMode);

        $beforeDaysRaw = $request->getUserVar('reviewReminderBeforeDays');
        $reviewReminderBeforeDays = ($beforeDaysRaw === null || $beforeDaysRaw === '') ? 4 : max(0, min(30, (int) $beforeDaysRaw));
        $reviewReminderOnDueDate = (bool) $request->getUserVar('reviewReminderOnDueDate');
        $reviewReminderOverdueFrequency = max(1, min(30, (int) ($request->getUserVar('reviewReminderOverdueFrequency') ?: 2)));
        $reviewReminderMaxOverdueCount = max(1, min(20, (int) ($request->getUserVar('reviewReminderMaxOverdueCount') ?: 3)));
        $reviewReminderSendReviewer = (bool) $request->getUserVar('reviewReminderSendReviewer');
        $reviewReminderSendEditor = (bool) $request->getUserVar('reviewReminderSendEditor');

        $plugin->updateSetting($contextId, 'reviewReminderBeforeDays', $reviewReminderBeforeDays);
        $plugin->updateSetting($contextId, 'reviewReminderOnDueDate', $reviewReminderOnDueDate);
        $plugin->updateSetting($contextId, 'reviewReminderOverdueFrequency', $reviewReminderOverdueFrequency);
        $plugin->updateSetting($contextId, 'reviewReminderMaxOverdueCount', $reviewReminderMaxOverdueCount);
        $plugin->updateSetting($contextId, 'reviewReminderSendReviewer', $reviewReminderSendReviewer);
        $plugin->updateSetting($contextId, 'reviewReminderSendEditor', $reviewReminderSendEditor);

        $plugin->writeDebug(
            'saveApiSettings: Đã lưu API Settings (queueFirst=' . ($queueFirstMode ? 'true' : 'false')
            . ", logRetention={$logRetentionDays}d, outboxRetention={$outboxRetentionDays}d"
            . ", overdueFreq={$reviewReminderOverdueFrequency}d, maxOverdue={$reviewReminderMaxOverdueCount})."
        );

        return new JSONMessage(true);
    }

    public static function handleSaveSettings(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $contextId = 0;
        $context = $request->getContext();
        if ($context) {
            $contextId = (int) $context->getId();
        }

        $groupsJsonRaw = $request->getUserVar('recipientGroupsJson') ?? '[]';
        $groups = json_decode($groupsJsonRaw, true);
        if (!is_array($groups)) {
            $groups = [];
        }

        $allowedEvents = [
            'SUBMISSION',
            'DECISION',
            'PUBLISH',
            'UNPUBLISH',
            'REVIEW_REQUEST',
            'REVIEW_RESPONSE',
            'REVIEW_REMINDER',
            'REVIEW_COMPLETED',
        ];

        $cleanGroups = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $name = trim($group['name'] ?? '');

            $rawPhones = [];
            if (isset($group['phones'])) {
                $rawPhones = is_array($group['phones'])
                    ? $group['phones']
                    : preg_split('/[,\r\n;]+/', (string) $group['phones']);
            }
            $rawPhonesList = array_values(array_unique(array_filter(array_map('trim', $rawPhones), function ($phone) {
                return $phone !== '';
            })));
            $phones = [];
            foreach ($rawPhonesList as $phone) {
                $normalized = ZaloApiClient::normalizePhoneNumber($phone);
                if (!empty($normalized)) {
                    $phones[] = $normalized;
                }
            }
            $phones = array_values(array_unique($phones));

            $rawEvents = isset($group['events']) && is_array($group['events'])
                ? $group['events']
                : [];
            $events = array_values(array_unique(array_filter($rawEvents, function ($event) use ($allowedEvents) {
                return in_array($event, $allowedEvents, true);
            })));

            if (!empty($name) || !empty($phones)) {
                $cleanGroups[] = [
                    'name' => $name,
                    'phones' => $phones,
                    'events' => $events,
                ];
            }
        }

        $plugin->updateSetting($contextId, 'recipientGroups', json_encode($cleanGroups, JSON_UNESCAPED_UNICODE));
        $plugin->writeDebug('saveSettings: Đã lưu groups=' . count($cleanGroups));

        return new JSONMessage(true);
    }

    public static function handleSaveTemplates(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $contextId = 0;
        $context = $request->getContext();
        if ($context) {
            $contextId = (int) $context->getId();
        }

        $templatesJsonRaw = $request->getUserVar('templatesJson') ?? '{}';
        $templates = json_decode($templatesJsonRaw, true);
        if (!is_array($templates)) {
            $templates = [];
        }

        $allowedRoles = ['editor', 'reviewer', 'author'];
        $allowedEvents = ['submission', 'decision', 'initial_decline', 'review_started', 'publish', 'unpublish', 'reminder', 'review_request', 'review_response', 'review_completed', 'author_revision', 'editor_assignment'];
        $cleanTemplates = [];

        foreach ($allowedRoles as $role) {
            $cleanTemplates[$role] = [];
            $roleTemplates = isset($templates[$role]) && is_array($templates[$role]) ? $templates[$role] : [];
            foreach ($allowedEvents as $event) {
                if (isset($roleTemplates[$event])) {
                    $cleanTemplates[$role][$event] = (string) $roleTemplates[$event];
                }
            }
        }

        $plugin->updateSetting($contextId, 'messageTemplates', json_encode($cleanTemplates));
        $plugin->writeDebug('saveTemplates: Đã lưu mẫu thông báo mới');

        return new JSONMessage(true);
    }
}
