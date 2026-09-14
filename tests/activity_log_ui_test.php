<?php

$tpl = file_get_contents(dirname(__DIR__) . '/templates/activityLog.tpl');

function assertContainsSnippet(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message} (Snippet missing: {$needle})\n");
        exit(1);
    }
}

// 1. Kiểm tra cấu trúc phân vùng chính
assertContainsSnippet($tpl, 'zalo-log-container', 'Container chính phải tồn tại');
assertContainsSnippet($tpl, 'zalo-log-header', 'Header phải tồn tại');
assertContainsSnippet($tpl, 'zsw-container', 'Khung KPI ribbon phải tồn tại');
assertContainsSnippet($tpl, 'zsw-ribbon', 'Thanh KPI ribbon ngang phải tồn tại');
assertContainsSnippet($tpl, 'zsw-drawer', 'Ngăn kéo chi tiết có thể thu gọn phải tồn tại');
assertContainsSnippet($tpl, 'zalo-log-toolbar', 'Toolbar điều khiển và tìm kiếm phải tồn tại');
assertContainsSnippet($tpl, 'zalo-log-table-wrap', 'Vùng bao bọc bảng log phải tồn tại');
assertContainsSnippet($tpl, 'zalo-log-pagination', 'Thanh phân trang phải tồn tại');

// 2. Kiểm tra các KPI pills trên thanh ngang
assertContainsSnippet($tpl, 'id="kpiSent"', 'KPI Đã gửi phải có ID tương ứng');
assertContainsSnippet($tpl, 'id="kpiDelivered"', 'KPI Thành công phải có ID tương ứng');
assertContainsSnippet($tpl, 'id="kpiFailed"', 'KPI Thất bại phải có ID tương ứng');
assertContainsSnippet($tpl, 'id="kpiOverdue"', 'KPI Quá hạn phải có ID tương ứng');
assertContainsSnippet($tpl, 'id="kpiDueSoon"', 'KPI Sắp hạn phải có ID tương ứng');

// 3. Kiểm tra nút toggle ngăn kéo và bộ chọn ngày
assertContainsSnippet($tpl, 'id="zswToggleBtn"', 'Nút bật tắt ngăn kéo chi tiết phải tồn tại');
assertContainsSnippet($tpl, 'id="zswDatePicker"', 'Bộ chọn ngày phải tồn tại');
assertContainsSnippet($tpl, 'id="zswBtnFilterByDate"', 'Nút lọc bảng theo ngày phải tồn tại');

// 4. Kiểm tra các trường tìm kiếm và thao tác
assertContainsSnippet($tpl, 'id="zaloFilterSelect"', 'Dropdown chọn loại log phải tồn tại');
assertContainsSnippet($tpl, 'id="zaloSearchInput"', 'Input tìm kiếm phải tồn tại');
assertContainsSnippet($tpl, 'id="zaloBtnRefresh"', 'Nút làm mới phải tồn tại');
assertContainsSnippet($tpl, 'id="zaloBtnExport"', 'Nút xuất file log phải tồn tại');

// 5. Kiểm tra JavaScript logic: Phân trang & Quản lý trạng thái
assertContainsSnippet($tpl, 'var logPageSize = 20;', 'Kích thước trang phải là 20');
assertContainsSnippet($tpl, 'localStorage.getItem(\'zalo_activity_drawer_open\')', 'Phải lưu trạng thái mở/thu gọn drawer');
assertContainsSnippet($tpl, 'applyDrawerState()', 'Phải áp dụng trạng thái drawer khi khởi tạo');
// 6. Kiểm tra các tab trong Website Settings
$websiteTabs = file_get_contents(dirname(__DIR__) . '/templates/websiteSettingsTab.tpl');
assertContainsSnippet($websiteTabs, 'id="zaloApiSettings"', 'Tab Cấu hình Zalo API phải tồn tại');
assertContainsSnippet($websiteTabs, 'url=$zaloApiSettingsUrl', 'URL cấu hình API phải được truyền vào tab');
assertContainsSnippet($websiteTabs, 'id="zaloGroupSettings"', 'Tab Cài đặt Nhóm Zalo phải tồn tại');
assertContainsSnippet($websiteTabs, 'url=$zaloGroupSettingsUrl', 'URL cài đặt nhóm phải được truyền vào tab');
assertContainsSnippet($websiteTabs, 'id="zaloMessageTemplates"', 'Tab Mẫu tin Zalo phải tồn tại');
assertContainsSnippet($websiteTabs, 'url=$zaloTemplatesUrl', 'URL mẫu tin nhắn phải được truyền vào tab');
assertContainsSnippet($websiteTabs, 'id="zaloActivityLog"', 'Tab Zalo Activity Log phải tồn tại');
assertContainsSnippet($websiteTabs, 'id="zaloReviewReminder"', 'Tab Nhắc phản biện phải tồn tại');
assertContainsSnippet($websiteTabs, 'id="zaloOutboxRetry"', 'Tab Hàng đợi & Thử lại phải tồn tại');

$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
assertContainsSnippet($pluginSource, "'zaloApiSettingsUrl' => \$zaloApiSettingsUrl", 'Plugin phải gán zaloApiSettingsUrl');
assertContainsSnippet($pluginSource, "'zaloGroupSettingsUrl' => \$zaloGroupSettingsUrl", 'Plugin phải gán zaloGroupSettingsUrl');
assertContainsSnippet($pluginSource, "'zaloTemplatesUrl' => \$zaloTemplatesUrl", 'Plugin phải gán zaloTemplatesUrl');

echo "Activity log and Website Settings UI verification passed.\n";
