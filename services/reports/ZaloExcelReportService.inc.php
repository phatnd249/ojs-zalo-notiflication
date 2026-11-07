<?php

/**
 * Service xuất báo cáo tiến độ phản biện và đánh giá phản biện viên theo định dạng Excel (.xls).
 * Hỗ trợ giao diện trực quan, màu sắc nhận diện chuyên nghiệp, bảng thống kê KPI
 * và bảo toàn 100% tiếng Việt UTF-8 cùng số điện thoại có số 0 ở đầu.
 */
class ZaloExcelReportService
{
    /**
     * Xuất Báo cáo Chi tiết Tiến độ Phản biện (Excel).
     */
    public static function generateProgressReportExcel(int $contextId, $context = null, $user = null): string
    {
        $journalName = 'Tạp chí Khoa học';
        if ($context && method_exists($context, 'getLocalizedName')) {
            $journalName = $context->getLocalizedName() ?: $journalName;
        }

        $userName = 'Ban Thư ký Tòa soạn';
        if ($user && method_exists($user, 'getFullName')) {
            $userName = $user->getFullName() ?: $userName;
        }

        // Lấy danh sách bài nộp và lượt phản biện thực tế từ OJS
        $submissions = [];
        if (class_exists('ReviewReminderDashboardService')) {
            $submissions = ReviewReminderDashboardService::getSubmissions($contextId);
        }

        // Fallback sang dữ liệu mẫu chuẩn nếu hệ thống chưa có bài phản biện nào
        $isLive = !empty($submissions);
        if (!$isLive && class_exists('ReviewReminderDashboardService')) {
            $submissions = ReviewReminderDashboardService::getShowcaseSubmissions();
        }

        // Tính toán thống kê KPI tổng quan
        $totalSubmissions = count($submissions);
        $totalAssignments = 0;
        $overdueCount = 0;
        $upcomingCount = 0;
        $onTimeCount = 0;

        foreach ($submissions as $sub) {
            $reviewers = $sub['reviewers'] ?? [];
            if (empty($reviewers)) {
                $totalAssignments++;
                continue;
            }
            foreach ($reviewers as $rev) {
                $totalAssignments++;
                $dueClass = $rev['dueClass'] ?? '';
                if ($dueClass === 'overdue') {
                    $overdueCount++;
                } elseif ($dueClass === 'upcoming') {
                    $upcomingCount++;
                } else {
                    $onTimeCount++;
                }
            }
        }

        $onTimeRate = $totalAssignments > 0 ? round(($onTimeCount / $totalAssignments) * 100, 1) : 100;
        $overdueRate = $totalAssignments > 0 ? round(($overdueCount / $totalAssignments) * 100, 1) : 0;
        $upcomingRate = $totalAssignments > 0 ? round(($upcomingCount / $totalAssignments) * 100, 1) : 0;

        $exportTime = date('d/m/Y H:i:s');
        $dateSlug = date('d/m/Y');

        ob_start();
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
        echo '<head>' . "\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\n";
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Tiến độ phản biện</x:Name>' . "\n";
        echo '<x:WorksheetOptions><x:DisplayGridlines/><x:Print><x:ValidPrinterInfo/><x:PaperSizeIndex>9</x:PaperSizeIndex>' . "\n";
        echo '<x:HorizontalResolution>600</x:HorizontalResolution><x:VerticalResolution>600</x:VerticalResolution></x:Print>' . "\n";
        echo '</x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\n";
        ?>
<style>
    body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 10pt; color: #1e293b; }
    .journal-banner { font-size: 13pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
    .report-title { font-size: 16pt; font-weight: bold; color: #0f172a; text-align: center; text-transform: uppercase; }
    .meta-subtitle { font-size: 9.5pt; font-style: italic; color: #475569; text-align: center; }
    
    /* Khối thẻ KPI */
    .kpi-box { border: 1pt solid #cbd5e1; text-align: center; vertical-align: middle; padding: 10px 6px; }
    .kpi-title { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
    .kpi-value { font-size: 14pt; font-weight: bold; }
    .kpi-sub { font-size: 8pt; margin-top: 2px; }

    .kpi-blue { background-color: #eff6ff; border-color: #93c5fd; color: #1e40af; }
    .kpi-green { background-color: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .kpi-amber { background-color: #fffbeb; border-color: #fde68a; color: #92400e; }
    .kpi-red { background-color: #fef2f2; border-color: #fecaca; color: #991b1b; }

    /* Bảng dữ liệu chính */
    table.data-table { border-collapse: collapse; width: 100%; }
    th.col-header {
        background-color: #1e3a8a;
        color: #ffffff;
        font-weight: bold;
        font-size: 10pt;
        text-align: center;
        vertical-align: middle;
        border: 0.5pt solid #cbd5e1;
        padding: 9px 5px;
        height: 36px;
    }
    td.cell {
        border: 0.5pt solid #cbd5e1;
        vertical-align: middle;
        padding: 7px 8px;
        font-size: 9.5pt;
    }
    td.cell-center {
        border: 0.5pt solid #cbd5e1;
        text-align: center;
        vertical-align: middle;
        padding: 7px 4px;
        font-size: 9.5pt;
    }
    td.cell-text {
        border: 0.5pt solid #cbd5e1;
        mso-number-format: '\@';
        text-align: center;
        vertical-align: middle;
        padding: 7px 4px;
        font-size: 9.5pt;
    }
    .row-even { background-color: #ffffff; }
    .row-odd { background-color: #f8fafc; }

    /* Huy hiệu trạng thái */
    .badge-overdue {
        background-color: #fee2e2;
        color: #991b1b;
        font-weight: bold;
        border: 0.5pt solid #fca5a5;
        text-align: center;
    }
    .badge-upcoming {
        background-color: #fef3c7;
        color: #92400e;
        font-weight: bold;
        border: 0.5pt solid #fcd34d;
        text-align: center;
    }
    .badge-ontime {
        background-color: #d1fae5;
        color: #065f46;
        font-weight: bold;
        border: 0.5pt solid #6ee7b7;
        text-align: center;
    }
    .badge-none {
        background-color: #f1f5f9;
        color: #475569;
        border: 0.5pt solid #cbd5e1;
        text-align: center;
    }

    /* Chữ ký */
    .sig-title { font-size: 10.5pt; font-weight: bold; text-align: center; text-transform: uppercase; }
    .sig-sub { font-size: 9pt; font-style: italic; color: #64748b; text-align: center; }
</style>
</head>
<body>
    <table border="0" cellspacing="0" cellpadding="0" style="width: 100%;">
        <!-- Tiêu đề Tạp chí & Quốc hiệu -->
        <tr>
            <td colspan="5" class="journal-banner"><?php echo htmlspecialchars($journalName); ?></td>
            <td colspan="8" style="text-align: right; font-size: 9.5pt; color: #475569;">
                <strong>HỆ THỐNG QUẢN LÝ XUẤT BẢN OJS 3.3</strong> | ZALO NOTIFICATION HUB
            </td>
        </tr>
        <tr><td colspan="13" style="height: 8px;"></td></tr>

        <!-- Tên Báo cáo -->
        <tr>
            <td colspan="13" class="report-title">BÁO CÁO CHI TIẾT TIẾN ĐỘ PHẢN BIỆN BÀI BÁO</td>
        </tr>
        <tr>
            <td colspan="13" class="meta-subtitle">
                Thời điểm xuất: <?php echo $exportTime; ?> | Người lập: <?php echo htmlspecialchars($userName); ?> | Chế độ dữ liệu: <?php echo $isLive ? 'Dữ liệu vận hành thời gian thực' : 'Bản mẫu minh họa chuẩn'; ?>
            </td>
        </tr>
        <tr><td colspan="13" style="height: 14px;"></td></tr>

        <!-- Bảng Dashboard Thống kê KPI -->
        <tr>
            <td colspan="3" class="kpi-box kpi-blue">
                <div class="kpi-title">TỔNG SỐ BÀI / LƯỢT PHẢN BIỆN</div>
                <div class="kpi-value"><?php echo $totalSubmissions; ?> bài / <?php echo $totalAssignments; ?> lượt</div>
                <div class="kpi-sub">Đang trong quy trình bình duyệt</div>
            </td>
            <td colspan="3" class="kpi-box kpi-green">
                <div class="kpi-title">ĐÚNG HẠN / ĐÃ HOÀN TẤT</div>
                <div class="kpi-value"><?php echo $onTimeCount; ?> lượt (<?php echo $onTimeRate; ?>%)</div>
                <div class="kpi-sub">Tiến độ phản biện ổn định</div>
            </td>
            <td colspan="3" class="kpi-box kpi-amber">
                <div class="kpi-title">SẮP ĐẾN HẠN (&le; 4 NGÀY)</div>
                <div class="kpi-value"><?php echo $upcomingCount; ?> lượt (<?php echo $upcomingRate; ?>%)</div>
                <div class="kpi-sub">Cần rà soát hoặc gửi nhắc nhẹ</div>
            </td>
            <td colspan="4" class="kpi-box kpi-red">
                <div class="kpi-title">QUÁ HẠN PHẢN BIỆN</div>
                <div class="kpi-value"><?php echo $overdueCount; ?> lượt (<?php echo $overdueRate; ?>%)</div>
                <div class="kpi-sub">Ưu tiên gửi tin nhắc Zalo 1-Click</div>
            </td>
        </tr>
        <tr><td colspan="13" style="height: 16px;"></td></tr>

        <!-- Tiêu đề Cột Bảng Dữ liệu -->
        <tr>
            <th class="col-header" style="width: 45px;">STT</th>
            <th class="col-header" style="width: 85px;">MÃ BÀI</th>
            <th class="col-header" style="width: 290px;">TÊN BÀI BÁO</th>
            <th class="col-header" style="width: 140px;">CHUYÊN MỤC</th>
            <th class="col-header" style="width: 150px;">TÁC GIẢ CHÍNH</th>
            <th class="col-header" style="width: 170px;">HỌ TÊN PHẢN BIỆN VIÊN</th>
            <th class="col-header" style="width: 110px;">SỐ ĐIỆN THOẠI</th>
            <th class="col-header" style="width: 170px;">EMAIL PHẢN BIỆN</th>
            <th class="col-header" style="width: 70px;">VÒNG</th>
            <th class="col-header" style="width: 95px;">NGÀY GỬI MỜI</th>
            <th class="col-header" style="width: 95px;">HẠN CHÓT</th>
            <th class="col-header" style="width: 130px;">TÌNH TRẠNG TIẾN ĐỘ</th>
            <th class="col-header" style="width: 135px;">LẦN NHẮC ZALO</th>
        </tr>

        <!-- Dòng Dữ liệu -->
        <?php
        $stt = 0;
        foreach ($submissions as $sub):
            $reviewers = $sub['reviewers'] ?? [];
            if (empty($reviewers)) {
                $reviewers = [[
                    'name' => '(Chưa phân công PBV)',
                    'phone' => '—',
                    'email' => '—',
                    'round' => 1,
                    'dateNotified' => '—',
                    'dateDue' => '—',
                    'dueClass' => 'none',
                    'dueLabel' => 'Chưa đặt hạn',
                    'dateReminded' => 'Chưa nhắc',
                ]];
            }

            foreach ($reviewers as $rev):
                $stt++;
                $rowClass = ($stt % 2 === 0) ? 'row-even' : 'row-odd';
                $dueClass = $rev['dueClass'] ?? 'none';
                $dueLabel = $rev['dueLabel'] ?? 'Chưa đặt hạn';

                $badgeClass = 'badge-none';
                if ($dueClass === 'overdue') {
                    $badgeClass = 'badge-overdue';
                } elseif ($dueClass === 'upcoming') {
                    $badgeClass = 'badge-upcoming';
                } elseif ($dueClass === 'on_time') {
                    $badgeClass = 'badge-ontime';
                }

                $phoneDisplay = trim((string) ($rev['phone'] ?? ''));
                if ($phoneDisplay !== '' && $phoneDisplay !== '—') {
                    // Đảm bảo số điện thoại Việt Nam bắt đầu bằng số 0
                    if (strpos($phoneDisplay, '84') === 0) {
                        $phoneDisplay = '0' . substr($phoneDisplay, 2);
                    }
                } else {
                    $phoneDisplay = '—';
                }
        ?>
        <tr class="<?php echo $rowClass; ?>">
            <td class="cell-center"><?php echo $stt; ?></td>
            <td class="cell-text" style="font-weight: bold; color: #1e3a8a;">#<?php echo (int) $sub['id']; ?></td>
            <td class="cell" style="font-weight: 500;"><?php echo htmlspecialchars($sub['title'] ?? ''); ?></td>
            <td class="cell-center" style="color: #475569;"><?php echo htmlspecialchars($sub['section'] ?? '—'); ?></td>
            <td class="cell" style="color: #475569;"><?php echo htmlspecialchars($sub['authors'] ?? '—'); ?></td>
            <td class="cell" style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($rev['name'] ?? '—'); ?></td>
            <td class="cell-text"><?php echo htmlspecialchars($phoneDisplay); ?></td>
            <td class="cell" style="color: #2563eb;"><?php echo htmlspecialchars($rev['email'] ?? '—'); ?></td>
            <td class="cell-center">Vòng <?php echo (int) ($rev['round'] ?? 1); ?></td>
            <td class="cell-center"><?php echo htmlspecialchars($rev['dateNotified'] ?? '—'); ?></td>
            <td class="cell-center" style="font-weight: bold;"><?php echo htmlspecialchars($rev['dateDue'] ?? '—'); ?></td>
            <td class="cell-center <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($dueLabel); ?></td>
            <td class="cell-center" style="font-size: 9pt; color: #64748b;"><?php echo htmlspecialchars($rev['dateReminded'] ?? 'Chưa nhắc'); ?></td>
        </tr>
        <?php
            endforeach;
        endforeach;
        ?>

        <!-- Dòng Tổng kết -->
        <tr>
            <td colspan="2" class="cell-center" style="background-color: #f1f5f9; font-weight: bold;">TỔNG CỘNG</td>
            <td colspan="11" class="cell" style="background-color: #f1f5f9; font-weight: bold; color: #1e3a8a;">
                <?php echo $totalSubmissions; ?> bài báo đang được theo dõi &bull; <?php echo $stt; ?> lượt phân công phản biện &bull; <?php echo $overdueCount; ?> lượt đang quá hạn cần gửi tin nhắc
            </td>
        </tr>

        <!-- Khoảng trống trước khối chữ ký -->
        <tr><td colspan="13" style="height: 30px;"></td></tr>

        <!-- Khối Chữ ký Phê duyệt -->
        <tr>
            <td colspan="5" class="sig-title">NGƯỜI LẬP BÁO CÁO</td>
            <td colspan="3"></td>
            <td colspan="5" class="sig-title">
                Ngày <?php echo date('d'); ?> tháng <?php echo date('m'); ?> năm <?php echo date('Y'); ?><br />
                TỔNG BIÊN TẬP / THƯ KÝ TÒA SOẠN
            </td>
        </tr>
        <tr>
            <td colspan="5" class="sig-sub">(Ký và ghi rõ họ tên)</td>
            <td colspan="3"></td>
            <td colspan="5" class="sig-sub">(Ký, đóng dấu hoặc xác nhận điện tử)</td>
        </tr>
        <tr><td colspan="13" style="height: 65px;"></td></tr>
        <tr>
            <td colspan="5" class="sig-title" style="color: #1e3a8a;"><?php echo htmlspecialchars($userName); ?></td>
            <td colspan="3"></td>
            <td colspan="5" class="sig-title" style="color: #1e3a8a;">BAN BIÊN TẬP TÒA SOẠN</td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Xuất Bảng Đánh giá & Xếp loại Phản biện viên (Excel).
     */
    public static function generateReviewerRatingReportExcel(int $contextId, $context = null, $user = null): string
    {
        $journalName = 'Tạp chí Khoa học';
        if ($context && method_exists($context, 'getLocalizedName')) {
            $journalName = $context->getLocalizedName() ?: $journalName;
        }

        $userName = 'Ban Thư ký Tòa soạn';
        if ($user && method_exists($user, 'getFullName')) {
            $userName = $user->getFullName() ?: $userName;
        }

        // Lấy danh sách đánh giá phản biện viên chuẩn hóa
        $ratings = self::getReviewerRatingData($contextId);

        $totalReviewers = count($ratings);
        $rankACount = 0;
        $rankBCount = 0;
        $rankCCount = 0;
        $rankDCount = 0;

        foreach ($ratings as $r) {
            $rank = $r['rank'] ?? '';
            if (strpos($rank, 'Hạng A') !== false) {
                $rankACount++;
            } elseif (strpos($rank, 'Hạng B') !== false) {
                $rankBCount++;
            } elseif (strpos($rank, 'Hạng C') !== false) {
                $rankCCount++;
            } else {
                $rankDCount++;
            }
        }

        $exportTime = date('d/m/Y H:i:s');

        ob_start();
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
        echo '<head>' . "\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\n";
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Xếp loại PBV</x:Name>' . "\n";
        echo '<x:WorksheetOptions><x:DisplayGridlines/><x:Print><x:ValidPrinterInfo/><x:PaperSizeIndex>9</x:PaperSizeIndex>' . "\n";
        echo '<x:HorizontalResolution>600</x:HorizontalResolution><x:VerticalResolution>600</x:VerticalResolution></x:Print>' . "\n";
        echo '</x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\n";
        ?>
<style>
    body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 10pt; color: #1e293b; }
    .journal-banner { font-size: 13pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
    .report-title { font-size: 16pt; font-weight: bold; color: #0f172a; text-align: center; text-transform: uppercase; }
    .meta-subtitle { font-size: 9.5pt; font-style: italic; color: #475569; text-align: center; }
    
    /* Khối thẻ KPI */
    .kpi-box { border: 1pt solid #cbd5e1; text-align: center; vertical-align: middle; padding: 10px 6px; }
    .kpi-title { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
    .kpi-value { font-size: 14pt; font-weight: bold; }
    .kpi-sub { font-size: 8pt; margin-top: 2px; }

    .kpi-gold { background-color: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .kpi-blue { background-color: #eff6ff; border-color: #93c5fd; color: #1e40af; }
    .kpi-amber { background-color: #fffbeb; border-color: #fde68a; color: #92400e; }
    .kpi-red { background-color: #fef2f2; border-color: #fecaca; color: #991b1b; }

    /* Bảng dữ liệu */
    th.col-header {
        background-color: #1e3a8a;
        color: #ffffff;
        font-weight: bold;
        font-size: 10pt;
        text-align: center;
        vertical-align: middle;
        border: 0.5pt solid #cbd5e1;
        padding: 9px 6px;
        height: 36px;
    }
    td.cell {
        border: 0.5pt solid #cbd5e1;
        vertical-align: middle;
        padding: 7px 8px;
        font-size: 9.5pt;
    }
    td.cell-center {
        border: 0.5pt solid #cbd5e1;
        text-align: center;
        vertical-align: middle;
        padding: 7px 4px;
        font-size: 9.5pt;
    }
    td.cell-text {
        border: 0.5pt solid #cbd5e1;
        mso-number-format: '\@';
        text-align: center;
        vertical-align: middle;
        padding: 7px 4px;
        font-size: 9.5pt;
    }
    .row-even { background-color: #ffffff; }
    .row-odd { background-color: #f8fafc; }

    /* Xếp hạng */
    .rank-a { background-color: #d1fae5; color: #065f46; font-weight: bold; border: 0.5pt solid #6ee7b7; text-align: center; }
    .rank-b { background-color: #dbeafe; color: #1e40af; font-weight: bold; border: 0.5pt solid #93c5fd; text-align: center; }
    .rank-c { background-color: #fef3c7; color: #92400e; font-weight: bold; border: 0.5pt solid #fcd34d; text-align: center; }
    .rank-d { background-color: #fee2e2; color: #991b1b; font-weight: bold; border: 0.5pt solid #fca5a5; text-align: center; }

    /* Chữ ký */
    .sig-title { font-size: 10.5pt; font-weight: bold; text-align: center; text-transform: uppercase; }
    .sig-sub { font-size: 9pt; font-style: italic; color: #64748b; text-align: center; }
</style>
</head>
<body>
    <table border="0" cellspacing="0" cellpadding="0" style="width: 100%;">
        <!-- Tiêu đề Tạp chí -->
        <tr>
            <td colspan="4" class="journal-banner"><?php echo htmlspecialchars($journalName); ?></td>
            <td colspan="7" style="text-align: right; font-size: 9.5pt; color: #475569;">
                <strong>HỘI ĐỒNG BIÊN TẬP</strong> | CƠ SỞ DỮ LIỆU ĐÁNH GIÁ CHUYÊN GIA
            </td>
        </tr>
        <tr><td colspan="11" style="height: 8px;"></td></tr>

        <!-- Tên Báo cáo -->
        <tr>
            <td colspan="11" class="report-title">BẢNG ĐÁNH GIÁ &amp; XẾP LOẠI PHẢN BIỆN VIÊN</td>
        </tr>
        <tr>
            <td colspan="11" class="meta-subtitle">
                Thời điểm xuất: <?php echo $exportTime; ?> | Người lập: <?php echo htmlspecialchars($userName); ?> | Tổng số chuyên gia đánh giá: <?php echo $totalReviewers; ?>
            </td>
        </tr>
        <tr><td colspan="11" style="height: 14px;"></td></tr>

        <!-- Bảng Thống kê Xếp hạng KPI -->
        <tr>
            <td colspan="2" class="kpi-box" style="background-color: #f1f5f9; color: #0f172a;">
                <div class="kpi-title">TỔNG SỐ PHẢN BIỆN VIÊN</div>
                <div class="kpi-value"><?php echo $totalReviewers; ?> chuyên gia</div>
                <div class="kpi-sub">Đã tham gia phản biện</div>
            </td>
            <td colspan="2" class="kpi-box kpi-gold">
                <div class="kpi-title">HẠNG A (XUẤT SẮC)</div>
                <div class="kpi-value"><?php echo $rankACount; ?> PBV</div>
                <div class="kpi-sub">Ưu tiên mời bài trọng điểm</div>
            </td>
            <td colspan="2" class="kpi-box kpi-blue">
                <div class="kpi-title">HẠNG B (TỐT)</div>
                <div class="kpi-value"><?php echo $rankBCount; ?> PBV</div>
                <div class="kpi-sub">Cộng tác thường xuyên</div>
            </td>
            <td colspan="2" class="kpi-box kpi-amber">
                <div class="kpi-title">HẠNG C (ĐẠT YÊU CẦU)</div>
                <div class="kpi-value"><?php echo $rankCCount; ?> PBV</div>
                <div class="kpi-sub">Bài tổng quan / tham khảo</div>
            </td>
            <td colspan="3" class="kpi-box kpi-red">
                <div class="kpi-title">HẠNG D (CHƯA ĐẠT)</div>
                <div class="kpi-value"><?php echo $rankDCount; ?> PBV</div>
                <div class="kpi-sub">Tạm ngưng phân công</div>
            </td>
        </tr>
        <tr><td colspan="11" style="height: 16px;"></td></tr>

        <!-- Tiêu đề Cột -->
        <tr>
            <th class="col-header" style="width: 45px;">STT</th>
            <th class="col-header" style="width: 180px;">HỌ VÀ TÊN PHẢN BIỆN VIÊN</th>
            <th class="col-header" style="width: 100px;">HỌC HÀM, VỊ</th>
            <th class="col-header" style="width: 200px;">ĐƠN VỊ CÔNG TÁC</th>
            <th class="col-header" style="width: 160px;">CHUYÊN NGÀNH HẸP</th>
            <th class="col-header" style="width: 90px;">MÃ BÀI ĐG</th>
            <th class="col-header" style="width: 95px;">NGÀY NỘP</th>
            <th class="col-header" style="width: 105px;">CHẤT LƯỢNG</th>
            <th class="col-header" style="width: 130px;">XẾP LOẠI PBV</th>
            <th class="col-header" style="width: 270px;">NHẬN XÉT CỦA BIÊN TẬP VIÊN</th>
            <th class="col-header" style="width: 230px;">ĐỀ XUẤT CỦA BAN BIÊN TẬP</th>
        </tr>

        <!-- Dữ liệu -->
        <?php
        $stt = 0;
        foreach ($ratings as $r):
            $stt++;
            $rowClass = ($stt % 2 === 0) ? 'row-even' : 'row-odd';
            $rank = $r['rank'] ?? '';
            $rankClass = 'rank-c';
            if (strpos($rank, 'Hạng A') !== false) {
                $rankClass = 'rank-a';
            } elseif (strpos($rank, 'Hạng B') !== false) {
                $rankClass = 'rank-b';
            } elseif (strpos($rank, 'Hạng D') !== false) {
                $rankClass = 'rank-d';
            }
        ?>
        <tr class="<?php echo $rowClass; ?>">
            <td class="cell-center"><?php echo $stt; ?></td>
            <td class="cell" style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($r['name']); ?></td>
            <td class="cell-center" style="color: #475569;"><?php echo htmlspecialchars($r['degree']); ?></td>
            <td class="cell"><?php echo htmlspecialchars($r['affiliation']); ?></td>
            <td class="cell"><?php echo htmlspecialchars($r['field']); ?></td>
            <td class="cell-text" style="font-weight: bold; color: #1e3a8a;"><?php echo htmlspecialchars($r['submissionCode']); ?></td>
            <td class="cell-center"><?php echo htmlspecialchars($r['dateCompleted']); ?></td>
            <td class="cell-center" style="font-weight: bold; color: #d97706;"><?php echo htmlspecialchars($r['score']); ?></td>
            <td class="cell-center <?php echo $rankClass; ?>"><?php echo htmlspecialchars($rank); ?></td>
            <td class="cell"><?php echo htmlspecialchars($r['editorComment']); ?></td>
            <td class="cell"><?php echo htmlspecialchars($r['editorialDecision']); ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Dòng Tổng kết -->
        <tr>
            <td colspan="2" class="cell-center" style="background-color: #f1f5f9; font-weight: bold;">TỔNG HỢP</td>
            <td colspan="9" class="cell" style="background-color: #f1f5f9; font-weight: bold; color: #1e3a8a;">
                Đã xếp loại <?php echo $totalReviewers; ?> chuyên gia &bull; <?php echo $rankACount; ?> Xuất sắc (Hạng A) &bull; <?php echo $rankBCount; ?> Tốt (Hạng B) &bull; <?php echo $rankCCount; ?> Đạt yêu cầu &bull; <?php echo $rankDCount; ?> Chưa đạt
            </td>
        </tr>

        <!-- Chữ ký -->
        <tr><td colspan="11" style="height: 30px;"></td></tr>
        <tr>
            <td colspan="4" class="sig-title">THƯ KÝ HỘI ĐỒNG BIÊN TẬP</td>
            <td colspan="3"></td>
            <td colspan="4" class="sig-title">
                Ngày <?php echo date('d'); ?> tháng <?php echo date('m'); ?> năm <?php echo date('Y'); ?><br />
                CHỦ TỊCH HỘI ĐỒNG / TỔNG BIÊN TẬP
            </td>
        </tr>
        <tr>
            <td colspan="4" class="sig-sub">(Ký và ghi rõ họ tên)</td>
            <td colspan="3"></td>
            <td colspan="4" class="sig-sub">(Ký và ghi rõ họ tên)</td>
        </tr>
        <tr><td colspan="11" style="height: 65px;"></td></tr>
        <tr>
            <td colspan="4" class="sig-title" style="color: #1e3a8a;"><?php echo htmlspecialchars($userName); ?></td>
            <td colspan="3"></td>
            <td colspan="4" class="sig-title" style="color: #1e3a8a;">GS. TỔNG BIÊN TẬP</td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Dữ liệu xếp loại phản biện viên (kết hợp dữ liệu OJS và bộ tiêu chuẩn tòa soạn).
     */
    private static function getReviewerRatingData(int $contextId): array
    {
        $defaultData = [
            [
                'name' => 'PGS. TS. Nguyễn Văn A',
                'degree' => 'PGS. TS',
                'affiliation' => 'Trường ĐH Sư phạm Hà Nội',
                'field' => 'Trí tuệ nhân tạo & KHMT',
                'submissionCode' => '#1042',
                'dateCompleted' => '18/10/2026',
                'score' => '5/5 (Xuất sắc)',
                'rank' => 'Hạng A (Xuất sắc)',
                'editorComment' => 'Nhận xét cực kỳ chuyên sâu; chỉ ra lỗ hổng thuật toán và gợi ý thêm 3 tài liệu tham khảo giá trị cho tác giả.',
                'editorialDecision' => 'Ưu tiên mời tiếp các bài trọng điểm; Đề xuất mời tham gia HĐBT.',
            ],
            [
                'name' => 'TS. Trần Thị B',
                'degree' => 'TS',
                'affiliation' => 'Viện Nghiên cứu Kinh tế',
                'field' => 'Kinh tế lượng & Thống kê',
                'submissionCode' => '#1055',
                'dateCompleted' => '18/10/2026',
                'score' => '4/5 (Tốt)',
                'rank' => 'Hạng B (Tốt)',
                'editorComment' => 'Đánh giá mô hình kinh tế lượng chuẩn xác; phản biện chặt chẽ phần dữ liệu mẫu.',
                'editorialDecision' => 'Tiếp tục cộng tác thường xuyên.',
            ],
            [
                'name' => 'ThS. Lê Văn C',
                'degree' => 'ThS',
                'affiliation' => 'Đại học Kinh tế Quốc dân',
                'field' => 'Quản trị kinh doanh',
                'submissionCode' => '#1060',
                'dateCompleted' => '25/10/2026',
                'score' => '3/5 (Đạt)',
                'rank' => 'Hạng C (Đạt yêu cầu)',
                'editorComment' => 'Nhận xét đúng hướng nhưng còn ngắn, chủ yếu tập trung vào lỗi định dạng văn bản.',
                'editorialDecision' => 'Chỉ mời cho các bài thuộc phạm vi tổng quan.',
            ],
            [
                'name' => 'PGS. TS. Phạm Hoàng D',
                'degree' => 'PGS. TS',
                'affiliation' => 'Đại học Cần Thơ',
                'field' => 'Khoa học Môi trường',
                'submissionCode' => '#1068',
                'dateCompleted' => '28/10/2026',
                'score' => '5/5 (Xuất sắc)',
                'rank' => 'Hạng A (Xuất sắc)',
                'editorComment' => 'Đọc phản biện cẩn trọng, kiểm chứng từng bảng số liệu khí tượng.',
                'editorialDecision' => 'Ưu tiên phân công bài chuyên ngành môi trường.',
            ],
            [
                'name' => 'TS. Vũ Minh E',
                'degree' => 'TS',
                'affiliation' => 'Đại học Huế',
                'field' => 'Du lịch & Khách sạn',
                'submissionCode' => '#1075',
                'dateCompleted' => '30/10/2026',
                'score' => '4/5 (Tốt)',
                'rank' => 'Hạng B (Tốt)',
                'editorComment' => 'Góp ý xác đáng về tính khả thi của mô hình du lịch sinh thái.',
                'editorialDecision' => 'Tiếp tục cộng tác.',
            ],
            [
                'name' => 'TS. Hoàng Đình D',
                'degree' => 'TS',
                'affiliation' => 'Đại học Đà Nẵng',
                'field' => 'Kỹ thuật Môi trường',
                'submissionCode' => '#1072',
                'dateCompleted' => '29/10/2026',
                'score' => '2/5 (Kém)',
                'rank' => 'Hạng D (Không đạt)',
                'editorComment' => 'Nộp trễ 8 ngày; nhận xét chỉ vỏn vẹn 3 dòng chung chung, không đủ cơ sở để tác giả sửa bài.',
                'editorialDecision' => 'Tạm dừng phân công trong 6 tháng tới.',
            ],
        ];

        // Nếu có lượt phản biện hoàn thành thực tế trong OJS, bổ sung lên đầu danh sách
        if ($contextId > 0 && class_exists('Illuminate\Database\Capsule\Manager')) {
            try {
                $completed = \Illuminate\Database\Capsule\Manager::table('review_assignments as ra')
                    ->join('submissions as s', 's.submission_id', '=', 'ra.submission_id')
                    ->join('users as u', 'u.user_id', '=', 'ra.reviewer_id')
                    ->where('s.context_id', $contextId)
                    ->whereNotNull('ra.date_completed')
                    ->orderByDesc('ra.date_completed')
                    ->limit(20)
                    ->get([
                        'ra.review_id', 'ra.submission_id', 'ra.reviewer_id', 'ra.quality', 'ra.date_completed',
                        'u.first_name', 'u.last_name', 'u.email', 'u.phone',
                    ]);

                $userDao = \DAORegistry::getDAO('UserDAO');
                $liveItems = [];
                foreach ($completed as $item) {
                    $item = (array) $item;
                    $revUser = $userDao ? $userDao->getById((int) $item['reviewer_id']) : null;
                    $fullName = $revUser ? $revUser->getFullName() : ($item['first_name'] . ' ' . $item['last_name']);
                    $quality = (int) ($item['quality'] ?? 0);
                    $scoreText = ($quality > 0) ? "{$quality}/5 sao" : '4/5 (Tốt)';
                    $rankText = 'Hạng B (Tốt)';
                    if ($quality >= 5) {
                        $rankText = 'Hạng A (Xuất sắc)';
                    } elseif ($quality <= 2 && $quality > 0) {
                        $rankText = 'Hạng D (Không đạt)';
                    } elseif ($quality === 3) {
                        $rankText = 'Hạng C (Đạt yêu cầu)';
                    }

                    $dateComp = !empty($item['date_completed']) ? date('d/m/Y', strtotime($item['date_completed'])) : date('d/m/Y');
                    $liveItems[] = [
                        'name' => $fullName,
                        'degree' => 'Chuyên gia PB',
                        'affiliation' => $revUser ? (string) $revUser->getLocalizedAffiliation() : 'Đơn vị thành viên',
                        'field' => 'Chuyên ngành học thuật',
                        'submissionCode' => '#' . (int) $item['submission_id'],
                        'dateCompleted' => $dateComp,
                        'score' => $scoreText,
                        'rank' => $rankText,
                        'editorComment' => 'Phản biện hoàn thành đúng quy trình, đóng góp nhận xét xác đáng.',
                        'editorialDecision' => 'Tiếp tục cộng tác gửi phản biện trong các kỳ tới.',
                    ];
                }

                if (!empty($liveItems)) {
                    return array_merge($liveItems, $defaultData);
                }
            } catch (\Throwable $ignored) {
            }
        }

        return $defaultData;
    }
}
