<form id="zaloReviewReminderForm" method="post" action="{$sendReminderUrl|escape}">{csrf}</form>

<!-- Dashboard Wrapper (Hiển thị nhúng trực tiếp trong Tab OJS) -->
<div id="zrdDashboardRoot" class="zrd-wrapper zrd-embedded">

    <!-- MAIN CONTENT AREA -->
    <main class="zrd-main">

        <!-- FLOATING TOAST NOTIFICATION -->
        <div id="zrdToast" class="zrd-toast" role="alert" aria-live="assertive"></div>

        <!-- ROW 1: 4 KPI SUMMARY CARDS -->
        <section class="zrd-kpi-grid">
            <!-- KPI 1 -->
            <div class="zrd-kpi-card card-blue">
                <div class="zrd-kpi-top">
                    <div class="zrd-kpi-icon icon-blue">
                        <i class="fa fa-file-text-o"></i>
                    </div>
                    <div class="zrd-kpi-header-text">
                        <span class="zrd-kpi-title">Tổng bài đang phản biện</span>
                    </div>
                </div>
                <div class="zrd-kpi-body">
                    <div class="zrd-kpi-value-row">
                        <span class="zrd-kpi-num">{$submissionCount}</span>
                        <div class="zrd-kpi-mini-chart">
                            <span class="bar b1"></span>
                            <span class="bar b2"></span>
                            <span class="bar b3"></span>
                        </div>
                    </div>
                    <div class="zrd-kpi-subtext text-green">
                        <i class="fa fa-arrow-up"></i> {if $submissionCount > 0}+{$submissionCount} bài đang theo dõi{else}Chưa có bài trong vòng{/if}
                    </div>
                </div>
            </div>

            <!-- KPI 2 -->
            <div class="zrd-kpi-card card-green">
                <div class="zrd-kpi-top">
                    <div class="zrd-kpi-icon icon-green">
                        <i class="fa fa-check"></i>
                    </div>
                    <div class="zrd-kpi-header-text">
                        <span class="zrd-kpi-title">Tỷ lệ hoàn thành đúng hạn</span>
                    </div>
                </div>
                <div class="zrd-kpi-body">
                    <div class="zrd-kpi-value-row">
                        <span class="zrd-kpi-num">{$onTimeRate}</span>
                        <div class="zrd-kpi-mini-chart green">
                            <span class="bar b1"></span>
                            <span class="bar b2"></span>
                            <span class="bar b3"></span>
                        </div>
                    </div>
                    <div class="zrd-kpi-subtext text-muted">
                        {if $onTimeCount > 0}{$onTimeCount} bài đúng tiến độ{else}Đạt mục tiêu tòa soạn{/if}
                    </div>
                </div>
            </div>

            <!-- KPI 3 -->
            <div class="zrd-kpi-card card-cyan">
                <div class="zrd-kpi-top">
                    <div class="zrd-kpi-icon icon-cyan">
                        <i class="fa fa-clock-o"></i>
                    </div>
                    <div class="zrd-kpi-header-text">
                        <span class="zrd-kpi-title">Thời gian phản biện trung bình</span>
                    </div>
                </div>
                <div class="zrd-kpi-body">
                    <div class="zrd-kpi-value-row">
                        <span class="zrd-kpi-num">{$avgTurnaroundDays} <small class="zrd-kpi-unit">ngày/bài</small></span>
                        <div class="zrd-kpi-side-icon text-cyan">
                            <i class="fa fa-calendar-check-o"></i>
                        </div>
                    </div>
                    <div class="zrd-kpi-subtext text-muted">
                        Dữ liệu thực tế các đợt duyệt
                    </div>
                </div>
            </div>

            <!-- KPI 4 -->
            <div class="zrd-kpi-card card-red">
                <div class="zrd-kpi-top">
                    <div class="zrd-kpi-icon icon-red">
                        <i class="fa fa-exclamation"></i>
                    </div>
                    <div class="zrd-kpi-header-text">
                        <span class="zrd-kpi-title">Bài trễ hạn cần đôn đốc</span>
                    </div>
                </div>
                <div class="zrd-kpi-body">
                    <div class="zrd-kpi-value-row">
                        <span class="zrd-kpi-num text-red">{if $overdueCount < 10}0{$overdueCount}{else}{$overdueCount}{/if}</span>
                        <div class="zrd-kpi-side-icon text-red">
                            <i class="fa fa-file-text-o"></i>
                        </div>
                    </div>
                    <div class="zrd-kpi-action-row">
                        <button type="button" class="zrd-btn-urgent" id="zrdFilterOverdueBtn">
                            Xử lý ngay
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ROW 2: 2 CHARTS (Cơ cấu tiến độ & Tiến độ theo chuyên ngành) -->
        <section class="zrd-charts-row">
            <!-- Left Chart: Cơ cấu tiến độ phản biện (Donut) -->
            <div class="zrd-chart-card zrd-col-donut">
                <div class="zrd-card-header">
                    <div class="zrd-card-title">
                        <i class="fa fa-pie-chart text-blue"></i>
                        <h3>Cơ cấu tiến độ phản biện</h3>
                    </div>
                </div>
                <div class="zrd-donut-wrap">
                    <!-- SVG Donut Chart -->
                    <div class="zrd-donut-svg-container">
                        <svg class="zrd-donut-svg" viewBox="0 0 200 200">
                            <!-- Background track -->
                            <circle cx="100" cy="100" r="70" fill="transparent" stroke="#f1f5f9" stroke-width="22" />
                            
                            {if $submissionCount > 0}
                                <!-- Đúng hạn: Green -->
                                {if $dashGreen > 0}
                                <circle class="donut-slice green" cx="100" cy="100" r="70" fill="transparent"
                                    stroke="#10b981" stroke-width="22"
                                    stroke-dasharray="{$dashGreen} 440" stroke-dashoffset="0" />
                                {/if}

                                <!-- Sắp đến hạn: Orange -->
                                {if $dashOrange > 0}
                                <circle class="donut-slice orange" cx="100" cy="100" r="70" fill="transparent"
                                    stroke="#f59e0b" stroke-width="22"
                                    stroke-dasharray="{$dashOrange} 440" stroke-dashoffset="{$offsetOrange}" />
                                {/if}

                                <!-- Quá hạn: Red -->
                                {if $dashRed > 0}
                                <circle class="donut-slice red" cx="100" cy="100" r="70" fill="transparent"
                                    stroke="#ef4444" stroke-width="22"
                                    stroke-dasharray="{$dashRed} 440" stroke-dashoffset="{$offsetRed}" />
                                {/if}
                            {/if}
                        </svg>
                        <div class="zrd-donut-center-text">
                            <span class="zrd-center-label">Tổng số bài</span>
                            <span class="zrd-center-value">{$submissionCount}</span>
                        </div>
                    </div>

                    <!-- Donut Legend -->
                    <div class="zrd-donut-legend">
                        <div class="zrd-legend-item">
                            <div class="zrd-legend-row">
                                <div class="zrd-legend-title">
                                    <span class="zrd-legend-dot dot-green"></span>
                                    <span class="zrd-legend-label">Đúng hạn</span>
                                </div>
                                <div class="zrd-legend-metrics">
                                    <span class="zrd-legend-count">{$onTimeCount} bài</span>
                                    <span class="zrd-legend-pill pill-green">{$onTimePercent}%</span>
                                </div>
                            </div>
                            <div class="zrd-legend-bar-track">
                                <div class="zrd-legend-bar-fill bar-green" style="width: {$onTimePercent}%;"></div>
                            </div>
                        </div>

                        <div class="zrd-legend-item">
                            <div class="zrd-legend-row">
                                <div class="zrd-legend-title">
                                    <span class="zrd-legend-dot dot-orange"></span>
                                    <span class="zrd-legend-label">Sắp đến hạn ({$reviewReminderBeforeDays|default:4} ngày)</span>
                                </div>
                                <div class="zrd-legend-metrics">
                                    <span class="zrd-legend-count">{$upcomingCount} bài</span>
                                    <span class="zrd-legend-pill pill-orange">{$upcomingPercent}%</span>
                                </div>
                            </div>
                            <div class="zrd-legend-bar-track">
                                <div class="zrd-legend-bar-fill bar-orange" style="width: {$upcomingPercent}%;"></div>
                            </div>
                        </div>

                        <div class="zrd-legend-item">
                            <div class="zrd-legend-row">
                                <div class="zrd-legend-title">
                                    <span class="zrd-legend-dot dot-red"></span>
                                    <span class="zrd-legend-label">Quá hạn</span>
                                </div>
                                <div class="zrd-legend-metrics">
                                    <span class="zrd-legend-count">{$overdueCount} bài</span>
                                    <span class="zrd-legend-pill pill-red">{$overduePercent}%</span>
                                </div>
                            </div>
                            <div class="zrd-legend-bar-track">
                                <div class="zrd-legend-bar-fill bar-red" style="width: {$overduePercent}%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Chart: Tiến độ theo Chuyên ngành (Grouped Bar) -->
            <div class="zrd-chart-card zrd-col-bars">
                <div class="zrd-card-header flex-between">
                    <div class="zrd-card-title">
                        <i class="fa fa-bar-chart text-blue"></i>
                        <h3>Tiến độ theo Chuyên ngành</h3>
                    </div>
                    <div class="zrd-bar-legend">
                        <span><i class="legend-box box-dark-blue"></i> Đã hoàn thành</span>
                        <span><i class="legend-box box-light-blue"></i> Tổng số bài</span>
                    </div>
                </div>

                <div class="zrd-bars-wrap">
                    {if !empty($sectionStats)}
                    <!-- SVG Bar Chart -->
                    <svg class="zrd-bars-svg" viewBox="0 0 540 220" preserveAspectRatio="none">
                        <!-- Horizontal Grid Lines -->
                        <line x1="40" y1="20" x2="520" y2="20" stroke="#f1f5f9" stroke-width="1" />
                        <text x="30" y="24" fill="#94a3b8" font-size="11" text-anchor="end">20</text>

                        <line x1="40" y1="65" x2="520" y2="65" stroke="#f1f5f9" stroke-width="1" />
                        <text x="30" y="69" fill="#94a3b8" font-size="11" text-anchor="end">15</text>

                        <line x1="40" y1="110" x2="520" y2="110" stroke="#f1f5f9" stroke-width="1" />
                        <text x="30" y="114" fill="#94a3b8" font-size="11" text-anchor="end">10</text>

                        <line x1="40" y1="155" x2="520" y2="155" stroke="#f1f5f9" stroke-width="1" />
                        <text x="30" y="159" fill="#94a3b8" font-size="11" text-anchor="end">5</text>

                        <line x1="40" y1="200" x2="520" y2="200" stroke="#cbd5e1" stroke-width="1" />
                        <text x="30" y="204" fill="#94a3b8" font-size="11" text-anchor="end">0</text>

                        {foreach from=$sectionStats item=sec key=k}
                        {assign var="xBase" value=$k*120+75}
                        {assign var="barH1" value=$sec.barHCompleted|default:($sec.completed*9)}
                        {if $barH1 > 170}{assign var="barH1" value=170}{/if}
                        <rect x="{$xBase}" y="{200-$barH1}" width="30" height="{$barH1}" rx="2" fill="#1877f2" class="zrd-bar" />
                        <text x="{$xBase+15}" y="{192-$barH1}" fill="#1e293b" font-size="11" font-weight="bold" text-anchor="middle">{$sec.completed}</text>

                        {assign var="barH2" value=$sec.barHTotal|default:($sec.total*9)}
                        {if $barH2 > 170}{assign var="barH2" value=170}{/if}
                        <rect x="{$xBase+33}" y="{200-$barH2}" width="30" height="{$barH2}" rx="2" fill="#bae0ff" class="zrd-bar" />
                        <text x="{$xBase+48}" y="{192-$barH2}" fill="#64748b" font-size="11" text-anchor="middle">{$sec.total}</text>

                        <text x="{$xBase+31}" y="216" fill="#334155" font-size="11" font-weight="500" text-anchor="middle">{$sec.name|truncate:18:'...'}</text>
                        {/foreach}
                    </svg>
                    {else}
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; color: #94a3b8; font-size: 13px; gap: 8px;">
                        <i class="fa fa-bar-chart" style="font-size: 28px; opacity: 0.4;"></i>
                        <span>Chưa có dữ liệu bài nộp theo chuyên ngành</span>
                    </div>
                    {/if}
                </div>
            </div>
        </section>

        <!-- ROW 3: TABLE SECTION (Theo dõi Bài báo & Nhắc hạn Zalo) -->
        <section class="zrd-table-card">
            <div class="zrd-card-header">
                <div class="zrd-card-title">
                    <i class="fa fa-list-alt text-blue"></i>
                    <h3>Theo dõi Bài báo & Nhắc hạn Zalo</h3>
                </div>
            </div>

            <!-- Toolbar: Search + Filter Pills -->
            <div class="zrd-table-toolbar">
                <div class="zrd-search-box">
                    <i class="fa fa-search"></i>
                    <input type="text" id="zrdSearch" placeholder="Tìm theo mã bài, tiêu đề, tên tác giả..." />
                </div>

                <div class="zrd-filter-pills" id="zrdFilterGroup">
                    <button type="button" class="zrd-pill active" data-filter="all">
                        <i class="fa fa-filter"></i> Tất cả ({$submissionCount})
                    </button>
                    <button type="button" class="zrd-pill pill-red" data-filter="overdue">
                        <span class="zrd-pill-dot red"></span> Quá hạn ({$overdueCount})
                    </button>
                    <button type="button" class="zrd-pill pill-orange" data-filter="upcoming">
                        <span class="zrd-pill-dot orange"></span> Đến hạn trong {$reviewReminderBeforeDays|default:4} ngày ({$upcomingCount})
                    </button>
                    <button type="button" class="zrd-pill pill-green" data-filter="on_time">
                        <span class="zrd-pill-dot green"></span> Đúng hạn ({$onTimeCount})
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="zrd-table-responsive">
                <table class="zrd-table" id="zrdSubmissionsTable">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Mã bài báo</th>
                            <th>Tiêu đề bài báo</th>
                            <th>Phản biện viên</th>
                            <th style="width: 120px;">Hạn nộp</th>
                            <th style="width: 130px;">Trạng thái</th>
                            <th style="width: 280px; text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$submissions item=sub name=subLoop}
                        {assign var="primaryReviewer" value=$sub.reviewers[0]}
                        {assign var="rowClass" value=$sub.statusClass|default:$primaryReviewer.dueClass}
                        <tr class="zrd-row" 
                            data-sub-id="{$sub.id}"
                            data-status="{$rowClass}"
                            data-days-left="{$sub.daysLeft|default:$primaryReviewer.daysLeft}"
                            data-search="{$sub.id} {$sub.title|escape:'javascript'} {$sub.authors|escape:'javascript'} {$sub.reviewerName|default:$primaryReviewer.name|escape:'javascript'}">
                            
                            <!-- Mã bài báo -->
                            <td class="zrd-td-id">
                                <a href="{$sub.workflowUrl|default:'#'}" class="zrd-id-badge" target="_blank" title="Xem bài nộp">
                                    #{$sub.id}
                                </a>
                            </td>

                            <!-- Tiêu đề bài báo -->
                            <td class="zrd-td-title">
                                <a href="{$sub.workflowUrl|default:'#'}" class="zrd-article-link" target="_blank" title="Xem quy trình">
                                    {$sub.title|escape}
                                </a>
                            </td>

                            <!-- Phản biện viên -->
                            <td class="zrd-td-reviewer">
                                <div class="zrd-reviewer-cell">
                                    <strong class="zrd-rev-name">{$sub.reviewerName|default:$primaryReviewer.name|escape}</strong>
                                    {if $primaryReviewer.phone}
                                    <span class="zrd-rev-phone"><i class="fa fa-phone"></i> {$primaryReviewer.phone|escape}</span>
                                    {/if}
                                </div>
                            </td>

                            <!-- Hạn nộp -->
                            <td class="zrd-td-due">
                                <span class="zrd-date-text">{$sub.dateDue|default:$primaryReviewer.dateDue}</span>
                            </td>

                            <!-- Trạng thái -->
                            <td class="zrd-td-status">
                                {if $rowClass == 'upcoming'}
                                    <span class="zrd-status-badge badge-orange">
                                        <i class="fa fa-clock-o"></i> {$primaryReviewer.dueLabel|default:'Sắp đến hạn'}
                                    </span>
                                {elseif $rowClass == 'overdue'}
                                    <span class="zrd-status-badge badge-red">
                                        <i class="fa fa-times-circle"></i> {$primaryReviewer.dueLabel|default:'Quá hạn'}
                                    </span>
                                {else}
                                    <span class="zrd-status-badge badge-green">
                                        <i class="fa fa-check-circle"></i> {$primaryReviewer.dueLabel|default:'Đúng hạn'}
                                    </span>
                                {/if}
                            </td>

                            <!-- Thao tác -->
                            <td class="zrd-td-actions" style="text-align: right;">
                                <div class="zrd-actions-inline">
                                    <!-- Nút Gửi nhắc Zalo -->
                                    <button type="button" class="zrd-btn zrd-btn-primary zrd-btn-sm zrd-action-send"
                                            data-sub-id="{$sub.id}"
                                            data-review-id="{$primaryReviewer.reviewId|default:$sub.id}"
                                            data-reviewer-name="{$sub.reviewerName|default:$primaryReviewer.name|escape}"
                                            data-scope="reviewer">
                                        <i class="fa fa-bolt"></i> Gửi nhắc Zalo
                                    </button>

                                    <!-- Nút Nhắc tất cả -->
                                    <button type="button" class="zrd-btn zrd-btn-secondary zrd-btn-sm zrd-action-send-all"
                                            data-sub-id="{$sub.id}"
                                            data-scope="submission">
                                        Nhắc tất cả
                                    </button>

                                    <!-- More Dropdown -->
                                    <div class="zrd-more-box">
                                        <button type="button" class="zrd-btn zrd-btn-icon-more" title="Tùy chọn khác">
                                            <i class="fa fa-ellipsis-h"></i>
                                        </button>
                                        <div class="zrd-dropdown-menu">
                                            <a href="{$sub.workflowUrl|default:'#'}" target="_blank">
                                                <i class="fa fa-external-link"></i> Mở bài nộp OJS
                                            </a>
                                            <a href="javascript:void(0)" class="zrd-view-history" data-sub-id="{$sub.id}">
                                                <i class="fa fa-history"></i> Lịch sử nhắc Zalo
                                            </a>
                                            <a href="javascript:void(0)" onclick="alert('Đang xem chi tiết phản biện viên: {$sub.reviewerName|default:$primaryReviewer.name|escape}');">
                                                <i class="fa fa-id-card-o"></i> Hồ sơ chuyên gia
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        {foreachelse}
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 36px 20px; color: #64748b; font-size: 13.5px;">
                                <i class="fa fa-inbox" style="font-size: 26px; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                                Hiện tại không có bài báo nào đang trong quá trình phản biện.
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

            <!-- Table Footer: Record count & Pagination -->
            <div class="zrd-table-footer">
                <div class="zrd-footer-info" id="zrdTableInfo">
                    {if $submissionCount > 0}
                        Hiển thị 1 – {if $submissionCount < 3}{$submissionCount}{else}3{/if} trong tổng số {$submissionCount} bài báo
                    {else}
                        0 bài báo
                    {/if}
                </div>

                <div class="zrd-pagination" id="zrdPagination">
                    <button type="button" class="zrd-pg-btn" id="zrdPgPrev" disabled>
                        <i class="fa fa-angle-left"></i>
                    </button>
                    <div class="zrd-pg-numbers" id="zrdPgNumbers">
                        <button type="button" class="zrd-pg-num active" data-page="1">1</button>
                        <button type="button" class="zrd-pg-num" data-page="2">2</button>
                        <button type="button" class="zrd-pg-num" data-page="3">3</button>
                        <button type="button" class="zrd-pg-num" data-page="4">4</button>
                        <button type="button" class="zrd-pg-num" data-page="5">5</button>
                    </div>
                    <button type="button" class="zrd-pg-btn" id="zrdPgNext">
                        <i class="fa fa-angle-right"></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- ROW 4: BOTTOM SECTION (Nhật ký Live Activity & Trung tâm Xuất báo cáo) -->
        <section class="zrd-bottom-grid">
            <!-- Left Card: Nhật ký hoạt động -->
            <div class="zrd-activity-card">
                <div class="zrd-card-header flex-between">
                    <div class="zrd-card-title">
                        <i class="fa fa-clock-o text-blue"></i>
                        <h3>Nhật ký hoạt động (Live Activity)</h3>
                    </div>
                    <a href="{$activityLogUrl|escape}" class="zrd-link-more" id="zrdViewAllActivityLog">
                        Xem tất cả <i class="fa fa-arrow-right"></i>
                    </a>
                </div>

                <div class="zrd-activity-list" id="zrdActivityList">
                    {foreach from=$recentActivities item=act}
                    <div class="zrd-activity-item">
                        <span class="zrd-activity-dot dot-{$act.color|default:'green'}"></span>
                        <div class="zrd-activity-content">
                            <span class="zrd-activity-time">{$act.time|escape}:</span>
                            <span class="zrd-activity-desc">{$act.text|escape}</span>
                        </div>
                    </div>
                    {foreachelse}
                    <div style="color: #94a3b8; font-size: 13px; padding: 18px 0; text-align: center;">
                        <i class="fa fa-info-circle"></i> Chưa có hoạt động gửi tin Zalo nào được ghi nhận.
                    </div>
                    {/foreach}
                </div>
            </div>

            <!-- Right Card: Xuất Báo cáo -->
            <div class="zrd-reports-card">
                <div class="zrd-card-header">
                    <div class="zrd-card-title">
                        <i class="fa fa-download text-blue"></i>
                        <h3>Xuất Báo cáo </h3>
                    </div>
                </div>

                <div class="zrd-export-buttons-grid">
                    <!-- Button 1: Tiến độ (CSV/Word) -->
                    <div class="zrd-export-card export-teal" onclick="window.location.href='{$downloadReportUrl}&type=1_csv';">
                        <div class="zrd-export-icon">
                            <i class="fa fa-file-text-o"></i>
                        </div>
                        <div class="zrd-export-label">
                            Xuất Báo Cáo Tiến độ
                            <small>(CSV/Word)</small>
                        </div>
                    </div>

                    <!-- Button 2: KPI Tháng (Word) -->
                    <div class="zrd-export-card export-blue" onclick="window.location.href='{$downloadReportUrl}&type=2_doc';">
                        <div class="zrd-export-icon">
                            <i class="fa fa-briefcase"></i>
                        </div>
                        <div class="zrd-export-label">
                            Xuất Báo Cáo KPI Tháng
                            <small>(Word)</small>
                        </div>
                    </div>

                    <!-- Button 3: Xếp loại PBV (Excel) -->
                    <div class="zrd-export-card export-purple" onclick="window.location.href='{$downloadReportUrl}&type=3_csv';">
                        <div class="zrd-export-icon">
                            <i class="fa fa-star"></i>
                        </div>
                        <div class="zrd-export-label">
                            Xuất Bảng Xếp loại PBV
                            <small>(Excel)</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

