<form id="zaloOutboxActionForm" method="post" action="{$outboxActionUrl|escape}">{csrf}</form>

<div class="zalo-outbox-dashboard">
    {* Header & Summary *}
    <div class="zod-heading">
        <div>
            <h3><i class="fa fa-refresh"></i> Dashboard Hàng đợi & Thử lại tin nhắn (Zalo Outbox)</h3>
            <p>Theo dõi các tin nhắn trong hàng đợi, trạng thái gửi, lỗi kỹ thuật và chủ động thử lại hoặc hủy gửi.</p>
        </div>
        <div class="zod-metrics">
            <span class="zod-metric-item is-pending"><i class="fa fa-clock-o"></i> <strong>{$stats.pending}</strong> Chờ gửi</span>
            <span class="zod-metric-item is-retry"><i class="fa fa-history"></i> <strong>{$stats.retry}</strong> Đang thử lại</span>
            <span class="zod-metric-item is-failed"><i class="fa fa-exclamation-triangle"></i> <strong>{$stats.failed}</strong> Thất bại</span>
            <span class="zod-metric-item is-sent"><i class="fa fa-check-circle"></i> <strong>{$stats.sent}</strong> Đã gửi</span>
            <span class="zod-metric-item" title="Tin nhắn đã gửi hoặc thất bại cũ hơn {$outboxRetentionDays|default:30} ngày sẽ được tự động dọn dẹp"><i class="fa fa-archive"></i> Lưu {$outboxRetentionDays|default:30} ngày</span>
        </div>
    </div>

    {* Notice bar *}
    <div id="zodNotice" class="zod-notice" role="status" aria-live="polite"></div>

    {* Toolbar *}
    <div class="zod-tools">
        <label class="zod-search">
            <i class="fa fa-search"></i>
            <input type="search" id="zodSearch" placeholder="Tìm theo sự kiện, SĐT người nhận, nội dung hoặc lỗi..." />
        </label>
        <select id="zodStatusFilter" aria-label="Lọc theo trạng thái hàng đợi">
            <option value="all" {if $currentFilter == 'all'}selected{/if}>Tất cả trạng thái ({$totalItems})</option>
            <option value="active_queue" {if $currentFilter == 'active_queue'}selected{/if}>Đang trong hàng đợi ({$stats.pending + $stats.retry + $stats.processing})</option>
            <option value="pending" {if $currentFilter == 'pending'}selected{/if}>Chờ gửi ({$stats.pending})</option>
            <option value="retry" {if $currentFilter == 'retry'}selected{/if}>Đang thử lại ({$stats.retry})</option>
            <option value="failed" {if $currentFilter == 'failed'}selected{/if}>Thất bại ({$stats.failed})</option>
            <option value="sent" {if $currentFilter == 'sent'}selected{/if}>Đã gửi thành công ({$stats.sent})</option>
        </select>
        <div class="zod-bulk-actions">
            <button type="button" class="pkp_button pkp_button_primary" id="zodRetryAllBtn" {if ($stats.failed + $stats.retry) == 0}disabled title="Không có tin lỗi cần thử lại"{/if}>
                <i class="fa fa-paper-plane"></i> Thử lại tất cả tin lỗi ({$stats.failed + $stats.retry})
            </button>
            <button type="button" class="pkp_button" id="zodClearSentBtn" {if $stats.sent == 0}disabled title="Không có tin đã gửi"{/if}>
                <i class="fa fa-trash"></i> Dọn tin đã gửi ({$stats.sent})
            </button>
            <button type="button" class="pkp_button" id="zodRefreshBtn">
                <i class="fa fa-refresh"></i> Làm mới
            </button>
        </div>
    </div>

    <div class="zod-result-count" id="zodResultCount"></div>
    <div class="zod-no-results" id="zodNoResults"><i class="fa fa-search"></i> Không tìm thấy tin nhắn phù hợp với điều kiện lọc.</div>

    {if empty($entries)}
        <div class="zod-empty"><i class="fa fa-check-circle"></i> Hàng đợi trống. Không có tin nhắn nào cần xử lý.</div>
    {else}
        <div class="zod-table-wrap">
            <table class="zod-table" id="zodTable">
                <thead>
                    <tr>
                        <th class="zod-th-id" style="width:70px;">ID</th>
                        <th class="zod-th-event" style="width:160px;">Sự kiện</th>
                        <th class="zod-th-recipients" style="width:160px;">Người nhận</th>
                        <th class="zod-th-message" style="min-width:330px;">Nội dung tin nhắn</th>
                        <th class="zod-th-status" style="width:145px;">Trạng thái & Thử</th>
                        <th class="zod-th-time" style="width:160px;">Thời gian</th>
                        <th class="zod-th-actions" style="width:130px; text-align:right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$entries item=item}
                    <tr class="zod-row" data-id="{$item.outbox_id}" data-status="{$item.status|escape}" data-event="{$item.event_type|escape}">
                        <td class="zod-col-id">
                            <span class="zod-id-badge">#{$item.outbox_id}</span>
                        </td>
                        <td class="zod-col-event">
                            <span class="zod-badge zod-badge-event" title="{$item.event_type|escape}">
                                <i class="fa fa-bell-o"></i> {$item.event_type|escape}
                            </span>
                        </td>
                        <td class="zod-col-recipients">
                            <div class="zod-recipients-wrap">
                                {if $item.recipient_count > 0}
                                    <div class="zod-recipients-header">
                                        <i class="fa fa-users"></i> <strong>{$item.recipient_count}</strong> người nhận
                                    </div>
                                    <div class="zod-phones">
                                        {foreach from=$item.recipients item=phone}
                                            <span class="zod-phone-badge" title="Số điện thoại nhận tin">
                                                <i class="fa fa-phone"></i> {$phone|escape}
                                            </span>
                                        {/foreach}
                                    </div>
                                {else}
                                    {if $item.status == 'sent'}
                                        <div class="zod-recipients-sent" title="Tin nhắn đã gửi thành công (dữ liệu số điện thoại được dọn dẹp tự động để bảo mật)">
                                            <i class="fa fa-check-circle"></i> Đã hoàn tất
                                            <small>Bảo mật SĐT sau gửi</small>
                                        </div>
                                    {else}
                                        <span class="zod-recipients-empty" title="Chưa có thông tin số điện thoại người nhận">
                                            <i class="fa fa-minus-circle"></i> 0 người nhận
                                        </span>
                                    {/if}
                                {/if}
                            </div>
                        </td>
                        <td class="zod-col-message">
                            {if !empty($item.message_text)}
                                <div class="zod-message-preview">
                                    {$item.message_text|escape|nl2br}
                                </div>
                            {else}
                                <div class="zod-message-cleared" title="Nội dung đã được dọn dẹp hoặc xóa bảo mật sau khi gửi">
                                    <i class="fa fa-shield"></i>
                                    <span>Nội dung đã dọn dẹp bảo mật sau khi gửi</span>
                                </div>
                            {/if}
                            {if $item.last_error}
                                <div class="zod-error-box" title="Chi tiết thông báo lỗi từ Zalo Gateway">
                                    <div class="zod-error-header">
                                        <i class="fa fa-exclamation-triangle"></i> <strong>Lỗi gửi:</strong>
                                    </div>
                                    <div class="zod-error-content">{$item.last_error|escape}</div>
                                </div>
                            {/if}
                        </td>
                        <td class="zod-col-status">
                            <div class="zod-status-wrap">
                                {if $item.status == 'sent'}
                                    <span class="zod-badge zod-badge-sent"><i class="fa fa-check-circle"></i> Đã gửi</span>
                                {elseif $item.status == 'failed'}
                                    <span class="zod-badge zod-badge-failed"><i class="fa fa-times-circle"></i> Thất bại</span>
                                {elseif $item.status == 'retry'}
                                    <span class="zod-badge zod-badge-retry"><i class="fa fa-refresh"></i> Đang thử lại</span>
                                {elseif $item.status == 'processing'}
                                    <span class="zod-badge zod-badge-processing"><i class="fa fa-spinner fa-spin"></i> Đang gửi</span>
                                {else}
                                    <span class="zod-badge zod-badge-pending"><i class="fa fa-hourglass-half"></i> Chờ gửi</span>
                                {/if}
                                <div class="zod-attempts-info">
                                    <span class="zod-attempts-chip">
                                        <i class="fa fa-repeat"></i> Đã thử: <strong>{$item.attempts}</strong>/{$item.max_attempts}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="zod-col-time">
                            <div class="zod-time-item" title="Thời gian tạo tin"><i class="fa fa-calendar-o"></i> {$item.created_at|escape}</div>
                            {if $item.status == 'retry'}
                                <div class="zod-next-try" title="Thời điểm tự động thử lại tiếp theo"><i class="fa fa-clock-o"></i> Retry: {$item.available_at|escape}</div>
                            {elseif $item.sent_at}
                                <div class="zod-sent-time" title="Thời điểm gửi thành công"><i class="fa fa-check"></i> Gửi: {$item.sent_at|escape}</div>
                            {/if}
                        </td>
                        <td class="zod-col-actions">
                            <div class="zod-action-buttons">
                                {if $item.status != 'sent'}
                                    <button type="button" class="pkp_button pkp_button_primary zod-btn-retry" data-id="{$item.outbox_id}" title="Thử gửi lại ngay lập tức">
                                        <i class="fa fa-paper-plane"></i> Thử lại
                                    </button>
                                {/if}
                                <button type="button" class="pkp_button zod-btn-delete" data-id="{$item.outbox_id}" title="Xóa bản ghi này khỏi hàng đợi">
                                    <i class="fa fa-trash-o"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>

        <nav class="zod-pagination" id="zodPagination" aria-label="Phân trang hàng đợi outbox">
            <button type="button" class="pkp_button" id="zodPrev"><i class="fa fa-chevron-left"></i> Trước</button>
            <span id="zodPageInfo"></span>
            <button type="button" class="pkp_button" id="zodNext">Sau <i class="fa fa-chevron-right"></i></button>
        </nav>
    {/if}
