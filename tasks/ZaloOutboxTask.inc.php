<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');

/** Gửi các thông báo đã được hook OJS đưa vào outbox. */
class ZaloOutboxTask extends ScheduledTask
{
    /** @var ZaloNotificationPlugin|null */
    private $plugin;

    public function __construct($args = [])
    {
        PluginRegistry::loadCategory('generic', true);
        $this->plugin = PluginRegistry::getPlugin('generic', 'zalonotificationplugin');
        parent::__construct($args);
    }

    public function getName()
    {
        return 'Zalo notification outbox';
    }

    protected function executeActions()
    {
        if (!$this->plugin || !ZaloOutboxRepository::ensureSchema()) {
            $this->addExecutionLogEntry('Zalo plugin hoặc bảng outbox chưa sẵn sàng.', SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
            return false;
        }

        $rows = ZaloOutboxRepository::claimBatch(20);
        $sent = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $outboxId = (int) $row->outbox_id;
            $contextId = (int) $row->context_id;
            $attempts = (int) $row->attempts;
            $eventType = (string) $row->event_type;

            try {
                if (!$this->plugin->getEnabled($contextId)) {
                    ZaloOutboxRepository::markFailed($outboxId, 5, 'Plugin đã bị tắt trước khi gửi');
                    $failed++;
                    continue;
                }

                $settings = ZaloSettingsProvider::getForContext($contextId);
                $recipients = json_decode((string) $row->recipients_json, true);
                if (!is_array($recipients) || empty($recipients)) {
                    ZaloOutboxRepository::markFailed($outboxId, 5, 'Danh sách người nhận không hợp lệ');
                    $failed++;
                    continue;
                }

                $status = ZaloApiClient::sendToPhonesNow(
                    (string) $row->message_text,
                    (string) $settings['botId'],
                    (string) $settings['apiKey'],
                    $recipients
                );

                if ($status === 'Thành công') {
                    ZaloOutboxRepository::markSent($outboxId);
                    $sent++;
                } else {
                    ZaloOutboxRepository::markFailed($outboxId, $attempts, $status);
                    $failed++;
                }

                ActivityLogger::logZaloResult(
                    0,
                    $eventType,
                    $status,
                    'Outbox #' . $outboxId . ', lần thử ' . $attempts,
                    $contextId
                );
            } catch (\Throwable $e) {
                ZaloOutboxRepository::markFailed($outboxId, $attempts, 'Lỗi worker: ' . $e->getMessage());
                ZaloNotificationPlugin::writeSecureDebug(
                    'Outbox #' . $outboxId . ' lỗi worker: ' . $e->getMessage(),
                    'ZaloOutboxTask'
                );
                $failed++;
            }
        }

        $deleted = ZaloOutboxRepository::cleanup();
        $this->addExecutionLogEntry(
            'Đã nhận ' . count($rows) . ' bản ghi; gửi thành công ' . $sent
                . '; chưa gửi ' . $failed . '; dọn ' . $deleted . ' bản ghi cũ.'
        );
        return true;
    }
}