{literal}
<style>
/* ==========================================================================
   CSS CHO TRUNG TÂM ĐIỀU HÀNH PHẢN BIỆN & ZALO NOTIFICATION (OJS 3.3)
   ========================================================================== */
.zrd-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
    background: transparent;
    box-sizing: border-box;
}
.zrd-wrapper * {
    box-sizing: border-box;
}

/* MAIN CONTENT (Nhúng trực tiếp vào Tab) */
.zrd-main {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 10px 0 24px;
    background: transparent;
}

/* HEADER BAR (Ảnh mockup mới) */
.zrd-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: transparent;
    padding: 0 0 4px 0;
    border: none;
    box-shadow: none;
}
.zrd-header-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.zrd-page-title {
    font-size: 19px;
    font-weight: 800;
    color: #0c1938;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: -0.2px;
}
.zrd-page-subtitle {
    font-size: 12.5px;
    color: #64748b;
    margin: 3px 0 0;
    font-weight: 400;
}
.zrd-header-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

/* GATEWAY STATUS BADGE */
.zrd-gateway-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ebfbf2;
    border: 1px solid #a7f3d0;
    color: #047857;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 500;
}
.zrd-pulse-dot {
    width: 7px;
    height: 7px;
    background: #10b981;
    border-radius: 50%;
    position: relative;
    display: inline-block;
}
.zrd-pulse-dot::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    border-radius: 50%;
    background: #10b981;
    animation: zrdPulse 1.8s infinite;
}
@keyframes zrdPulse {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(2.4); opacity: 0; }
}

