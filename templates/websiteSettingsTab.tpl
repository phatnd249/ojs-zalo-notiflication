{**
 * plugins/generic/zaloNotification/templates/websiteSettingsTab.tpl
 *
 * Các tab Zalo nhúng vào trang Website Settings.
 *}
<tab id="zaloApiSettings" label="Cấu hình Zalo API">
    {load_url_in_div id="zaloApiSettingsContainer" url=$zaloApiSettingsUrl}
</tab>

<tab id="zaloGroupSettings" label="Cài đặt Nhóm Zalo">
    {load_url_in_div id="zaloGroupSettingsContainer" url=$zaloGroupSettingsUrl}
</tab>

<tab id="zaloMessageTemplates" label="Mẫu tin Zalo">
    {load_url_in_div id="zaloMessageTemplatesContainer" url=$zaloTemplatesUrl}
</tab>

<tab id="zaloActivityLog" label="Zalo Activity Log">
    {load_url_in_div id="zaloActivityLogContainer" url=$zaloLogUrl}
</tab>

<tab id="zaloReviewReminder" label="Nhắc phản biện">
    {load_url_in_div id="zaloReviewReminderContainer" url=$zaloReviewDashboardUrl}
</tab>

<tab id="zaloOutboxRetry" label="Hàng đợi & Thử lại">
    {load_url_in_div id="zaloOutboxContainer" url=$zaloOutboxDashboardUrl}
</tab>
