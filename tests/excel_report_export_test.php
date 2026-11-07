<?php

/**
 * Kiểm thử tính năng xuất báo cáo Excel (.xls) đẹp, chuẩn hóa tên file và định dạng dữ liệu.
 */

function failExcelTest(string $message): void
{
    fwrite(STDERR, "FAIL [ExcelReportTest]: {$message}\n");
    exit(1);
}

require_once dirname(__DIR__) . '/services/reports/ZaloExcelReportService.inc.php';

// 1. Kiểm tra xuất Báo cáo Tiến độ Phản biện
$progressExcel = ZaloExcelReportService::generateProgressReportExcel(0);

if (empty($progressExcel)) {
    failExcelTest('generateProgressReportExcel trả về nội dung rỗng.');
}

if (strpos($progressExcel, '<html xmlns:o="urn:schemas-microsoft-com:office:office"') === false) {
    failExcelTest('Thiếu khai báo XML Excel namespace.');
}

if (strpos($progressExcel, 'BÁO CÁO CHI TIẾT TIẾN ĐỘ PHẢN BIỆN BÀI BÁO') === false) {
    failExcelTest('Thiếu tiêu đề BÁO CÁO CHI TIẾT TIẾN ĐỘ PHẢN BIỆN BÀI BÁO.');
}

if (strpos($progressExcel, 'TỔNG SỐ BÀI / LƯỢT PHẢN BIỆN') === false) {
    failExcelTest('Thiếu thẻ KPI tổng số bài / lượt phản biện.');
}

if (strpos($progressExcel, 'QUÁ HẠN PHẢN BIỆN') === false) {
    failExcelTest('Thiếu thẻ KPI quá hạn phản biện.');
}

if (strpos($progressExcel, "mso-number-format: '\\@'") === false) {
    failExcelTest('Thiếu định dạng mso-number-format cho cột số điện thoại / mã bài.');
}

if (strpos($progressExcel, '#1e3a8a') === false) {
    failExcelTest('Thiếu mã màu nhận diện xanh đậm #1e3a8a trong tiêu đề/header bảng.');
}

if (strpos($progressExcel, 'TỔNG BIÊN TẬP / THƯ KÝ TÒA SOẠN') === false) {
    failExcelTest('Thiếu khối chữ ký phê duyệt cuối báo cáo.');
}

// 2. Kiểm tra xuất Bảng Xếp loại Phản biện viên
$ratingExcel = ZaloExcelReportService::generateReviewerRatingReportExcel(0);

if (empty($ratingExcel)) {
    failExcelTest('generateReviewerRatingReportExcel trả về nội dung rỗng.');
}

if (strpos($ratingExcel, 'BẢNG ĐÁNH GIÁ &amp; XẾP LOẠI PHẢN BIỆN VIÊN') === false) {
    failExcelTest('Thiếu tiêu đề BẢNG ĐÁNH GIÁ & XẾP LOẠI PHẢN BIỆN VIÊN.');
}

if (strpos($ratingExcel, 'HẠNG A (XUẤT SẮC)') === false) {
    failExcelTest('Thiếu khối KPI xếp hạng A (Xuất sắc).');
}

if (strpos($ratingExcel, 'rank-a') === false || strpos($ratingExcel, 'rank-b') === false) {
    failExcelTest('Thiếu class định kiểu CSS phân hạng phản biện viên.');
}

// 3. Kiểm tra mã nguồn ZaloReviewReminderController về việc đổi tên file và hỗ trợ xls
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloReviewReminderController.inc.php');

if (strpos($controllerSource, "Bao_cao_tien_do_phan_bien_' . \$dateStr . '.xls'") === false) {
    failExcelTest('Controller chưa đổi tên file tiến độ sang Bao_cao_tien_do_phan_bien_YYYYMMDD.xls.');
}

if (strpos($controllerSource, "Bang_xep_loai_phan_bien_vien_' . \$dateStr . '.xls'") === false) {
    failExcelTest('Controller chưa đổi tên file xếp loại sang Bang_xep_loai_phan_bien_vien_YYYYMMDD.xls.');
}

if (strpos($controllerSource, 'ZaloExcelReportService::generateProgressReportExcel') === false) {
    failExcelTest('Controller chưa tích hợp ZaloExcelReportService::generateProgressReportExcel.');
}

// 4. Kiểm tra mã nguồn Template reviewReminderSubmissionsTab.tpl
$tabSource = file_get_contents(dirname(__DIR__) . '/templates/reviewReminderSubmissionsTab.tpl');

if (strpos($tabSource, 'type=1_xls') === false) {
    failExcelTest('Template chưa cập nhật URL type=1_xls.');
}

if (strpos($tabSource, 'fa-file-excel-o') === false) {
    failExcelTest('Template chưa cập nhật icon Excel.');
}

echo "Excel Report Export tests passed.\n";