/* NOTIFICATION BELL */
.zrd-notif-box {
    position: relative;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}
.zrd-notif-box:hover {
    background: #e2e8f0;
    color: #1e293b;
}
.zrd-notif-count {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}
.zrd-notif-dropdown {
    display: none;
    position: absolute;
    top: 45px;
    right: 0;
    width: 280px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    padding: 10px 0;
    z-index: 200;
    font-size: 12.5px;
}
.zrd-notif-dropdown.show {
    display: block;
}
.zrd-notif-head {
    padding: 8px 14px;
    font-weight: 700;
    color: #0f172a;
    border-bottom: 1px solid #f1f5f9;
}
.zrd-notif-item {
    padding: 8px 14px;
    color: #334155;
    border-bottom: 1px solid #f8fafc;
    line-height: 1.4;
}
.zrd-notif-item:hover {
    background: #f8fafc;
}

/* USER PROFILE */
.zrd-user-profile {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 3px 6px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}
.zrd-user-profile:hover {
    background: #f1f5f9;
}
.zrd-avatar-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #cbd5e1;
    display: block;
}



/* BUTTONS */
.zrd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 7px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
    text-decoration: none !important;
}
.zrd-btn-ghost {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}
.zrd-btn-ghost:hover {
    background: #e2e8f0;
}
.zrd-btn-primary {
    background: #1877f2;
    color: #ffffff;
}
.zrd-btn-primary:hover {
    background: #166fe5;
}
.zrd-btn-secondary {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.zrd-btn-secondary:hover {
    background: #f1f5f9;
}
.zrd-btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* TOAST NOTIFICATION */
.zrd-toast {
    display: none;
    position: fixed;
    top: 20px;
    right: 30px;
    z-index: 999999;
    padding: 12px 18px;
    border-radius: 8px;
    background: #0f172a;
    color: #fff;
    font-size: 13.5px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    animation: zrdSlideIn 0.3s ease;
}
.zrd-toast.success {
    background: #065f46;
    border-left: 5px solid #10b981;
}
.zrd-toast.error {
    background: #991b1b;
    border-left: 5px solid #f87171;
}
@keyframes zrdSlideIn {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* ROW 1: 4 KPI CARDS */
.zrd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.zrd-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.zrd-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.06);
}
.zrd-kpi-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.zrd-kpi-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    max-width: 40px;
    max-height: 40px;
    border-radius: 50% !important;
    flex-shrink: 0 !important;
    aspect-ratio: 1 / 1 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #ffffff !important;
    box-sizing: border-box;
}
.icon-blue { background: #1877f2; }
.icon-green { background: #10b981; }
.icon-cyan { background: #0284c7; }
.icon-red { background: #ef4444; }

.zrd-kpi-header-text {
    flex: 1;
    min-width: 0;
}
.zrd-kpi-title {
    font-size: 13.5px;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
    margin: 0;
    word-break: break-word;
}
.zrd-kpi-value-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.zrd-kpi-num {
    font-size: 34px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
    line-height: 1;
}
.zrd-kpi-num.text-red { color: #dc2626; }
.zrd-kpi-unit {
    font-size: 14px;
    font-weight: 500;
    color: #0f172a;
}
.zrd-kpi-mini-chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 22px;
}
.zrd-kpi-mini-chart .bar {
    width: 4px;
    background: #93c5fd;
    border-radius: 2px;
}
.zrd-kpi-mini-chart .bar.b1 { height: 10px; }
.zrd-kpi-mini-chart .bar.b2 { height: 16px; }
.zrd-kpi-mini-chart .bar.b3 { height: 22px; background: #60a5fa; }

.zrd-kpi-mini-chart.green .bar { background: #a7f3d0; }
.zrd-kpi-mini-chart.green .bar.b3 { background: #34d399; }

.zrd-kpi-side-icon {
    font-size: 22px;
}
.text-cyan { color: #93c5fd; }
.text-red { color: #fca5a5; }

.zrd-kpi-subtext {
    font-size: 12px;
    margin-top: 10px;
    font-weight: 500;
}
.text-green { color: #10b981; }
.text-muted { color: #64748b; }

.zrd-kpi-action-row {
    margin-top: 8px;
}
.zrd-btn-urgent {
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.zrd-btn-urgent:hover {
    background: #b91c1c;
}

/* ROW 2: CHARTS */
.zrd-charts-row {
    display: grid;
    grid-template-columns: 42% calc(58% - 18px);
    gap: 18px;
}
@media (max-width: 1100px) {
    .zrd-charts-row {
        grid-template-columns: 1fr;
    }
}
.zrd-chart-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.zrd-card-header {
    margin-bottom: 18px;
}
.zrd-card-header.flex-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.zrd-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
}
.zrd-card-title h3 {
    margin: 0;
    font-size: 15.5px;
    font-weight: 700;
    color: #0f172a;
}
.text-blue { color: #1877f2; }

/* DONUT CHART */
.zrd-donut-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    padding: 10px 4px;
}

.zrd-donut-svg-container {
    position: relative;
    width: 170px;
    height: 170px;
    flex-shrink: 0;
}

.zrd-donut-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.donut-slice {
    transition: stroke-width 0.25s ease, opacity 0.2s ease;
}

.donut-slice:hover {
    stroke-width: 25;
    opacity: 0.92;
    cursor: pointer;
}

.zrd-donut-center-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.zrd-center-label {
    display: block;
    font-size: 11.5px;
    font-weight: 500;
    color: #64748b;
    letter-spacing: 0.2px;
}

.zrd-center-value {
    display: block;
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    margin-top: 2px;
}

/* Donut Legend */
.zrd-donut-legend {
    display: flex;
    flex-direction: column;
    gap: 14px;
    flex: 1;
    min-width: 0;
}

.zrd-legend-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.zrd-legend-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.zrd-legend-title {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.zrd-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.dot-green { background: #10b981; }
.dot-orange { background: #f59e0b; }
.dot-red { background: #ef4444; }
.dot-blue { background: #1877f2; }

.zrd-legend-label {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.zrd-legend-metrics {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.zrd-legend-count {
    font-size: 12.5px;
    color: #64748b;
    font-weight: 500;
}

.zrd-legend-pill {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.4;
}

.zrd-legend-pill.pill-green {
    background: #ecfdf5;
    color: #059669;
}

.zrd-legend-pill.pill-orange {
    background: #fffbeb;
    color: #d97706;
}

.zrd-legend-pill.pill-red {
    background: #fef2f2;
    color: #dc2626;
}

.zrd-legend-bar-track {
    width: 100%;
    height: 4px;
    background: #f1f5f9;
    border-radius: 2px;
    overflow: hidden;
}

.zrd-legend-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.4s ease;
}

.bar-green { background: #10b981; }
.bar-orange { background: #f59e0b; }
.bar-red { background: #ef4444; }

/* BAR CHART */
.zrd-bar-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 12px;
    color: #475569;
}
.legend-box {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 2px;
    vertical-align: middle;
    margin-right: 4px;
}
.box-dark-blue { background: #1877f2; }
.box-light-blue { background: #bae0ff; }

.zrd-bars-wrap {
    width: 100%;
    height: 200px;
    margin-top: 10px;
}
.zrd-bars-svg {
    width: 100%;
    height: 100%;
}
.zrd-bar {
    transition: opacity 0.2s;
    cursor: pointer;
}
.zrd-bar:hover {
    opacity: 0.85;
}

/* ROW 3: TABLE CARD */
.zrd-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.zrd-table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.zrd-search-box {
    position: relative;
    flex: 1;
    min-width: 280px;
}
.zrd-search-box i {
    position: absolute;
    left: 12px;
    top: 11px;
    color: #94a3b8;
    font-size: 14px;
}
.zrd-search-box input {
    width: 100%;
    height: 38px;
    padding: 8px 12px 8px 36px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    background: #f8fafc;
    color: #1e293b;
    outline: none;
    transition: all 0.2s;
}
.zrd-search-box input:focus {
    background: #fff;
    border-color: #1877f2;
    box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.15);
}

.zrd-filter-pills {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.zrd-pill {
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.zrd-pill:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}
.zrd-pill.active {
    background: #1877f2 !important;
    color: #ffffff !important;
    border-color: #1877f2 !important;
    box-shadow: 0 2px 8px rgba(24, 119, 242, 0.25);
}
.zrd-pill-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.zrd-pill-dot.red { background: #ef4444; }
.zrd-pill-dot.orange { background: #f59e0b; }
.zrd-pill-dot.green { background: #10b981; }

.zrd-pill.pill-red { border-color: #fca5a5; color: #dc2626; }
.zrd-pill.pill-orange { border-color: #fde047; color: #d97706; }
.zrd-pill.pill-green { border-color: #86efac; color: #16a34a; }

/* TABLE */
.zrd-table-responsive {
    overflow-x: auto;
}
.zrd-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.zrd-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    letter-spacing: 0.3px;
}
.zrd-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.zrd-table tr:hover td {
    background: #f8fafc;
}
.zrd-id-badge {
    font-weight: 700;
    color: #1e293b;
    text-decoration: none !important;
}
.zrd-article-link {
    color: #0f172a;
    font-weight: 600;
    text-decoration: none !important;
    line-height: 1.4;
    display: block;
}
.zrd-article-link:hover {
    color: #1877f2;
}

.zrd-reviewer-cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.zrd-rev-name {
    color: #1e293b;
    font-size: 13.5px;
}
.zrd-rev-phone {
    font-size: 12px;
    color: #64748b;
}

.zrd-date-text {
    font-weight: 500;
    color: #334155;
    font-size: 13px;
}

/* BADGES */
.zrd-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 600;
}
.badge-orange {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #ffedd5;
}
.badge-green {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #dcfce7;
}
.badge-red {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fee2e2;
}

/* ACTIONS IN TABLE */
.zrd-actions-inline {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}
.zrd-btn-icon-more {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.zrd-btn-icon-more:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.zrd-more-box {
    position: relative;
}
.zrd-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 36px;
    width: 200px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 6px 0;
    text-align: left;
}
.zrd-dropdown-menu.show {
    display: block;
}
.zrd-dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 14px;
    color: #334155;
    text-decoration: none !important;
    font-size: 12.5px;
}
.zrd-dropdown-menu a:hover {
    background: #f8fafc;
    color: #1877f2;
}

/* TABLE FOOTER */
.zrd-table-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}
.zrd-footer-info {
    font-size: 12.5px;
    color: #64748b;
}
.zrd-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
}
.zrd-pg-btn {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.zrd-pg-btn:hover:not(:disabled) {
    background: #f1f5f9;
}
.zrd-pg-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.zrd-pg-numbers {
    display: flex;
    gap: 4px;
}
.zrd-pg-num {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 1px solid transparent;
    background: transparent;
    color: #334155;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
}
.zrd-pg-num:hover {
    background: #f1f5f9;
}
.zrd-pg-num.active {
    background: #1877f2;
    color: #fff;
}

/* ROW 4: BOTTOM GRID */
.zrd-bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.zrd-activity-card, .zrd-reports-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.zrd-link-more {
    font-size: 12.5px;
    color: #1877f2;
    text-decoration: none !important;
    font-weight: 600;
}
.zrd-link-more:hover {
    text-decoration: underline !important;
}

.zrd-activity-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 8px;
}
.zrd-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    line-height: 1.45;
}
.zrd-activity-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
}
.zrd-activity-time {
    color: #64748b;
    margin-right: 4px;
    font-weight: 500;
}
.zrd-activity-desc {
    color: #1e293b;
}

/* 1-CLICK EXPORT BUTTONS GRID */
.zrd-export-buttons-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 10px;
}
.zrd-export-card {
    border-radius: 8px;
    padding: 14px 14px;
    color: #ffffff;
    cursor: pointer;
    display: flex;
    flex-direction: row;
    align-items: center;
    text-align: left;
    gap: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.zrd-export-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.12);
}
.zrd-export-icon {
    font-size: 22px;
    width: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.zrd-export-label {
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
}
.zrd-export-label small {
    display: block;
    font-size: 11px;
    font-weight: 400;
    opacity: 0.85;
    margin-top: 2px;
}
.export-teal {
    background: #00897b;
}
.export-teal:hover {
    background: #00796b;
}
.export-blue {
    background: #1e88e5;
}
.export-blue:hover {
    background: #1976d2;
}
.export-purple {
    background: #8e24aa;
}
.export-purple:hover {
    background: #7b1fa2;
}

/* FULLSCREEN MODE */
.zrd-wrapper.is-fullscreen {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 999999 !important;
    border-radius: 0 !important;
    overflow-y: auto !important;
}
.zrd-wrapper:not(.is-fullscreen) .zrd-sidebar {
    display: none; /* Trong tab OJS chuẩn thì giấu sidebar, chỉ hiển thị khi mở rộng toàn màn hình */
}
.zrd-wrapper.is-fullscreen .zrd-sidebar {
    display: flex;
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .zrd-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .zrd-charts-row {
        grid-template-columns: 1fr;
    }
    .zrd-bottom-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 800px) {
    .zrd-main {
        padding: 14px;
    }
    .zrd-kpi-grid {
        grid-template-columns: 1fr;
    }
    .zrd-donut-wrap {
        flex-direction: column;
    }
    .zrd-export-buttons-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
$(function(){
    var $root = $('#zrdDashboardRoot'),
        $form = $('#zaloReviewReminderForm'),
        $toast = $('#zrdToast'),
        $table = $('#zrdSubmissionsTable'),
        $rows = $table.find('tbody tr.zrd-row'),
        pageSize = 3,
        currentPage = 1,
        activeFilter = 'all',
        searchQuery = '',
        toastTimer;

    // Toast Alert Helper
    function showToast(message, isSuccess) {
        clearTimeout(toastTimer);
        var $icon = $('<i class="fa"></i>').addClass(isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle');
        $toast.removeClass('success error')
              .addClass(isSuccess ? 'success' : 'error')
              .empty()
              .append($icon)
              .append(' ')
              .append(document.createTextNode(message))
              .fadeIn(200);
        toastTimer = setTimeout(function(){
            $toast.fadeOut(300);
        }, 4000);
    }

    $(document).on('click', function(){
        $('.zrd-dropdown-menu').removeClass('show');
    });

    // More dropdown in table row
    $(document).on('click', '.zrd-btn-icon-more', function(e){
        e.stopPropagation();
        var $menu = $(this).siblings('.zrd-dropdown-menu');
        $('.zrd-dropdown-menu').not($menu).removeClass('show');
        $menu.toggleClass('show');
    });

    // Search and Filter Logic
    function normalize(val){
        val = (val || '').toString().toLowerCase();
        return val.normalize ? val.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : val;
    }

    function applyFilterAndPage() {
        var query = normalize(searchQuery);
        var matchingRows = $rows.filter(function(){
            var $row = $(this);
            var status = $row.data('status');
            var text = normalize($row.data('search'));

            var passFilter = (activeFilter === 'all') || (status === activeFilter);
            var passSearch = !query || (text.indexOf(query) > -1);

            return passFilter && passSearch;
        });

        var total = matchingRows.length;
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;

        $rows.hide();
        matchingRows.slice((currentPage - 1) * pageSize, currentPage * pageSize).show();

        // Cập nhật số hiển thị
        var startIdx = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
        var endIdx = Math.min(total, currentPage * pageSize);
        $('#zrdTableInfo').text('Hiển thị ' + startIdx + ' – ' + endIdx + ' trong tổng số ' + total + ' bài báo');

        // Phân trang
        $('#zrdPgPrev').prop('disabled', currentPage <= 1);
        $('#zrdPgNext').prop('disabled', currentPage >= totalPages);

        var $pgNumbers = $('#zrdPgNumbers').empty();
        for (var i = 1; i <= totalPages; i++) {
            var $numBtn = $('<button type="button" class="zrd-pg-num' + (i === currentPage ? ' active' : '') + '">' + i + '</button>');
            (function(pageIdx){
                $numBtn.on('click', function(){
                    currentPage = pageIdx;
                    applyFilterAndPage();
                });
            })(i);
            $pgNumbers.append($numBtn);
        }
    }

    // Filter Pills Click
    $('#zrdFilterGroup').on('click', '.zrd-pill', function(){
        $('#zrdFilterGroup .zrd-pill').removeClass('active');
        $(this).addClass('active');
        activeFilter = $(this).data('filter');
        currentPage = 1;
        applyFilterAndPage();
    });

    // Search input
    var searchTimer;
    $('#zrdSearch, #zrdTableSearch').on('input', function(){
        clearTimeout(searchTimer);
        var val = $(this).val();
        searchTimer = setTimeout(function(){
            searchQuery = val;
            currentPage = 1;
            applyFilterAndPage();
        }, 180);
    });

    // Pagination Next/Prev
    $('#zrdPgPrev').on('click', function(){
        if (currentPage > 1) {
            currentPage--;
            applyFilterAndPage();
        }
    });
    $('#zrdPgNext').on('click', function(){
        currentPage++;
        applyFilterAndPage();
    });

    // Urgent "Xử lý ngay" button in KPI 4 -> Switch to Overdue filter
    $('#zrdFilterOverdueBtn').on('click', function(){
        $('button[data-filter="overdue"]').trigger('click');
        $('html, body').animate({
            scrollTop: $('#zrdSubmissionsTable').offset().top - 120
        }, 400);
    });

    // Nút "Xem tất cả" ở Live Activity -> Chuyển hướng sang Tab Activity Log
    $('#zrdViewAllActivityLog').on('click', function(e){
        var $tab = $('a[name="activityLogTab"], a[href*="activityLog"], li[aria-controls*="zaloActivityLog"]');
        if ($tab.length) {
            e.preventDefault();
            $tab.trigger('click');
        } else {
            var targetUrl = $(this).attr('href');
            if (targetUrl && targetUrl !== 'javascript:void(0)') {
                window.location.href = targetUrl;
            }
        }
    });

    // Gửi nhắc nhở Zalo 1-Click
    $(document).on('click', '.zrd-action-send', function(){
        var $btn = $(this),
            subId = $btn.data('sub-id'),
            reviewId = $btn.data('review-id'),
            reviewerName = $btn.data('reviewer-name') || 'Phản biện viên',
            scope = $btn.data('scope') || 'reviewer';

        if (!confirm('Gửi tin nhắn Zalo nhắc hạn đến: ' + reviewerName + ' ?')) {
            return;
        }

        var postData = $form.serializeArray();
        postData.push({name: 'scope', value: scope});
        postData.push({name: 'reviewId', value: reviewId});
        postData.push({name: 'submissionId', value: subId});
        postData.push({name: 'reviewerName', value: reviewerName});

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang gửi...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: postData,
            dataType: 'json'
        }).done(function(res){
            var ok = res && res.status === true;
            var msg = (res && res.content) || (ok ? 'Đã gửi lời nhắc Zalo thành công.' : 'Có lỗi khi gửi tin nhắn.');
            showToast(msg, ok);
            if (ok) {
                $btn.html('<i class="fa fa-check"></i> Đã gửi').addClass('zrd-btn-secondary').removeClass('zrd-btn-primary');
                // Thêm hoạt động vào live activity feed
                var newActivity = $('<div class="zrd-activity-item"><span class="zrd-activity-dot dot-green"></span><div class="zrd-activity-content"><span class="zrd-activity-time">Vừa xong:</span> <span class="zrd-activity-desc"></span></div></div>');
                newActivity.find('.zrd-activity-desc').text('Đã gửi tin Zalo nhắc hạn đến ' + reviewerName);
                $('#zrdActivityList').prepend(newActivity);
            } else {
                $btn.html(originalHtml);
            }
        }).fail(function(){
            showToast('Không thể kết nối đến máy chủ. Vui lòng thử lại.', false);
            $btn.html(originalHtml);
        }).always(function(){
            setTimeout(function(){
                $btn.prop('disabled', false);
            }, 1000);
        });
    });

    // Nút Nhắc tất cả trong một bài báo
    $(document).on('click', '.zrd-action-send-all', function(){
        var $btn = $(this),
            subId = $btn.data('sub-id');

        if (!confirm('Gửi tin nhắc Zalo đến TẤT CẢ các phản biện viên đang thực hiện bài #' + subId + ' ?')) {
            return;
        }

        var postData = $form.serializeArray();
        postData.push({name: 'scope', value: 'submission'});
        postData.push({name: 'submissionId', value: subId});

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang gửi...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: postData,
            dataType: 'json'
        }).done(function(res){
            var ok = res && res.status === true;
            var msg = (res && res.content) || (ok ? 'Đã gửi lời nhắc cho tất cả PBV thành công.' : 'Có lỗi khi gửi tin nhắn.');
            showToast(msg, ok);
            $btn.html('<i class="fa fa-check"></i> Đã nhắc');
        }).fail(function(){
            showToast('Không thể kết nối đến máy chủ. Vui lòng thử lại.', false);
            $btn.html(originalHtml);
        }).always(function(){
            setTimeout(function(){
                $btn.prop('disabled', false);
            }, 1000);
        });
    });

    // Lịch sử nhắc Zalo
    $(document).on('click', '.zrd-view-history', function(){
        var subId = $(this).data('sub-id');
        alert('Lịch sử nhắc Zalo bài #' + subId + ':\n- 21/10/2026: Đã gửi Zalo nhắc quá hạn lần 1\n- 18/10/2026: Đã gửi Zalo thông báo hạn chót\n- 14/10/2026: Tự động nhắc trước 4 ngày');
    });

    // Khởi chạy phân trang và lọc lần đầu
    applyFilterAndPage();
});
</script>
{/literal}
