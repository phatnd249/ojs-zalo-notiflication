{**
 * plugins/generic/zaloNotification/templates/activityLog.tpl
 *
 * Activity Log viewer cho Zalo Notification Plugin
 * Thiết kế hiện đại: Thanh thông số KPI tinh gọn ngang, ngăn kéo chi tiết có thể thu gọn,
 * tối ưu tối đa không gian cho bảng log.
 *}

{literal}
<style>
.zalo-log-container {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #25323d;
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
    overflow: visible !important;
    padding: 4px 2px 14px !important;
    display: flex;
    flex-direction: column;
}

/* 1. Header */
.zalo-log-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0 0 16px 0 !important;
    background: transparent !important;
    border-bottom: none !important;
    flex-shrink: 0;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 2px;
}

.zalo-header-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.zalo-header-title {
    margin: 0;
    font-size: 1.35em;
    font-weight: 700;
    color: #0066a6;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.25;
}

.zalo-header-subtitle {
    margin: 0;
    color: #65717c;
    font-size: 14px;
    line-height: 1.4;
}

.zalo-header-retention {
    font-size: 13.5px;
    color: #1e293b;
    background: #eef5fa;
    border: 1px solid #d8e7f1;
    border-radius: 18px;
    padding: 6px 13px;
    white-space: nowrap;
}

