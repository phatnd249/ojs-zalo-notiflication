{**
 * plugins/generic/zaloNotification/templates/websiteSettingsTab.tpl
 *
 * Các tab Zalo nhúng vào trang Website Settings.
 *}
<tab id="zaloActivityLog" label="Zalo Activity Log">
    {load_url_in_div id="zaloActivityLogContainer" url=$zaloLogUrl}
</tab>

<tab id="zaloReviewReminder" label="Nhắc phản biện">
    {load_url_in_div id="zaloReviewReminderContainer" url=$zaloReviewDashboardUrl}
</tab>
