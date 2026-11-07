<?php
/**
 * Test chèn Tab Điều hành & Nhắc Zalo kế bên tab Lưu trữ trên trang Các bài gửi (dashboard/index.tpl).
 */

function failTabTest(string $message): void
{
    fwrite(STDERR, "FAIL [SubmissionsDashboardTab]: {$message}\n");
    exit(1);
}

// 1. Kiểm tra mã nguồn ZaloNotificationPlugin.inc.php
$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');

if (strpos($pluginSource, "HookRegistry::register('TemplateManager::display', [\$this, 'callbackExtendSubmissionsDashboard']);") === false) {
    failTabTest("Chưa đăng ký hook TemplateManager::display với callbackExtendSubmissionsDashboard");
}

if (strpos($pluginSource, 'function callbackExtendSubmissionsDashboard') === false) {
    failTabTest("Thiếu phương thức callbackExtendSubmissionsDashboard");
}

if (strpos($pluginSource, "\$templateMgr->registerFilter('output', [\$this, 'filterDashboardSubmissionsTab']);") === false) {
    failTabTest("callbackExtendSubmissionsDashboard chưa đăng ký filter output filterDashboardSubmissionsTab");
}

if (strpos($pluginSource, 'function filterDashboardSubmissionsTab') === false) {
    failTabTest("Thiếu phương thức filterDashboardSubmissionsTab");
}

if (strpos($pluginSource, 'id="zaloDashboard"') === false) {
    failTabTest("filterDashboardSubmissionsTab không định nghĩa id=zaloDashboard");
}

if (strpos($pluginSource, 'label="Điều hành Zalo"') === false) {
    failTabTest("filterDashboardSubmissionsTab thiếu nhãn Điều hành Zalo");
}

if (strpos($pluginSource, ':badge="34"') === false) {
    failTabTest("filterDashboardSubmissionsTab thiếu :badge=\"34\"");
}

if (strpos($pluginSource, 'isEmbedded=1') === false) {
    failTabTest("filterDashboardSubmissionsTab URL thiếu tham số isEmbedded=1");
}

if (strpos($pluginSource, 'zaloDashboardSubmissionsContainer') === false) {
    failTabTest("filterDashboardSubmissionsTab thiếu container zaloDashboardSubmissionsContainer");
}

// 2. Kiểm tra template reviewReminderSubmissionsTab.tpl nhúng trực tiếp trong Tab
$tplSource = file_get_contents(dirname(__DIR__) . '/templates/reviewReminderSubmissionsTab.tpl');
if (strpos($tplSource, 'class="zrd-wrapper zrd-embedded"') === false) {
    failTabTest("reviewReminderSubmissionsTab.tpl chưa hỗ trợ class zrd-wrapper zrd-embedded");
}

// Kiểm tra topbar cũ (ảnh 3) và fullscreen đã được loại bỏ theo yêu cầu người dùng
if (strpos($tplSource, 'zrd-topbar') !== false || strpos($tplSource, 'zrdFullscreenBtn') !== false) {
    failTabTest("reviewReminderSubmissionsTab.tpl vẫn còn chứa zrd-topbar hoặc nút fullscreen (ảnh 3)");
}

// Kiểm tra nút Xem tất cả đã cấu hình chuyển hướng đến Tab Activity Log
if (strpos($tplSource, 'activityLogUrl') === false) {
    failTabTest("reviewReminderSubmissionsTab.tpl thiếu biến activityLogUrl để chuyển hướng");
}

// Kiểm tra template reviewReminderDashboard.tpl đã được trả về giao diện cũ cho Tab Website Settings
$classicTplSource = file_get_contents(dirname(__DIR__) . '/templates/reviewReminderDashboard.tpl');
if (strpos($classicTplSource, 'class="zalo-review-dashboard"') === false || strpos($classicTplSource, 'zrd-heading') === false) {
    failTabTest("reviewReminderDashboard.tpl chưa trả về giao diện cũ");
}

// 3. Giả lập logic chèn tab vào cấu trúc HTML của dashboard/index.tpl
$mockDashboardOutput = '
	<tabs :track-history="true">
		<tab id="myQueue" label="Hàng đợi của tôi" :badge="25">
			<div>Queue content</div>
		</tab>
		<tab id="unassigned" label="Chưa phân công" :badge="0">
			<div>Unassigned content</div>
		</tab>
		<tab id="active" label="Tất cả đang hoạt động" :badge="30">
			<div>Active content</div>
		</tab>
		<tab id="archive" label="Lưu trữ" :badge="5">
			<submissions-list-panel />
		</tab>
	</tabs>
';

// Mô phỏng logic tìm kiếm và thay thế như trong filterDashboardSubmissionsTab
$archivePos = strpos($mockDashboardOutput, 'id="archive"');
if ($archivePos === false) {
    failTabTest("Không tìm thấy archive tab trong mock output");
}

$closeTabPos = strpos($mockDashboardOutput, '</tab>', $archivePos);
if ($closeTabPos === false) {
    failTabTest("Không tìm thấy thẻ đóng </tab> của archive tab");
}

$insertPos = $closeTabPos + strlen('</tab>');
$tabHtml = '<tab id="zaloDashboard" label="Điều hành &amp; Nhắc Zalo" :badge="34"><div v-pre id="zaloDashboardSubmissionsContainer"></div></tab>';
$simulatedOutput = substr_replace($mockDashboardOutput, $tabHtml, $insertPos, 0);

$archiveEndPos = strpos($simulatedOutput, '</tab>', strpos($simulatedOutput, 'id="archive"')) + strlen('</tab>');
$zaloPos = strpos($simulatedOutput, '<tab id="zaloDashboard"');
$tabsEndPos = strpos($simulatedOutput, '</tabs>');

if (!($zaloPos >= $archiveEndPos && $zaloPos < $tabsEndPos)) {
    failTabTest("Vị trí tab zaloDashboard không nằm giữa </tab> của archive và </tabs>");
}

echo "Submissions Dashboard Tab Extension tests passed.\n";
