{**
 * plugins/generic/zaloNotification/templates/activityLog.tpl
 *
 * Activity Log viewer cho Zalo Notification Plugin
 * Thiết kế hiện đại: Thanh thông số KPI tinh gọn ngang, ngăn kéo chi tiết có thể thu gọn,
 * tối ưu tối đa không gian cho bảng log.
 *}

<style>
    .zalo-log-container {ldelim}
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 13px;
        line-height: 1.45;
        color: #1e293b;
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        height: calc(85vh - 30px);
        min-height: 600px;
        max-height: 860px;
        overflow: hidden;
    {rdelim}

    /* 1. Header */
    .zalo-log-header {ldelim}
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
        flex-wrap: wrap;
        gap: 8px;
    {rdelim}

    .zalo-header-left {ldelim}
        display: flex;
        align-items: center;
        gap: 10px;
    {rdelim}

    .zalo-header-title {ldelim}
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 6px;
    {rdelim}

    .zalo-header-retention {ldelim}
        font-size: 11px;
        color: #64748b;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 2px 8px;
    {rdelim}

    .zalo-header-right {ldelim}
        display: flex;
        align-items: center;
        gap: 8px;
    {rdelim}

    .zalo-stat-chip {ldelim}
        font-size: 12px;
        color: #475569;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        padding: 3px 9px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    {rdelim}

    .zalo-stat-chip strong {ldelim}
        color: #0f172a;
    {rdelim}

    .zalo-stat-chip.is-queue strong.text-danger {ldelim}
        color: #dc2626;
    {rdelim}

    /* 2. KPI Ribbon (Thanh thông số tinh gọn) */
    .zsw-container {ldelim}
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
    {rdelim}

    .zsw-ribbon {ldelim}
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 7px 16px;
        background: #f8fafc;
        flex-wrap: wrap;
        gap: 8px;
    {rdelim}

    .zsw-date-group {ldelim}
        display: flex;
        align-items: center;
        gap: 5px;
    {rdelim}

    .zsw-quick-date {ldelim}
        font-size: 11px;
        font-weight: 500;
        padding: 3px 9px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s ease;
    {rdelim}

    .zsw-quick-date:hover {ldelim}
        background: #f1f5f9;
        border-color: #94a3b8;
    {rdelim}

    .zsw-quick-date.active {ldelim}
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    {rdelim}

    .zsw-date-group input[type="date"] {ldelim}
        font-size: 11px;
        padding: 2px 7px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #ffffff;
        color: #334155;
        height: 25px;
    {rdelim}

    .zsw-btn-date-filter {ldelim}
        font-size: 11px;
        font-weight: 500;
        padding: 3px 8px;
        border: 1px solid #93c5fd;
        border-radius: 4px;
        background: #eff6ff;
        color: #1d4ed8;
        cursor: pointer;
    {rdelim}

    .zsw-btn-date-filter:hover {ldelim}
        background: #dbeafe;
    {rdelim}

    /* KPI Group */
    .zsw-kpi-group {ldelim}
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    {rdelim}

    .zsw-kpi-pill {ldelim}
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 5px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #334155;
        white-space: nowrap;
    {rdelim}

    .zsw-kpi-pill strong {ldelim}
        font-weight: 700;
        font-size: 12px;
    {rdelim}

    .zsw-kpi-pill.is-sent {ldelim}
        border-color: #e2e8f0;
        background: #f8fafc;
    {rdelim}

    .zsw-kpi-pill.is-delivered {ldelim}
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    {rdelim}

    .zsw-kpi-pill.is-failed {ldelim}
        border-color: #fecaca;
        background: #fef2f2;
        color: #991b1b;
    {rdelim}

    .zsw-kpi-pill.is-failed.has-alert {ldelim}
        background: #fee2e2;
        border-color: #f87171;
        color: #b91c1c;
        box-shadow: 0 0 0 1px #f87171;
    {rdelim}

    .zsw-kpi-pill.is-overdue {ldelim}
        border-color: #fed7aa;
        background: #fff7ed;
        color: #9a3412;
    {rdelim}

    .zsw-kpi-pill.is-overdue.has-alert {ldelim}
        background: #ffe4e6;
        border-color: #fb7185;
        color: #be123c;
        box-shadow: 0 0 0 1px #fb7185;
    {rdelim}

    .zsw-kpi-pill.is-duesoon {ldelim}
        border-color: #fef08a;
        background: #fefce8;
        color: #854d0e;
    {rdelim}

    .zsw-drawer-toggle {ldelim}
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #ffffff;
        color: #0284c7;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s ease;
    {rdelim}

    .zsw-drawer-toggle:hover {ldelim}
        background: #f0f9ff;
        border-color: #7dd3fc;
    {rdelim}

    /* 3. Collapsible Drawer (Chi tiết thống kê) */
    .zsw-drawer {ldelim}
        background: #f1f5f9;
        border-top: 1px solid #e2e8f0;
        padding: 10px 16px 14px 16px;
    {rdelim}

    .zsw-drawer-header {ldelim}
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    {rdelim}

    .zsw-drawer-title {ldelim}
        font-weight: 600;
        font-size: 12px;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 6px;
    {rdelim}

    .zsw-grid {ldelim}
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    {rdelim}

    .zsw-card {ldelim}
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 9px 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    {rdelim}

    .zsw-card-header {ldelim}
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #475569;
        letter-spacing: 0.03em;
        margin-bottom: 6px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    {rdelim}

    .zsw-badge-tag {ldelim}
        font-size: 10px;
        padding: 1px 6px;
        border-radius: 4px;
        background: #f1f5f9;
        color: #64748b;
        font-weight: normal;
        text-transform: none;
    {rdelim}

    .zsw-stats-list {ldelim}
        display: flex;
        flex-direction: column;
        gap: 3px;
    {rdelim}

    .zsw-stat-row {ldelim}
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: #475569;
    {rdelim}

    .zsw-stat-val {ldelim}
        font-weight: 700;
        font-size: 12px;
        color: #1e293b;
    {rdelim}

    .zsw-val-delivered {ldelim} color: #16a34a; {rdelim}
    .zsw-val-failed    {ldelim} color: #dc2626; {rdelim}
    .zsw-val-retrying  {ldelim} color: #d97706; {rdelim}
    .zsw-val-overdue   {ldelim} color: #dc2626; font-weight: 700; {rdelim}
    .zsw-val-duesoon   {ldelim} color: #d97706; {rdelim}

    .zsw-activity-list {ldelim}
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-height: 85px;
        overflow-y: auto;
    {rdelim}

    .zsw-activity-item {ldelim}
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    {rdelim}

    .zsw-act-icon {ldelim}
        font-weight: bold;
        display: inline-block;
        width: 14px;
        text-align: center;
        flex-shrink: 0;
    {rdelim}

    .zsw-act-icon.is-success {ldelim} color: #16a34a; {rdelim}
    .zsw-act-icon.is-danger  {ldelim} color: #dc2626; {rdelim}
    .zsw-act-icon.is-warning {ldelim} color: #d97706; {rdelim}

    .zsw-act-time {ldelim}
        color: #94a3b8;
        font-size: 10px;
        flex-shrink: 0;
    {rdelim}

    .zsw-act-label {ldelim}
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    {rdelim}

    .zsw-empty-act {ldelim}
        color: #94a3b8;
        font-size: 11px;
        font-style: italic;
        padding: 8px 0;
        text-align: center;
    {rdelim}

    /* 4. Toolbar */
    .zalo-log-toolbar {ldelim}
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 16px;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
        flex-wrap: wrap;
        gap: 10px;
    {rdelim}

    .zalo-toolbar-filters {ldelim}
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        flex: 1;
    {rdelim}

    .zalo-filter-label {ldelim}
        font-weight: 600;
        color: #475569;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    {rdelim}

    .zalo-select {ldelim}
        font-size: 12px;
        padding: 5px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #ffffff;
        color: #1e293b;
        cursor: pointer;
        min-width: 150px;
    {rdelim}

    .zalo-select:focus {ldelim}
        outline: none;
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    {rdelim}

    .zalo-search-box {ldelim}
        position: relative;
        display: inline-flex;
        align-items: center;
        min-width: 240px;
        flex: 1;
        max-width: 360px;
    {rdelim}

    .zalo-search-icon {ldelim}
        position: absolute;
        left: 9px;
        color: #94a3b8;
        font-size: 12px;
        pointer-events: none;
    {rdelim}

    .zalo-search-input {ldelim}
        width: 100%;
        font-size: 12px;
        padding: 5px 26px 5px 28px;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #ffffff;
        color: #1e293b;
        transition: all 0.15s ease;
    {rdelim}

    .zalo-search-input:focus {ldelim}
        outline: none;
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    {rdelim}

    .zalo-search-clear {ldelim}
        position: absolute;
        right: 6px;
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 14px;
        cursor: pointer;
        padding: 0 4px;
        line-height: 1;
    {rdelim}

    .zalo-search-clear:hover {ldelim}
        color: #475569;
    {rdelim}

    .zalo-toolbar-actions {ldelim}
        display: flex;
        align-items: center;
        gap: 6px;
    {rdelim}

    .zalo-btn {ldelim}
        font-size: 12px;
        font-weight: 500;
        padding: 5px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.15s ease;
    {rdelim}

    .zalo-btn:hover {ldelim}
        background: #f8fafc;
        border-color: #94a3b8;
    {rdelim}

    .zalo-btn-primary {ldelim}
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    {rdelim}

    .zalo-btn-primary:hover {ldelim}
        background: #0369a1;
        border-color: #0369a1;
    {rdelim}

    /* 5. Table Wrap & Table */
    .zalo-log-table-wrap {ldelim}
        flex: 1;
        min-height: 380px;
        overflow-y: auto;
        overflow-x: auto;
        background: #ffffff;
    {rdelim}

    .zalo-log-table {ldelim}
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    {rdelim}

    .zalo-log-table thead th {ldelim}
        position: sticky;
        top: 0;
        z-index: 5;
        background: #f1f5f9;
        padding: 9px 12px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    {rdelim}

    .zalo-log-table tbody td {ldelim}
        padding: 8px 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: top;
        font-size: 12px;
    {rdelim}

    .zalo-log-table tbody tr {ldelim}
        transition: background-color 0.1s ease;
    {rdelim}

    .zalo-log-table tbody tr:hover {ldelim}
        background: #f8fafc;
    {rdelim}

    .zalo-log-table .col-time {ldelim}
        white-space: nowrap;
        color: #64748b;
        font-size: 11px;
    {rdelim}

    .zalo-log-table .col-id {ldelim}
        text-align: center;
        font-weight: 600;
        color: #0369a1;
    {rdelim}

    .zalo-log-table .col-title {ldelim}
        color: #0f172a;
        font-weight: 500;
        word-break: break-word;
    {rdelim}

    .zalo-log-table .col-detail {ldelim}
        font-size: 12px;
        line-height: 1.4;
    {rdelim}

    /* Badges */
    .badge {ldelim}
        display: inline-block;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        white-space: nowrap;
    {rdelim}

    .badge-info    {ldelim} background: #dbeafe; color: #1d4ed8; {rdelim}
    .badge-warning {ldelim} background: #fef3c7; color: #b45309; {rdelim}
    .badge-error   {ldelim} background: #fee2e2; color: #dc2626; {rdelim}

    .badge-submission       {ldelim} background: #d1fae5; color: #065f46; {rdelim}
    .badge-decision         {ldelim} background: #e0e7ff; color: #3730a3; {rdelim}
    .badge-publish          {ldelim} background: #fce7f3; color: #9d174d; {rdelim}
    .badge-unpublish        {ldelim} background: #fee2e2; color: #991b1b; {rdelim}
    .badge-zalo             {ldelim} background: #cffafe; color: #0e7490; {rdelim}
    .badge-err-type         {ldelim} background: #fee2e2; color: #dc2626; {rdelim}
    .badge-reminder         {ldelim} background: #fff7ed; color: #9a3412; {rdelim}
    .badge-review-response  {ldelim} background: #ecfeff; color: #0e7490; {rdelim}
    .badge-review-completed {ldelim} background: #dcfce7; color: #166534; {rdelim}

    /* Detail block & toggle */
    .zalo-detail-toggle {ldelim}
        display: inline-flex;
        align-items: center;
        gap: 3px;
        margin-top: 3px;
        cursor: pointer;
        color: #0284c7;
        font-size: 11px;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid #e0f2fe;
        background: #f0f9ff;
        padding: 1px 6px;
        border-radius: 4px;
        transition: all 0.15s ease;
    {rdelim}

    .zalo-detail-toggle:hover {ldelim}
        background: #e0f2fe;
        color: #0369a1;
    {rdelim}

    .zalo-detail-content {ldelim}
        display: none;
        margin-top: 6px;
        padding: 8px 10px;
        background: #0f172a;
        color: #f8fafc;
        border-radius: 5px;
        font-size: 11px;
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        max-height: 180px;
        overflow-y: auto;
        word-break: break-all;
    {rdelim}

    /* Empty state */
    .zalo-log-empty {ldelim}
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    {rdelim}

    .zalo-log-empty .empty-icon {ldelim}
        font-size: 40px;
        margin-bottom: 8px;
    {rdelim}

    /* 6. Pagination */
    .zalo-log-pagination {ldelim}
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 8px 16px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        flex-shrink: 0;
    {rdelim}

    .zalo-log-pagination button {ldelim}
        min-width: 82px;
        padding: 5px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        color: #334155;
        background: #ffffff;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    {rdelim}

    .zalo-log-pagination button:hover:not(:disabled) {ldelim}
        color: #0284c7;
        border-color: #7dd3fc;
        background: #f0f9ff;
    {rdelim}

    .zalo-log-pagination button:disabled {ldelim}
        opacity: 0.45;
        cursor: default;
    {rdelim}

    .zalo-log-page-info {ldelim}
        min-width: 150px;
        color: #64748b;
        text-align: center;
        font-size: 12px;
    {rdelim}

    @media (max-width: 900px) {ldelim}
        .zsw-grid {ldelim} grid-template-columns: 1fr; {rdelim}
        .zsw-ribbon {ldelim} flex-direction: column; align-items: flex-start; {rdelim}
        .zalo-log-toolbar {ldelim} flex-direction: column; align-items: stretch; {rdelim}
        .zalo-search-box {ldelim} max-width: 100%; {rdelim}
    {rdelim}