</div>

{literal}
<style>
/* Card container */
.zalo-outbox-dashboard {
    padding: 16px 18px 24px;
    color: #1e293b;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

/* Heading */
.zod-heading {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f1f5f9;
}
.zod-heading h3 {
    color: #0f172a;
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.zod-heading h3 i {
    color: #0284c7;
}
.zod-heading p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.4;
}

/* Metrics pills */
.zod-metrics {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.zod-metric-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 12px;
    white-space: nowrap;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .15s ease;
}
.zod-metric-item strong {
    font-weight: 700;
}
.zod-metric-item.is-pending { background: #fefce8; border-color: #fef08a; color: #a16207; }
.zod-metric-item.is-retry   { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.zod-metric-item.is-failed  { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.zod-metric-item.is-sent    { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
.zod-metric-item.is-retention { background: #f8fafc; border-color: #e2e8f0; color: #475569; }

/* Notice bar */
.zod-notice {
    display: none;
    margin: 0 0 16px;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
}
.zod-notice.success { display: block; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.zod-notice.error   { display: block; background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }

/* Toolbar */
.zod-tools {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    padding: 12px 14px;
    margin: 0 0 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.zod-search {
    position: relative;
    flex: 1;
    min-width: 260px;
    margin: 0;
}
.zod-search i {
    position: absolute;
    left: 12px;
    top: 11px;
    color: #94a3b8;
    font-size: 13px;
}
.zod-search input {
    box-sizing: border-box;
    width: 100%;
    height: 36px;
    padding: 0 12px 0 34px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    font-size: 12.5px;
    color: #1e293b;
    outline: none;
    transition: all 0.15s ease;
}
.zod-search input:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.zod-tools select {
    height: 36px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    padding: 0 12px;
    font-size: 12.5px;
    color: #334155;
    outline: none;
    cursor: pointer;
    transition: all 0.15s ease;
}
.zod-tools select:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.zod-bulk-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.zod-bulk-actions button {
    height: 36px;
    padding: 0 13px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .15s ease;
}

/* Result count & no results */
.zod-result-count {
    color: #64748b;
    font-size: 12px;
    margin: 4px 4px 10px;
    font-weight: 500;
}
.zod-no-results {
    display: none;
    text-align: center;
    padding: 35px 20px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #64748b;
    font-size: 13px;
}

/* TABLE WRAP & TABLE STYLING - Anti-distortion Guarantee */
.zod-table-wrap {
    overflow-x: auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    -webkit-overflow-scrolling: touch;
}
.zod-table-wrap::-webkit-scrollbar {
    height: 8px;
}
.zod-table-wrap::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.zod-table-wrap::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.zod-table-wrap::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.zod-table {
    width: 100%;
    min-width: 1080px; /* Crucial: Prevents any column squishing */
    border-collapse: collapse;
    font-size: 12.5px;
    text-align: left;
}
.zod-table th {
    background: #f8fafc;
    padding: 12px 14px;
    font-weight: 700;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: 11px;
    white-space: nowrap !important; /* Prevents word-breaking like NỘI \n DUNG */
}
.zod-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #334155;
    background: #ffffff;
    line-height: 1.45;
}
.zod-table tbody tr {
    transition: background-color 0.12s ease;
}
.zod-table tbody tr:hover td {
    background: #f8fafc;
}

/* Col: ID */
.zod-col-id {
    text-align: center;
}
.zod-id-badge {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 4px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
}

/* Badges */
.zod-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border-radius: 5px;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.01em;
    white-space: nowrap;
}
.zod-badge-event      { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 11px; }
.zod-badge-sent       { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.zod-badge-failed     { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.zod-badge-retry      { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.zod-badge-pending    { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.zod-badge-processing { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }

/* Col: Recipients */
.zod-recipients-wrap {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.zod-recipients-header {
    font-size: 12px;
    color: #1e293b;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}
.zod-recipients-header i {
    color: #64748b;
}
.zod-phones {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.zod-phone-badge {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 11px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    color: #334155;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    letter-spacing: 0.02em;
}
.zod-phone-badge i {
    font-size: 10px;
    color: #0284c7;
}
.zod-recipients-sent {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: #15803d;
    font-size: 12px;
    font-weight: 600;
}
.zod-recipients-sent i {
    color: #16a34a;
}
.zod-recipients-sent small {
    color: #64748b;
    font-size: 10.5px;
    font-weight: 400;
}
.zod-recipients-empty {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #94a3b8;
    font-size: 11.5px;
    font-style: italic;
}

/* Col: Message & Preview */
.zod-col-message {
    min-width: 330px;
}
.zod-message-preview {
    max-height: 90px;
    overflow-y: auto;
    padding: 8px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    color: #1e293b;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.02);
}
.zod-message-preview::-webkit-scrollbar {
    width: 5px;
}
.zod-message-preview::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.zod-message-preview::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
.zod-message-cleared {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    color: #64748b;
    font-size: 11.5px;
    font-style: italic;
    width: 100%;
    box-sizing: border-box;
}
.zod-message-cleared i {
    color: #0ea5e9;
    font-style: normal;
}
.zod-error-box {
    margin-top: 6px;
    padding: 8px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 4px;
    color: #991b1b;
    font-size: 11.5px;
    line-height: 1.45;
    word-break: break-word;
}
.zod-error-header {
    font-weight: 700;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #dc2626;
}

/* Col: Status & Attempts */
.zod-status-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-start;
}
.zod-attempts-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}
.zod-attempts-chip strong {
    color: #0f172a;
}

/* Col: Time */
.zod-col-time {
    color: #475569;
    font-size: 12px;
    white-space: nowrap;
}
.zod-time-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #334155;
    font-weight: 500;
}
.zod-time-item i {
    color: #64748b;
}
.zod-next-try {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    color: #b45309;
    font-weight: 600;
    font-size: 11px;
    background: #fef3c7;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid #fde68a;
    width: fit-content;
}
.zod-sent-time {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    color: #15803d;
    font-size: 11px;
    font-weight: 500;
}

/* Col: Actions */
.zod-col-actions {
    text-align: right;
}
.zod-action-buttons {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    justify-content: flex-end;
}
button.zod-btn-retry {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 5px;
    height: 30px;
    padding: 0 10px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    color: #ffffff !important;
    background: #0284c7 !important;
    border: 1px solid #0284c7 !important;
    border-radius: 5px !important;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(2, 132, 199, 0.2);
}
button.zod-btn-retry:hover {
    background: #0369a1 !important;
    border-color: #0369a1 !important;
}
button.zod-btn-delete {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0 !important;
    font-size: 13px !important;
    color: #e11d48 !important;
    background: #ffffff !important;
    border: 1px solid #fecdd3 !important;
    border-radius: 5px !important;
    cursor: pointer;
    transition: all 0.15s ease;
}
button.zod-btn-delete:hover {
    background: #fff1f2 !important;
    border-color: #fda4af !important;
    color: #be123c !important;
}

/* Empty State */
.zod-empty {
    text-align: center;
    padding: 40px 20px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    color: #166534;
    font-size: 14px;
    font-weight: 500;
}
.zod-empty i {
    font-size: 18px;
    margin-right: 6px;
    vertical-align: middle;
}

/* Pagination */
.zod-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin: 18px 0 6px;
}
.zod-pagination button {
    height: 32px;
    padding: 0 14px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.zod-pagination button:hover:not([disabled]) {
    background: #f1f5f9;
    border-color: #94a3b8;
}
.zod-pagination button[disabled] {
    opacity: 0.45;
    cursor: not-allowed;
}
.zod-pagination span {
    min-width: 110px;
    text-align: center;
    color: #475569;
    font-size: 12.5px;
    font-weight: 600;
}

@media(max-width: 800px) {
    .zod-heading { flex-direction: column; align-items: stretch; }
    .zod-metrics { justify-content: flex-start; }
    .zod-tools > * { width: 100%; }
    .zod-bulk-actions { justify-content: space-between; }
}
</style>

<script>
$(function(){
    var $form = $('#zaloOutboxActionForm');
    var $notice = $('#zodNotice');
    var $table = $('#zodTable');
    var $rows = $('.zod-row');
    var page = 1;
    var pageSize = 10;
    var filteredRows = [];

    function showNotice(type, text) {
        $notice.removeClass('success error').addClass(type).html(text).show();
        setTimeout(function(){
            $notice.fadeOut();
        }, 6000);
    }

    function normalizeText(val) {
        return (val || '').toString().toLowerCase();
    }

    function applyFilters() {
        var query = normalizeText($('#zodSearch').val());
        var statusFilter = $('#zodStatusFilter').val();

        filteredRows = [];
        $rows.each(function(){
            var $tr = $(this);
            var rowStatus = $tr.data('status');
            var rowText = normalizeText($tr.text());

            var matchesStatus = true;
            if (statusFilter === 'active_queue') {
                matchesStatus = (rowStatus === 'pending' || rowStatus === 'retry' || rowStatus === 'processing');
            } else if (statusFilter !== 'all') {
                matchesStatus = (rowStatus === statusFilter);
            }

            var matchesQuery = (!query || rowText.indexOf(query) !== -1);

            if (matchesStatus && matchesQuery) {
                filteredRows.push($tr);
            }
        });

        var total = filteredRows.length;
        $('#zodResultCount').text('Hiển thị ' + total + ' trên tổng số ' + $rows.length + ' bản ghi');

        if (total === 0) {
            $('#zodNoResults').show();
            $('#zodPagination').hide();
            $rows.hide();
        } else {
            $('#zodNoResults').hide();
            $('#zodPagination').show();
            renderPage();
        }
    }

    function renderPage() {
        var total = filteredRows.length;
        var maxPage = Math.max(1, Math.ceil(total / pageSize));
        if (page > maxPage) page = maxPage;
        if (page < 1) page = 1;

        $rows.hide();
        var start = (page - 1) * pageSize;
        var end = Math.min(start + pageSize, total);

        for (var i = start; i < end; i++) {
            filteredRows[i].show();
        }

        $('#zodPageInfo').text('Trang ' + page + ' / ' + maxPage);
        $('#zodPrev').prop('disabled', page <= 1);
        $('#zodNext').prop('disabled', page >= maxPage);
    }

    $('#zodSearch').on('input', function(){
        page = 1;
        applyFilters();
    });

    $('#zodStatusFilter').on('change', function(){
        page = 1;
        applyFilters();
    });

    $('#zodPrev').on('click', function(){
        if (page > 1) {
            page--;
            renderPage();
        }
    });

    $('#zodNext').on('click', function(){
        page++;
        renderPage();
    });

    // Helper: Execute AJAX action via manage endpoint
    function sendOutboxAction(verb, data, $btn, successCallback) {
        if ($btn) $btn.prop('disabled', true);
        var postData = $.extend({}, data, {
            csrfToken: $form.find('input[name="csrfToken"]').val(),
            verb: verb
        });

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                if ($btn) $btn.prop('disabled', false);
                if (res && res.status) {
                    showNotice('success', res.content || 'Thao tác thành công.');
                    if (successCallback) successCallback(res);
                } else {
                    showNotice('error', (res && res.content) ? res.content : 'Thao tác thất bại.');
                }
            },
            error: function() {
                if ($btn) $btn.prop('disabled', false);
                showNotice('error', 'Lỗi kết nối máy chủ khi thực hiện thao tác.');
            }
        });
    }

    // Single item retry
    $(document).on('click', '.zod-btn-retry', function(){
        var $btn = $(this);
        var outboxId = $btn.data('id');
        sendOutboxAction('retryOutboxItem', { outboxId: outboxId }, $btn, function(){
            setTimeout(function(){ location.reload(); }, 1000);
        });
    });

    // Single item delete
    $(document).on('click', '.zod-btn-delete', function(){
        if (!confirm('Bạn có chắc chắn muốn xóa tin nhắn này khỏi hàng đợi?')) {
            return;
        }
        var $btn = $(this);
        var outboxId = $btn.data('id');
        sendOutboxAction('deleteOutboxItem', { outboxId: outboxId }, $btn, function(){
            $btn.closest('tr').remove();
            $rows = $('.zod-row');
            applyFilters();
        });
    });

    // Retry all failed items
    $('#zodRetryAllBtn').on('click', function(){
        if (!confirm('Bạn có chắc chắn muốn đưa tất cả tin nhắn lỗi về trạng thái Chờ gửi để thử lại?')) {
            return;
        }
        var $btn = $(this);
        sendOutboxAction('retryAllOutbox', {}, $btn, function(){
            setTimeout(function(){ location.reload(); }, 1200);
        });
    });

    // Clear sent items
    $('#zodClearSentBtn').on('click', function(){
        if (!confirm('Bạn có muốn xóa tất cả tin nhắn đã gửi thành công để dọn dẹp cơ sở dữ liệu?')) {
            return;
        }
        var $btn = $(this);
        sendOutboxAction('clearSentOutbox', {}, $btn, function(){
            setTimeout(function(){ location.reload(); }, 1000);
        });
    });

    // Refresh view
    $('#zodRefreshBtn').on('click', function(){
        location.reload();
    });

    // Initial filter execution
    applyFilters();
});
</script>
{/literal}
