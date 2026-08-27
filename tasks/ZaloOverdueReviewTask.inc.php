<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');

/** Quét các lượt phản biện quá hạn theo từng tạp chí đang bật plugin. */
class ZaloOverdueReviewTask extends ScheduledTask
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
        return 'Zalo overdue review reminders';
    }

    protected function executeActions()
    {
        if (!$this->plugin) {
            $this->addExecutionLogEntry(
                'Zalo plugin chưa sẵn sàng.',
                SCHEDULED_TASK_MESSAGE_TYPE_WARNING
            );
            return false;
        }

        StageChangeHandler::setPlugin($this->plugin);
        $journals = \DAORegistry::getDAO('JournalDAO')->getAll(true);
        $scanned = 0;
        $disabled = 0;

        while ($journal = $journals->next()) {
            $contextId = (int) $journal->getId();
            if ($contextId <= 0 || !$this->plugin->getEnabled($contextId)) {
                $disabled++;
                continue;
            }

            StageChangeHandler::checkOverdueReviewDeadlines($this->plugin, $contextId);
            $scanned++;
        }

        $this->addExecutionLogEntry(
            "Đã quét {$scanned} tạp chí đang bật Zalo; bỏ qua {$disabled} tạp chí."
        );
        return true;
    }
}