</style>

<div class="zalo-log-container">
    {* 1. Header Bar *}
    <div class="zalo-log-header">
        <div class="zalo-header-left">
            <h3 class="zalo-header-title">
                <span style="color:#0284c7;">📋</span>
                <span>Activity Log — Zalo Notification</span>
            </h3>
            <span class="zalo-header-retention" title="Nhật ký được bảo vệ và tự động xoay vòng sau 90 ngày">
                🛡️ Lưu 90 ngày
            </span>
        </div>
        <div class="zalo-header-right">
            <span class="zalo-stat-chip" title="Tổng số bản ghi trong thời hạn lưu trữ">
                📊 <strong>{$totalEntries}</strong> bản ghi
            </span>
            <span class="zalo-stat-chip is-queue" title="Trạng thái hàng đợi tin nhắn hiện tại">
                ⏳ Hàng đợi: <strong>{$outboxStats.pending}</strong> chờ, 
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
                <button type="button" class="zsw-quick-date {if $dailyStats.is_today}active{/if}" data-date="today">Hôm nay</button>
                <button type="button" class="zsw-quick-date" data-date="yesterday">Hôm qua</button>
                <input type="date" id="zswDatePicker" value="{$selectedStatsDate|escape}" title="Chọn ngày xem thống kê">
                <button type="button" id="zswBtnFilterByDate" class="zsw-btn-date-filter" title="Lọc bảng log theo ngày này">
                    🔍 Lọc bảng
                </button>
            </div>

            <div class="zsw-kpi-group">
                <span class="zsw-kpi-pill is-sent" title="Tổng tin Zalo đã gửi trong ngày">
                    📤 Gửi: <strong id="kpiSent">{$dailyStats.sent}</strong>
                </span>
                <span class="zsw-kpi-pill is-delivered" title="Gửi thành công">
                    ✅ Thành công: <strong id="kpiDelivered">{$dailyStats.delivered}</strong>
                </span>
                <span class="zsw-kpi-pill is-failed {if $dailyStats.failed > 0}has-alert{/if}" title="Gửi lỗi">
                    ❌ Lỗi: <strong id="kpiFailed">{$dailyStats.failed}</strong>
                </span>
                <span class="zsw-kpi-pill is-overdue {if $dailyStats.reviewer_reminders.overdue > 0}has-alert{/if}" title="Phản biện đang quá hạn">
                    🚨 PB Quá hạn: <strong id="kpiOverdue">{$dailyStats.reviewer_reminders.overdue}</strong>
                </span>
                <span class="zsw-kpi-pill is-duesoon" title="Phản biện đến hạn trong 3 ngày">
                    ⏰ PB Sắp hạn: <strong id="kpiDueSoon">{$dailyStats.reviewer_reminders.due_in_3_days}</strong>
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
                    <span>📈 Chi tiết phân tích ngày</span>
                    <span id="zswDateLabel" style="font-weight:700;color:#0284c7;">({$dailyStats.date|escape})</span>
                </div>
                <small style="color:#64748b;">Số liệu tổng hợp tự động từ nhật ký và hàng đợi tin nhắn</small>
            </div>

            <div class="zsw-grid" id="zswGrid">
                {* Card 1: Gửi tin Zalo *}
                <div class="zsw-card">
                    <div class="zsw-card-header">
                        <span>💬 Gửi tin Zalo</span>
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
                        <span>⏰ Nhắc phản biện</span>
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
                        <span>⚡ Hoạt động trong ngày</span>
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
                <option value="SUBMISSION" {if $filterType == 'SUBMISSION'}selected{/if}>📥 Nộp bài</option>
                <option value="DECISION" {if $filterType == 'DECISION'}selected{/if}>📌 Quyết định</option>
                <option value="PUBLISH" {if $filterType == 'PUBLISH'}selected{/if}>🎉 Xuất bản</option>
                <option value="UNPUBLISH" {if $filterType == 'UNPUBLISH'}selected{/if}>⚠️ Hủy xuất bản</option>
                <option value="ZALO_SEND" {if $filterType == 'ZALO_SEND'}selected{/if}>💬 Gửi Zalo</option>
                <option value="ERROR" {if $filterType == 'ERROR'}selected{/if}>❌ Lỗi</option>
                <option value="REVIEW_REQUEST" {if $filterType == 'REVIEW_REQUEST'}selected{/if}>🔔 Mời PB</option>
                <option value="REVIEW_RESPONSE" {if $filterType == 'REVIEW_RESPONSE'}selected{/if}>↪ PB phản hồi</option>
                <option value="REVIEW_REMINDER" {if $filterType == 'REVIEW_REMINDER'}selected{/if}>⏰ Nhắc hạn PB</option>
                <option value="REVIEW_COMPLETED" {if $filterType == 'REVIEW_COMPLETED'}selected{/if}>✅ PB đã nộp</option>
            </select>

            <div class="zalo-search-box">
                <span class="zalo-search-icon">🔍</span>
                <input type="text" id="zaloSearchInput" class="zalo-search-input" placeholder="Tìm kiếm ID, tiêu đề, tên tác giả/biên tập...">
                <button type="button" id="zaloSearchClear" class="zalo-search-clear" style="display:none;" title="Xóa tìm kiếm">×</button>
            </div>
        </div>

        <div class="zalo-toolbar-actions">
            <button type="button" id="zaloBtnRefresh" class="zalo-btn" title="Làm mới bảng log">🔄 Làm mới</button>
            <button type="button" id="zaloBtnExport" class="zalo-btn zalo-btn-primary" title="Tải xuống tệp nhật ký TXT">📥 Xuất file log</button>
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
                                    <span class="badge badge-submission">📥 Nộp bài</span>
                                {elseif $entry.type == 'DECISION'}
                                    <span class="badge badge-decision">📌 Quyết định</span>
                                {elseif $entry.type == 'PUBLISH'}
                                    <span class="badge badge-publish">🎉 Xuất bản</span>
                                {elseif $entry.type == 'UNPUBLISH'}
                                    <span class="badge badge-unpublish">⚠️ Hủy XB</span>
                                {elseif $entry.type == 'ZALO_SEND'}
                                    <span class="badge badge-zalo">💬 Zalo</span>
                                {elseif $entry.type == 'ERROR'}
                                    <span class="badge badge-err-type">❌ Lỗi</span>
                                {elseif $entry.type == 'REVIEW_REQUEST'}
                                    <span class="badge badge-reminder">🔔 Mời PB</span>
                                {elseif $entry.type == 'REVIEW_RESPONSE'}
                                    <span class="badge badge-review-response">↪ PB phản hồi</span>
                                {elseif $entry.type == 'REVIEW_REMINDER'}
                                    <span class="badge badge-reminder">⏰ Nhắc hạn</span>
                                {elseif $entry.type == 'REVIEW_COMPLETED'}
                                    <span class="badge badge-review-completed">✓ PB đã nộp</span>
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
                                        <div>👤 {$entry.extra.performed_by|escape}</div>
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
                                        <div style="color:#dc2626;">⚠️ {$entry.extra.message|escape|truncate:80:"..."}</div>
                                    {/if}
                                    {if isset($entry.extra.reviewerName)}
                                        <div>👤 Reviewer: {$entry.extra.reviewerName|escape}</div>
                                    {elseif isset($entry.extra.reviewer)}
                                        <div>👤 Reviewer: {$entry.extra.reviewer|escape}</div>
                                    {/if}
                                    {if isset($entry.extra.deadline)}
                                        <div>📅 Hạn: {$entry.extra.deadline|escape}
                                            {if isset($entry.extra.daysLeft)}
                                                ({if $entry.extra.daysLeft < 0}<span style="color:#dc2626;font-weight:600;">quá hạn</span>{elseif $entry.extra.daysLeft == 0}<span style="color:#d97706;font-weight:600;">HÔM NAY</span>{else}còn {$entry.extra.daysLeft} ngày{/if})
                                            {/if}
                                        </div>
                                    {elseif isset($entry.extra.date_due)}
                                        <div>📅 Hạn: {$entry.extra.date_due|escape}
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