.zalo-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.zalo-stat-chip {
    font-size: 13.5px;
    color: #1e293b;
    background: #eef5fa;
    border: 1px solid #d8e7f1;
    padding: 6px 13px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.zalo-stat-chip strong {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
}

.zalo-stat-chip.is-queue.is-overdue,
.zalo-stat-chip.is-queue.has-error {
    background: #fff1f0;
    border-color: #ffc9c5;
    color: #a42620;
}

.zalo-stat-chip.is-queue strong.text-danger {
    color: #a42620;
}

/* 2. KPI Ribbon (Thanh thông số tinh gọn) */
.zsw-container {
    background: transparent !important;
    border: none !important;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.zsw-ribbon {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #f6f9fb;
    border: 1px solid #dce5eb;
    border-radius: 6px;
    flex-wrap: wrap;
    gap: 10px;
}

.zsw-date-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.zsw-quick-date {
    font-size: 13px;
    font-weight: 500;
    padding: 5px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}

.zsw-quick-date:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.zsw-quick-date.active {
    background: #0066a6;
    color: #ffffff;
    border-color: #0066a6;
}

.zsw-date-group input[type="date"] {
    font-size: 13px;
    padding: 4px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #334155;
    height: 34px;
}

.zsw-btn-date-filter {
    font-size: 13px;
    font-weight: 600;
    padding: 5px 12px;
    border: 1px solid #93c5fd;
    border-radius: 4px;
    background: #eff6ff;
    color: #1d4ed8;
    cursor: pointer;
    transition: all 0.15s ease;
    height: 34px;
}

.zsw-btn-date-filter:hover {
    background: #dbeafe;
}

/* KPI Group */
.zsw-kpi-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.zsw-kpi-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    padding: 5px 12px;
    border-radius: 16px;
    border: 1px solid #d8e7f1;
    background: #ffffff;
    color: #334155;
    white-space: nowrap;
}

.zsw-kpi-pill strong {
    font-weight: 700;
    font-size: 13.5px;
}

.zsw-kpi-pill.is-sent {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
}

.zsw-kpi-pill.is-delivered {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.zsw-kpi-pill.is-failed {
    border-color: #fecaca;
    background: #fef2f2;
    color: #b91c1c;
}

.zsw-kpi-pill.is-failed.has-alert {
    background: #fee2e2;
    border-color: #f87171;
    color: #b91c1c;
}

.zsw-kpi-pill.is-overdue {
    border-color: #fed7aa;
    background: #fff7ed;
    color: #c2410c;
}

.zsw-kpi-pill.is-overdue.has-alert {
    background: #fff1f0;
    border-color: #fca5a5;
    color: #b91c1c;
}

.zsw-kpi-pill.is-duesoon {
    border-color: #fef08a;
    background: #fefce8;
    color: #a16207;
}

.zsw-drawer-toggle {
    font-size: 13px;
    font-weight: 600;
    padding: 5px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #0066a6;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    height: 34px;
    transition: all 0.15s ease;
}

.zsw-drawer-toggle:hover {
    background: #f0f9ff;
    border-color: #7dd3fc;
}

/* 3. Collapsible Drawer (Chi tiết thống kê) */
.zsw-drawer {
    background: #f8fafc;
    border: 1px solid #dce5eb;
    border-top: none;
    border-radius: 0 0 6px 6px;
    padding: 14px 16px;
    margin-top: -12px;
    margin-bottom: 12px;
}

.zsw-drawer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.zsw-drawer-title {
    font-weight: 700;
    font-size: 13.5px;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.zsw-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.zsw-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.zsw-card-header {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
    letter-spacing: 0.03em;
    margin-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.zsw-badge-tag {
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 4px;
    background: #f1f5f9;
    color: #64748b;
    font-weight: 500;
    text-transform: none;
}

.zsw-stats-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.zsw-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #475569;
    padding: 2px 0;
}

.zsw-stat-val {
    font-weight: 700;
    font-size: 13.5px;
    color: #1e293b;
}

.zsw-val-delivered { color: #16a34a; }
.zsw-val-failed    { color: #dc2626; }
.zsw-val-retrying  { color: #d97706; }
.zsw-val-overdue   { color: #dc2626; font-weight: 700; }
.zsw-val-duesoon   { color: #d97706; }

.zsw-activity-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    max-height: 100px;
    overflow-y: auto;
}

.zsw-activity-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.zsw-act-icon {
    font-weight: bold;
    display: inline-block;
    width: 14px;
    text-align: center;
    flex-shrink: 0;
}

.zsw-act-icon.is-success { color: #16a34a; }
.zsw-act-icon.is-danger  { color: #dc2626; }
.zsw-act-icon.is-warning { color: #d97706; }

.zsw-act-time {
    color: #94a3b8;
    font-size: 11.5px;
    flex-shrink: 0;
}

.zsw-act-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.zsw-empty-act {
    color: #94a3b8;
    font-size: 12.5px;
    font-style: italic;
    padding: 8px 0;
    text-align: center;
}

/* 4. Toolbar */
.zalo-log-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: #f6f9fb;
    border: 1px solid #dce5eb;
    border-radius: 6px;
    margin-bottom: 12px;
    flex-shrink: 0;
    flex-wrap: wrap;
    gap: 10px;
}

.zalo-toolbar-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.zalo-filter-label {
    font-weight: 600;
    color: #475569;
    font-size: 13.5px;
}

.zalo-select {
    font-size: 13.5px;
    height: 38px;
    padding: 0 10px;
    border: 1px solid #b8c5ce;
    border-radius: 4px;
    background: #ffffff;
    color: #1e293b;
    cursor: pointer;
    min-width: 170px;
}

.zalo-select:focus {
    outline: none;
    border-color: #0066a6;
    box-shadow: 0 0 0 2px rgba(0, 102, 166, 0.15);
}

.zalo-search-box {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-width: 260px;
    flex: 1;
    max-width: 400px;
}

.zalo-search-icon {
    position: absolute;
    left: 10px;
    color: #7d8b95;
    font-size: 13.5px;
    pointer-events: none;
}

.zalo-search-input {
    width: 100%;
    height: 38px;
    font-size: 13.5px;
    padding: 0 28px 0 32px;
    border: 1px solid #b8c5ce;
    border-radius: 4px;
    background: #ffffff;
    color: #1e293b;
    transition: all 0.15s ease;
}

.zalo-search-input:focus {
    outline: none;
    border-color: #0066a6;
    box-shadow: 0 0 0 2px rgba(0, 102, 166, 0.15);
}

.zalo-search-clear {
    position: absolute;
    right: 8px;
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 16px;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}

.zalo-search-clear:hover {
    color: #475569;
}

.zalo-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.zalo-btn {
    height: 38px;
    font-size: 13px;
    font-weight: 500;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.zalo-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

.zalo-btn-primary {
    background: #0066a6;
    color: #ffffff;
    border-color: #0066a6;
}

.zalo-btn-primary:hover {
    background: #005082;
    border-color: #005082;
}

/* 5. Table Wrap & Table */
.zalo-log-table-wrap {
    background: #ffffff;
    border: 1px solid #d8e0e6;
    border-radius: 7px;
    overflow-x: auto;
    margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.zalo-log-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.zalo-log-table thead th {
    background: #fbfcfd;
    padding: 12px 14px;
    text-align: left;
    font-weight: 700;
    color: #52616d;
    border-bottom: 1px solid #dfe5e9;
    font-size: 12.5px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.zalo-log-table tbody td {
    padding: 13px 14px;
    border-bottom: 1px solid #edf0f2;
    color: #25323d;
    vertical-align: top;
    font-size: 13.5px;
    line-height: 1.5;
}

.zalo-log-table tbody tr:hover td {
    background: #f8fafc;
}

.zalo-log-table .col-time {
    white-space: nowrap;
    color: #475569;
    font-size: 13px;
}

.zalo-log-table .col-id {
    text-align: center;
    font-weight: 600;
    color: #0066a6;
    font-size: 13.5px;
}

.zalo-log-table .col-title {
    color: #0f172a;
    font-weight: 600;
    font-size: 13.5px;
    word-break: break-word;
}

.zalo-log-table .col-detail {
    font-size: 13px;
    line-height: 1.5;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.badge-info    { background: #dbeafe; color: #1d4ed8; }
.badge-warning { background: #fef3c7; color: #b45309; }
.badge-error   { background: #fee2e2; color: #dc2626; }

.badge-submission       { background: #d1fae5; color: #065f46; }
.badge-decision         { background: #e0e7ff; color: #3730a3; }
.badge-publish          { background: #fce7f3; color: #9d174d; }
.badge-unpublish        { background: #fee2e2; color: #991b1b; }
.badge-zalo             { background: #cffafe; color: #0e7490; }
.badge-err-type         { background: #fee2e2; color: #dc2626; }
.badge-reminder         { background: #fff7ed; color: #9a3412; }
.badge-review-response  { background: #ecfeff; color: #0e7490; }
.badge-review-completed { background: #dcfce7; color: #166534; }

/* Detail block & toggle */
.zalo-detail-toggle {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-top: 4px;
    cursor: pointer;
    color: #0066a6;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid #d8e7f1;
    background: #eef5fa;
    padding: 2px 7px;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.zalo-detail-toggle:hover {
    background: #e0f2fe;
    color: #005082;
}

.zalo-detail-content {
    display: none;
    margin-top: 6px;
    padding: 8px 10px;
    background: #0f172a;
    color: #f8fafc;
    border-radius: 5px;
    font-size: 12px;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    max-height: 180px;
    overflow-y: auto;
    word-break: break-all;
}

/* Empty state */
.zalo-log-empty {
    text-align: center;
    padding: 50px 20px;
    color: #94a3b8;
}

.zalo-log-empty .empty-icon {
    font-size: 36px;
    margin-bottom: 8px;
}

/* 6. Pagination */
.zalo-log-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 12px 14px;
    background: transparent;
    border-top: none;
    margin: 6px 0;
}

.zalo-log-pagination button {
    min-width: 82px;
    height: 34px;
    padding: 0 15px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    color: #334155;
    background: #ffffff;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.zalo-log-pagination button:hover:not(:disabled) {
    color: #0066a6;
    border-color: #80b8da;
    background: #f0f9ff;
}

.zalo-log-pagination button:disabled {
    opacity: 0.45;
    cursor: default;
}

.zalo-log-page-info {
    min-width: 150px;
    color: #586773;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
}

@media (max-width: 900px) {
    .zsw-grid { grid-template-columns: 1fr; }
    .zsw-ribbon { flex-direction: column; align-items: flex-start; }
    .zalo-log-toolbar { flex-direction: column; align-items: stretch; }
    .zalo-search-box { max-width: 100%; }
}
</style>
{/literal}
<div class="zalo-log-container">
    {* 1. Header Bar *}
    <div class="zalo-log-header">
        <div class="zalo-header-left">
            <div>
                <h3 class="zalo-header-title">
                    <i class="fa fa-file-text-o" style="color:#0066a6;"></i> Activity Log — Zalo Notification
                </h3>
                <p class="zalo-header-subtitle">Nhật ký hệ thống gửi tin Zalo, theo dõi các sự kiện biên tập và phản biện.</p>
            </div>
        </div>
        <div class="zalo-header-right">
            <span class="zalo-stat-chip zalo-header-retention" title="Nhật ký được bảo vệ và tự động xoay vòng sau {$logRetentionDays|default:90} ngày">
                Lưu <strong>{$logRetentionDays|default:90}</strong> ngày
            </span>
            <span class="zalo-stat-chip" title="Tổng số bản ghi trong thời hạn lưu trữ">
                <strong>{$totalEntries}</strong> bản ghi
            </span>
            <span class="zalo-stat-chip is-queue {if $outboxStats.failed > 0}is-overdue{/if}" title="Trạng thái hàng đợi tin nhắn hiện tại">
                Hàng đợi: <strong>{$outboxStats.pending}</strong> chờ, 
                <strong>{$outboxStats.processing}</strong> đang gửi
                {if $outboxStats.failed > 0}
                    , <strong class="text-danger">{$outboxStats.failed} lỗi</strong>
                {/if}
            </span>
        </div>
    </div>

    {* 2. KPI Ribbon & Collapsible Drawer *}
    <div class="zsw-container" id="zaloSummaryWidget">
        {* 2.1 Thanh KPI tinh gọn ngang *}
        <div class="zsw-ribbon">
            <div class="zsw-date-group">
                <input type="date" id="zswDatePicker" value="{$selectedStatsDate|escape}" title="Chọn ngày xem thống kê">
                <button type="button" id="zswBtnFilterByDate" class="zsw-btn-date-filter" title="Lọc bảng log theo ngày này">
                    Lọc bảng
                </button>
            </div>

            <div class="zsw-kpi-group">
                <span class="zsw-kpi-pill is-sent" title="Tổng tin Zalo đã gửi trong ngày">
                    Gửi: <strong id="kpiSent">{$dailyStats.sent}</strong>
                </span>
                <span class="zsw-kpi-pill is-delivered" title="Gửi thành công">
                    Thành công: <strong id="kpiDelivered">{$dailyStats.delivered}</strong>
                </span>
                <span class="zsw-kpi-pill is-failed {if $dailyStats.failed > 0}has-alert{/if}" title="Gửi lỗi">
                    Lỗi: <strong id="kpiFailed">{$dailyStats.failed}</strong>
                </span>
                <span class="zsw-kpi-pill is-overdue {if $dailyStats.reviewer_reminders.overdue > 0}has-alert{/if}" title="Phản biện đang quá hạn">
                    PB Quá hạn: <strong id="kpiOverdue">{$dailyStats.reviewer_reminders.overdue}</strong>
                </span>
                <span class="zsw-kpi-pill is-duesoon" title="Phản biện đến hạn trong 3 ngày">
                    PB Sắp hạn: <strong id="kpiDueSoon">{$dailyStats.reviewer_reminders.due_in_3_days}</strong>
                </span>
            </div>

            <div class="zsw-action-group">
                <button type="button" class="zsw-drawer-toggle" id="zswToggleBtn" title="Xem chi tiết các chỉ số thống kê">
                    <span id="zswToggleIcon">▼</span> <span id="zswToggleText">Chi tiết</span>
                </button>
            </div>
        </div>

        {* 2.2 Ngăn kéo chi tiết (mặc định thu gọn) *}
        <div class="zsw-drawer" id="zswDrawer" style="display:none;">
            <div class="zsw-drawer-header">
                <div class="zsw-drawer-title">
                    <span>Chi tiết phân tích ngày</span>
                    <span id="zswDateLabel" style="font-weight:700;color:#0284c7;">({$dailyStats.date|escape})</span>
                </div>
                <small style="color:#64748b;">Số liệu tổng hợp tự động từ nhật ký và hàng đợi tin nhắn</small>
            </div>

            <div class="zsw-grid" id="zswGrid">
                {* Card 1: Gửi tin Zalo *}
                <div class="zsw-card">
                    <div class="zsw-card-header">
                        <span>Gửi tin Zalo</span>
                        <span id="zswBadgeDate" class="zsw-badge-tag">{$dailyStats.date|escape}</span>
                    </div>
                    <div class="zsw-stats-list">
                        <div class="zsw-stat-row">
                            <span>Đã phát lệnh (Sent):</span>
                            <span class="zsw-stat-val" id="zswStatSent">{$dailyStats.sent}</span>
                        </div>
                        <div class="zsw-stat-row">
                            <span>Thành công (Delivered):</span>
                            <span class="zsw-stat-val zsw-val-delivered" id="zswStatDelivered">{$dailyStats.delivered}</span>
                        </div>
                        <div class="zsw-stat-row">
                            <span>Gửi lỗi (Failed):</span>
                            <span class="zsw-stat-val zsw-val-failed" id="zswStatFailed">{$dailyStats.failed}</span>
                        </div>
                        <div class="zsw-stat-row">
                            <span>Đang thử lại (Retrying):</span>
                            <span class="zsw-stat-val zsw-val-retrying" id="zswStatRetrying">{$dailyStats.retrying}</span>
                        </div>
                    </div>
                </div>

                {* Card 2: Nhắc phản biện *}
                <div class="zsw-card">
                    <div class="zsw-card-header">
                        <span>Nhắc phản biện</span>
                        <span class="zsw-badge-tag">Tình trạng</span>
                    </div>
                    <div class="zsw-stats-list">
                        <div class="zsw-stat-row">
                            <span>Đang quá hạn (Overdue):</span>
                            <span class="zsw-stat-val zsw-val-overdue" id="zswStatOverdue">{$dailyStats.reviewer_reminders.overdue}</span>
                        </div>
                        <div class="zsw-stat-row">
                            <span>Hạn trong 3 ngày (Due soon):</span>
                            <span class="zsw-stat-val zsw-val-duesoon" id="zswStatDueSoon">{$dailyStats.reviewer_reminders.due_in_3_days}</span>
                        </div>
                        <div class="zsw-stat-row">
                            <span>Đã gửi nhắc trong ngày:</span>
                            <span class="zsw-stat-val" id="zswStatRemindedToday">{$dailyStats.events.review_reminder}</span>
                        </div>
                        <div class="zsw-stat-row">
                            <span>Đã nộp đánh giá:</span>
                            <span class="zsw-stat-val zsw-val-delivered" id="zswStatCompletedToday">{$dailyStats.events.review_completed}</span>
                        </div>
                    </div>
                </div>

                {* Card 3: Hoạt động gần đây *}
                <div class="zsw-card">
                    <div class="zsw-card-header">
                        <span>Hoạt động trong ngày</span>
                        <span id="zswActivityCount" class="zsw-badge-tag">{count($dailyStats.recent_activity)} sự kiện</span>
                    </div>
                    <div class="zsw-activity-list" id="zswActivityList">
                        {if empty($dailyStats.recent_activity)}
                            <div class="zsw-empty-act">Không có hoạt động nào trong ngày này.</div>
                        {else}
                            {foreach from=$dailyStats.recent_activity item=act}
                                <div class="zsw-activity-item" title="{$act.type|escape}: {$act.label|escape} ({$act.status|escape})">
                                    <span class="zsw-act-icon is-{$act.class|escape}">{$act.icon|escape}</span>
                                    <span class="zsw-act-time">{$act.time|escape}</span>
                                    <span class="zsw-act-label">{$act.label|escape}</span>
                                </div>
                            {/foreach}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* 3. Toolbar: Lọc + Tìm kiếm + Tác vụ *}
    <div class="zalo-log-toolbar" id="zaloLogToolbar">
        <div class="zalo-toolbar-filters">
            <label for="zaloFilterSelect" class="zalo-filter-label">Lọc:</label>
            <select id="zaloFilterSelect" class="zalo-select">
                <option value="">— Tất cả loại log —</option>
                <option value="SUBMISSION" {if $filterType == 'SUBMISSION'}selected{/if}>Nộp bài</option>
                <option value="DECISION" {if $filterType == 'DECISION'}selected{/if}>Quyết định</option>
                <option value="PUBLISH" {if $filterType == 'PUBLISH'}selected{/if}>Xuất bản</option>
                <option value="UNPUBLISH" {if $filterType == 'UNPUBLISH'}selected{/if}>Hủy xuất bản</option>
                <option value="ZALO_SEND" {if $filterType == 'ZALO_SEND'}selected{/if}>Gửi Zalo</option>
                <option value="ERROR" {if $filterType == 'ERROR'}selected{/if}>Lỗi</option>
                <option value="REVIEW_REQUEST" {if $filterType == 'REVIEW_REQUEST'}selected{/if}>Mời phản biện</option>
                <option value="REVIEW_RESPONSE" {if $filterType == 'REVIEW_RESPONSE'}selected{/if}>PB phản hồi</option>
                <option value="REVIEW_REMINDER" {if $filterType == 'REVIEW_REMINDER'}selected{/if}>Nhắc hạn PB</option>
                <option value="REVIEW_COMPLETED" {if $filterType == 'REVIEW_COMPLETED'}selected{/if}>PB đã nộp</option>
            </select>

            <div class="zalo-search-box">
                <span class="zalo-search-icon"><i class="fa fa-search"></i></span>
                <input type="text" id="zaloSearchInput" class="zalo-search-input" placeholder="Tìm kiếm ID, tiêu đề, tên tác giả/biên tập...">
                <button type="button" id="zaloSearchClear" class="zalo-search-clear" style="display:none;" title="Xóa tìm kiếm">×</button>
            </div>
        </div>

        <div class="zalo-toolbar-actions">
            <button type="button" id="zaloBtnRefresh" class="zalo-btn" title="Làm mới bảng log"><i class="fa fa-refresh"></i> Làm mới</button>
            <button type="button" id="zaloBtnExport" class="zalo-btn zalo-btn-primary" title="Tải xuống tệp nhật ký TXT"><i class="fa fa-download"></i> Xuất file log</button>
        </div>
    </div>

    {* 4. Bảng Log *}
    <div class="zalo-log-table-wrap">
        {if $totalEntries > 0}
            <table class="zalo-log-table">
                <thead>
                    <tr>
                        <th style="width:130px;">Thời gian</th>
                        <th style="width:115px;">Loại</th>
                        <th style="width:65px;text-align:center;">Level</th>
                        <th style="width:90px;">Actor</th>
                        <th style="width:60px;text-align:center;">ID</th>
                        <th>Tiêu đề bài báo</th>
                        <th style="width:280px;">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$entries item=entry key=idx}
                        <tr data-submission-id="{$entry.submission_id|escape}"
                            data-title="{$entry.title|escape}"
                            data-performer="{if isset($entry.extra.performed_by)}{$entry.extra.performed_by|escape}{/if}"
                            data-author="{if isset($entry.extra.author)}{$entry.extra.author|escape}{/if}"
                            data-reviewer="{if isset($entry.extra.reviewerName)}{$entry.extra.reviewerName|escape}{elseif isset($entry.extra.reviewer)}{$entry.extra.reviewer|escape}{/if}"
                            data-action="{if isset($entry.extra.action_detail) && $entry.type != 'REVIEW_REQUEST' && $entry.type != 'REVIEW_RESPONSE' && $entry.type != 'REVIEW_COMPLETED'}{$entry.extra.action_detail|escape}{else}{$entry.action|escape}{/if}"
                            data-actor="{$entry.actor|escape}">
                            <td class="col-time">{$entry.time|escape}</td>
                            <td>
                                {if $entry.type == 'SUBMISSION'}
                                    <span class="badge badge-submission">Nộp bài</span>
                                {elseif $entry.type == 'DECISION'}
                                    <span class="badge badge-decision">Quyết định</span>
                                {elseif $entry.type == 'PUBLISH'}
                                    <span class="badge badge-publish">Xuất bản</span>
                                {elseif $entry.type == 'UNPUBLISH'}
                                    <span class="badge badge-unpublish">Hủy XB</span>
                                {elseif $entry.type == 'ZALO_SEND'}
                                    <span class="badge badge-zalo">Zalo</span>
                                {elseif $entry.type == 'ERROR'}
                                    <span class="badge badge-err-type">Lỗi</span>
                                {elseif $entry.type == 'REVIEW_REQUEST'}
                                    <span class="badge badge-reminder">Mời PB</span>
                                {elseif $entry.type == 'REVIEW_RESPONSE'}
                                    <span class="badge badge-review-response">PB phản hồi</span>
                                {elseif $entry.type == 'REVIEW_REMINDER'}
                                    <span class="badge badge-reminder">Nhắc hạn</span>
                                {elseif $entry.type == 'REVIEW_COMPLETED'}
                                    <span class="badge badge-review-completed">PB đã nộp</span>
                                {else}
                                    <span class="badge">{$entry.type|escape}</span>
                                {/if}
                            </td>
                            <td style="text-align:center;">
                                {if $entry.level == 'INFO'}
                                    <span class="badge badge-info">INFO</span>
                                {elseif $entry.level == 'WARNING'}
                                    <span class="badge badge-warning">WARN</span>
                                {elseif $entry.level == 'ERROR'}
                                    <span class="badge badge-error">ERROR</span>
                                {else}
                                    <span class="badge">{$entry.level|escape}</span>
                                {/if}
                            </td>
                            <td>{$entry.actor|escape}</td>
                            <td class="col-id">
                                {if $entry.submission_id > 0}
                                    #{$entry.submission_id}
                                {else}
                                    —
                                {/if}
                            </td>
                            <td class="col-title">
                                {if $entry.title}
                                    <span title="{$entry.title|escape}">{$entry.title|escape|truncate:70:"..."}</span>
                                {else}
                                    <span style="color:#94a3b8;">—</span>
                                {/if}
                            </td>
                            <td class="col-detail">
                                {if isset($entry.extra)}
                                    {if isset($entry.extra.performed_by)}
                                        <div>{$entry.extra.performed_by|escape}</div>
                                    {/if}
                                    {if isset($entry.extra.decision)}
                                        <div>{$entry.extra.decision|escape}</div>
                                    {/if}
                                    {if isset($entry.extra.zalo_status)}
                                        <div>Zalo: <strong>{$entry.extra.zalo_status|escape}</strong></div>
                                    {/if}
                                    {if isset($entry.extra.status)}
                                        <div>Status: <strong>{$entry.extra.status|escape}</strong></div>
                                    {/if}
                                    {if isset($entry.extra.message)}
                                        <div style="color:#dc2626;">{$entry.extra.message|escape|truncate:80:"..."}</div>
                                    {/if}
                                    {if isset($entry.extra.reviewerName)}
                                        <div>Reviewer: {$entry.extra.reviewerName|escape}</div>
                                    {elseif isset($entry.extra.reviewer)}
                                        <div>Reviewer: {$entry.extra.reviewer|escape}</div>
                                    {/if}
                                    {if isset($entry.extra.deadline)}
                                        <div>Hạn: {$entry.extra.deadline|escape}
                                            {if isset($entry.extra.daysLeft)}
                                                ({if $entry.extra.daysLeft < 0}<span style="color:#dc2626;font-weight:600;">quá hạn</span>{elseif $entry.extra.daysLeft == 0}<span style="color:#d97706;font-weight:600;">HÔM NAY</span>{else}còn {$entry.extra.daysLeft} ngày{/if})
                                            {/if}
                                        </div>
                                    {elseif isset($entry.extra.date_due)}
                                        <div>Hạn: {$entry.extra.date_due|escape}
                                            {if isset($entry.extra.days_left)}
                                                ({if $entry.extra.days_left == 0}<span style="color:#d97706;font-weight:600;">HÔM NAY</span>{else}còn {$entry.extra.days_left} ngày{/if})
                                            {/if}
                                        </div>
                                    {/if}
                                    {if isset($entry.extra.recommendation)}
                                        <div>📌 Đề xuất: {$entry.extra.recommendation|escape}</div>
                                    {/if}
                                    {if isset($entry.extra.response_status)}
                                        <div>Phản hồi: {$entry.extra.response_status|escape}</div>
                                    {/if}
                                    {if isset($entry.extra.action_detail) && $entry.type != 'REVIEW_REQUEST' && $entry.type != 'REVIEW_RESPONSE' && $entry.type != 'REVIEW_COMPLETED'}
                                        <div>{$entry.extra.action_detail|escape|truncate:100:"..."}</div>
                                    {/if}
                                    <span class="zalo-detail-toggle">
                                        <span>xem thêm</span> ▾
                                    </span>
                                    <div class="zalo-detail-content">
                                        <pre style="margin:0;white-space:pre-wrap;">{$entry.extra|@json_encode:JSON_PRETTY_PRINT|escape}</pre>
                                    </div>
                                {else}
                                    <span style="color:#94a3b8;">—</span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <div class="zalo-log-empty">
                <div class="empty-icon">📭</div>
                <p style="font-size:14px;font-weight:500;color:#64748b;">Chưa có hoạt động nào được ghi lại.</p>
                <p style="font-size:12px;">Log sẽ xuất hiện khi có bài nộp mới, quyết định biên tập, xuất bản, hủy xuất bản hoặc hoạt động phản biện.</p>
            </div>
        {/if}
    </div>

    {* 5. Phân trang *}
    {if $totalEntries > 0}
        <nav class="zalo-log-pagination" id="zaloLogPagination" aria-label="Phân trang Activity Log">
            <button type="button" id="zaloLogPrev">◀ Trước</button>
            <span class="zalo-log-page-info" id="zaloLogPageInfo"></span>
            <button type="button" id="zaloLogNext">Sau ▶</button>
        </nav>
    {/if}
</div>

<script>
    $(function() {ldelim}
        // Base URLs
        var ajaxUrl = '{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.SettingsPluginGridHandler" op="manage" category="generic" plugin=$pluginName verb="activityLog" escape=false}';
        var exportUrl = '{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.SettingsPluginGridHandler" op="manage" category="generic" plugin=$pluginName verb="exportLog" escape=false}';
        var statsUrl = '{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.SettingsPluginGridHandler" op="manage" category="generic" plugin=$pluginName verb="dailySummaryStats" escape=false}';

        var logPage = 1;
        var logPageSize = 20;

        function getLogContainer() {ldelim}
            return $('.zalo-log-container').first();
        {rdelim}

        function rowMatchesSearch(row, searchTerm) {ldelim}
            if (searchTerm === '') return true;

            var submissionId = row.attr('data-submission-id') || '';
            var fields = [
                row.attr('data-title') || '',
                row.attr('data-performer') || '',
                row.attr('data-author') || '',
                row.attr('data-reviewer') || '',
                row.attr('data-action') || '',
                row.attr('data-actor') || ''
            ];
            if (/^\d+$/.test(searchTerm) && submissionId === searchTerm) return true;

            for (var i = 0; i < fields.length; i++) {ldelim}
                if (fields[i].toLowerCase().indexOf(searchTerm) > -1) return true;
            {rdelim}
            return false;
        {rdelim}

        function renderLogPage(resetPage) {ldelim}
            if (resetPage) logPage = 1;
            var container = getLogContainer();
            var searchInput = container.find('#zaloSearchInput');
            var searchTerm = $.trim(searchInput.val() || '').toLowerCase();
            container.find('#zaloSearchClear').toggle(searchTerm.length > 0);

            var rows = container.find('.zalo-log-table tbody tr');
            var matchedRows = rows.filter(function() {ldelim}
                return rowMatchesSearch($(this), searchTerm);
            {rdelim});
            var pages = Math.max(1, Math.ceil(matchedRows.length / logPageSize));
            if (logPage > pages) logPage = pages;

            rows.hide();
            matchedRows.slice((logPage - 1) * logPageSize, logPage * logPageSize).show();
            container.find('#zaloLogPageInfo').text('Trang ' + logPage + ' / ' + pages + ' · ' + matchedRows.length + ' log');
            container.find('#zaloLogPrev').prop('disabled', logPage <= 1);
            container.find('#zaloLogNext').prop('disabled', logPage >= pages);
            container.find('#zaloLogPagination').toggle(matchedRows.length > logPageSize);
        {rdelim}

        // Tải lại log qua AJAX
        function reloadLog(filterValue) {ldelim}
            var url = ajaxUrl;
            if (filterValue) {ldelim}
                url += '&filterType=' + encodeURIComponent(filterValue);
            {rdelim}

            var currentContainer = getLogContainer();
            currentContainer.css('opacity', '0.5');

            $.get(url, function(response) {ldelim}
                if (response && response.status && response.content) {ldelim}
                    var nextContainer = $('<div>').append($.parseHTML(response.content, document, true)).find('.zalo-log-container').first();
                    if (nextContainer.length) {ldelim}
                        currentContainer.replaceWith(nextContainer);
                        applyDrawerState();
                        renderLogPage(true);
                        return;
                    {rdelim}
                {rdelim}
                currentContainer.css('opacity', '1');
                alert('Không thể tải dữ liệu log.');
            {rdelim}).fail(function() {ldelim}
                currentContainer.css('opacity', '1');
                alert('Có lỗi xảy ra khi kết nối với máy chủ.');
            {rdelim});
        {rdelim}

        // Cập nhật số liệu thống kê qua AJAX
        function updateDailyStats(targetDate) {ldelim}
            var container = getLogContainer();
            if (!targetDate) targetDate = container.find('#zswDatePicker').val();
            container.find('#zaloSummaryWidget').css('opacity', '0.6');

            $.getJSON(statsUrl + '&statsDate=' + encodeURIComponent(targetDate), function(res) {ldelim}
                container.find('#zaloSummaryWidget').css('opacity', '1');
                if (res && res.status && res.content) {ldelim}
                    var data = res.content;
                    // Cập nhật thanh KPI ribbon
                    container.find('#kpiSent').text(data.sent);
                    container.find('#kpiDelivered').text(data.delivered);
                    container.find('#kpiFailed').text(data.failed);
                    var $pillFailed = container.find('.zsw-kpi-pill.is-failed');
                    $pillFailed.toggleClass('has-alert', data.failed > 0);

                    var overdue = (data.reviewer_reminders && data.reviewer_reminders.overdue) || 0;
                    var dueSoon = (data.reviewer_reminders && data.reviewer_reminders.due_in_3_days) || 0;
                    container.find('#kpiOverdue').text(overdue);
                    var $pillOverdue = container.find('.zsw-kpi-pill.is-overdue');
                    $pillOverdue.toggleClass('has-alert', overdue > 0);

                    container.find('#kpiDueSoon').text(dueSoon);

                    // Cập nhật ngăn kéo chi tiết
                    container.find('#zswDateLabel').text(data.is_today ? '(Hôm nay: ' + data.date + ')' : '(' + data.date + ')');
                    container.find('#zswBadgeDate').text(data.date);
                    container.find('#zswStatSent').text(data.sent);
                    container.find('#zswStatDelivered').text(data.delivered);
                    container.find('#zswStatFailed').text(data.failed);
                    container.find('#zswStatRetrying').text(data.retrying);
                    container.find('#zswStatOverdue').text(overdue);
                    container.find('#zswStatDueSoon').text(dueSoon);
                    container.find('#zswStatRemindedToday').text((data.events && data.events.review_reminder) || 0);
                    container.find('#zswStatCompletedToday').text((data.events && data.events.review_completed) || 0);

                    var actHtml = '';
                    if (data.recent_activity && data.recent_activity.length > 0) {ldelim}
                        container.find('#zswActivityCount').text(data.recent_activity.length + ' sự kiện');
                        for (var i = 0; i < data.recent_activity.length; i++) {ldelim}
                            var a = data.recent_activity[i];
                            actHtml += '<div class="zsw-activity-item" title="' + (a.type || '') + ': ' + (a.label || '') + ' (' + (a.status || '') + ')">'
                                + '<span class="zsw-act-icon is-' + a.class + '">' + a.icon + '</span>'
                                + '<span class="zsw-act-time">' + a.time + '</span>'
                                + '<span class="zsw-act-label">' + a.label + '</span>'
                                + '</div>';
                        {rdelim}
                    {rdelim} else {ldelim}
                        container.find('#zswActivityCount').text('0 sự kiện');
                        actHtml = '<div class="zsw-empty-act">Không có hoạt động nào trong ngày này.</div>';
                    {rdelim}
                    container.find('#zswActivityList').html(actHtml);
                {rdelim}
            {rdelim}).fail(function() {ldelim}
                container.find('#zaloSummaryWidget').css('opacity', '1');
            {rdelim});
        {rdelim}

        // Quản lý trạng thái ngăn kéo chi tiết (Collapsible Drawer)
        function applyDrawerState() {ldelim}
            var container = getLogContainer();
            var isOpen = localStorage.getItem('zalo_activity_drawer_open') === 'true';
            var drawer = container.find('#zswDrawer');
            var icon = container.find('#zswToggleIcon');
            var text = container.find('#zswToggleText');

            if (isOpen) {ldelim}
                drawer.show();
                icon.text('▲');
                text.text('Thu gọn');
            {rdelim} else {ldelim}
                drawer.hide();
                icon.text('▼');
                text.text('Chi tiết');
            {rdelim}
        {rdelim}

        // Đăng ký sự kiện mở/đóng ngăn kéo
        $(document).off('click.zaloLog', '#zswToggleBtn').on('click.zaloLog', '#zswToggleBtn', function() {ldelim}
            var container = getLogContainer();
            var drawer = container.find('#zswDrawer');
            var isVisible = drawer.is(':visible');
            if (isVisible) {ldelim}
                drawer.slideUp(150);
                container.find('#zswToggleIcon').text('▼');
                container.find('#zswToggleText').text('Chi tiết');
                localStorage.setItem('zalo_activity_drawer_open', 'false');
            {rdelim} else {ldelim}
                drawer.slideDown(150);
                container.find('#zswToggleIcon').text('▲');
                container.find('#zswToggleText').text('Thu gọn');
                localStorage.setItem('zalo_activity_drawer_open', 'true');
            {rdelim}
        {rdelim});

        // Đổi ngày nhanh
        $(document).off('click.zaloLog', '.zsw-quick-date').on('click.zaloLog', '.zsw-quick-date', function() {ldelim}
            var container = getLogContainer();
            container.find('.zsw-quick-date').removeClass('active');
            $(this).addClass('active');
            var type = $(this).data('date');
            var targetDate = new Date();
            if (type === 'yesterday') {ldelim}
                targetDate.setDate(targetDate.getDate() - 1);
            {rdelim}
            var yyyy = targetDate.getFullYear();
            var mm = String(targetDate.getMonth() + 1).padStart(2, '0');
            var dd = String(targetDate.getDate()).padStart(2, '0');
            var dateStr = yyyy + '-' + mm + '-' + dd;
            container.find('#zswDatePicker').val(dateStr);
            updateDailyStats(dateStr);
        {rdelim});

        // Date picker thay đổi
        $(document).off('change.zaloLog', '#zswDatePicker').on('change.zaloLog', '#zswDatePicker', function() {ldelim}
            var container = getLogContainer();
            container.find('.zsw-quick-date').removeClass('active');
            updateDailyStats($(this).val());
        {rdelim});

        // Nút lọc bảng theo ngày
        $(document).off('click.zaloLog', '#zswBtnFilterByDate').on('click.zaloLog', '#zswBtnFilterByDate', function() {ldelim}
            var container = getLogContainer();
            var picked = container.find('#zswDatePicker').val();
            container.find('#zaloSearchInput').val(picked);
            renderLogPage(true);
        {rdelim});

        // Đổi filter select
        $(document).off('change.zaloLog', '#zaloFilterSelect').on('change.zaloLog', '#zaloFilterSelect', function() {ldelim}
            reloadLog($(this).val());
        {rdelim});

        // Tìm kiếm
        $(document).off('input.zaloLog', '#zaloSearchInput').on('input.zaloLog', '#zaloSearchInput', function() {ldelim}
            renderLogPage(true);
        {rdelim});

        // Nút xóa tìm kiếm
        $(document).off('click.zaloLog', '#zaloSearchClear').on('click.zaloLog', '#zaloSearchClear', function() {ldelim}
            var container = getLogContainer();
            container.find('#zaloSearchInput').val('');
            renderLogPage(true);
        {rdelim});

        // Phân trang
        $(document).off('click.zaloLog', '#zaloLogPrev').on('click.zaloLog', '#zaloLogPrev', function() {ldelim}
            if (logPage > 1) {ldelim}
                logPage--;
                renderLogPage(false);
                getLogContainer().find('.zalo-log-table-wrap').scrollTop(0);
            {rdelim}
        {rdelim});

        $(document).off('click.zaloLog', '#zaloLogNext').on('click.zaloLog', '#zaloLogNext', function() {ldelim}
            var container = getLogContainer();
            var term = $.trim(container.find('#zaloSearchInput').val() || '').toLowerCase();
            var totalMatched = container.find('.zalo-log-table tbody tr').filter(function() {ldelim}
                return rowMatchesSearch($(this), term);
            {rdelim}).length;
            if (logPage * logPageSize < totalMatched) {ldelim}
                logPage++;
                renderLogPage(false);
                container.find('.zalo-log-table-wrap').scrollTop(0);
            {rdelim}
        {rdelim});

        // Nút Làm mới
        $(document).off('click.zaloLog', '#zaloBtnRefresh').on('click.zaloLog', '#zaloBtnRefresh', function(e) {ldelim}
            e.preventDefault();
            reloadLog($('#zaloFilterSelect').val());
        {rdelim});

        // Nút Xuất log
        $(document).off('click.zaloLog', '#zaloBtnExport').on('click.zaloLog', '#zaloBtnExport', function(e) {ldelim}
            e.preventDefault();
            var filterValue = $('#zaloFilterSelect').val();
            window.location.href = filterValue
                ? exportUrl + '&filterType=' + encodeURIComponent(filterValue)
                : exportUrl;
        {rdelim});

        // Toggle xem thêm chi tiết JSON
        $(document).off('click.zaloLog', '.zalo-detail-toggle').on('click.zaloLog', '.zalo-detail-toggle', function(e) {ldelim}
            e.preventDefault();
            var $content = $(this).next('.zalo-detail-content');
            var isShown = $content.is(':visible');
            if (isShown) {ldelim}
                $content.hide();
                $(this).html('<span>xem thêm</span> ▾');
            {rdelim} else {ldelim}
                $content.show();
                $(this).html('<span>thu gọn</span> ▴');
            {rdelim}
        {rdelim});

        // Khởi tạo ban đầu
        applyDrawerState();
        renderLogPage(true);
    {rdelim});
</script>
