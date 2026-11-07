<form id="zaloOutboxActionForm" method="post" action="{$outboxActionUrl|escape}">{csrf}</form>

<div class="zalo-outbox-dashboard">
    {* Header & Summary *}
    <div class="zod-heading">
        <div>
            <h3><i class="fa fa-paper-plane" style="color:#0066a6;"></i> Dashboard Hàng đợi & Thử lại tin nhắn (Zalo Outbox)</h3>
            <p>Theo dõi các tin nhắn trong hàng đợi, trạng thái gửi, lỗi kỹ thuật và chủ động thử lại hoặc hủy gửi.</p>
        </div>
        <div class="zod-metrics">
            <span class="zod-metric-item is-pending"><strong>{$stats.pending}</strong> Chờ gửi</span>
            <span class="zod-metric-item is-retry"><strong>{$stats.retry}</strong> Đang thử lại</span>
            <span class="zod-metric-item is-failed"><strong>{$stats.failed}</strong> Thất bại</span>
            <span class="zod-metric-item is-sent"><strong>{$stats.sent}</strong> Đã gửi</span>
            <span class="zod-metric-item" title="Tin nhắn đã gửi hoặc thất bại cũ hơn {$outboxRetentionDays|default:30} ngày sẽ được tự động dọn dẹp">Lưu <strong>{$outboxRetentionDays|default:30}</strong> ngày</span>
        </div>
    </div>

    {* Notice bar *}
    <div id="zodNotice" class="zod-notice" role="status" aria-live="polite"></div>

    {if $hasStalePending}
        <div style="margin-bottom: 16px; padding: 12px 16px; background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; color: #92400e; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-exclamation-triangle" style="font-size: 18px; color: #d97706; flex-shrink: 0;"></i>
            <div>
                <strong>Cảnh báo tiến trình ngầm (Cron / Acron):</strong>
                Phát hiện <strong>{$stalePendingCount}</strong> tin nhắn đang chờ gửi quá 15 phút chưa được giải phóng. Nếu bạn đang bật chế độ Queue-First, hãy kiểm tra đảm bảo Cron Job hoặc plugin Acron của OJS đang hoạt động bình thường.
            </div>
        </div>
    {/if}

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
    <div class="zod-no-results" id="zodNoResults">Không tìm thấy tin nhắn phù hợp với điều kiện lọc.</div>

    {if empty($entries)}
        <div class="zod-empty">Hàng đợi trống. Không có tin nhắn nào cần xử lý.</div>
    {else}
        <div class="zod-table-wrap">
            <table class="zod-table" id="zodTable">
                <thead>
                    <tr>
                        <th class="zod-th-id" style="width:70px;">ID</th>
                        <th class="zod-th-event" style="width:165px;">Sự kiện</th>
                        <th class="zod-th-recipients" style="width:165px;">Người nhận</th>
                        <th class="zod-th-message" style="min-width:320px;">Nội dung tin nhắn</th>
                        <th class="zod-th-status" style="width:145px;">Trạng thái & Thử</th>
                        <th class="zod-th-time" style="width:160px;">Thời gian</th>
                        <th class="zod-th-actions" style="width:125px; text-align:right;">Thao tác</th>
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
                                {$item.event_type|escape}
                            </span>
                        </td>
                        <td class="zod-col-recipients">
                            <div class="zod-recipients-wrap">
                                {if $item.recipient_count > 0}
                                    <div class="zod-recipients-header">
                                        <strong>{$item.recipient_count}</strong> người nhận
                                    </div>
                                    <div class="zod-phones">
                                        {assign var="displayRecipients" value=$item.masked_recipients|default:$item.recipients}
                                        {foreach from=$displayRecipients item=phone}
                                            <span class="zod-phone-badge" title="Số điện thoại nhận tin (được che bảo mật)">
                                                {$phone|escape}
                                            </span>
                                        {/foreach}
                                    </div>
                                {else}
                                    {if $item.status == 'sent'}
                                        <div class="zod-recipients-sent" title="Tin nhắn đã gửi thành công (dữ liệu được lưu đến khi hết hạn lưu trữ)">
                                            Đã hoàn tất
                                            <small>Lưu đến khi hết hạn</small>
                                        </div>
                                    {else}
                                        <span class="zod-recipients-empty" title="Chưa có thông tin số điện thoại người nhận">
                                            0 người nhận
                                        </span>
                                    {/if}
                                {/if}
                            </div>
                        </td>
                        <td class="zod-col-message">
                            {if !empty($item.parsed_msg.title) || !empty($item.parsed_msg.main_lines)}
                                <div class="zod-msg-wrapper">
                                    {if !empty($item.parsed_msg.title)}
                                        <div class="zod-msg-header">{$item.parsed_msg.title}</div>
                                    {/if}
                                    <div class="zod-msg-body">
                                        {foreach from=$item.parsed_msg.main_lines item=mline}
                                            <div class="zod-msg-line">{$mline}</div>
                                        {/foreach}
                                    </div>
                                    {if $item.parsed_msg.has_more}
                                        <div class="zod-msg-more" style="display:none;">
                                            {foreach from=$item.parsed_msg.more_lines item=mline}
                                                <div class="zod-msg-line secondary">{$mline}</div>
                                            {/foreach}
                                        </div>
                                        <button type="button" class="zod-msg-toggle" data-count="{$item.parsed_msg.more_count}" title="Bấm để xem đầy đủ nội dung tin">
                                            <span class="zod-toggle-text">Xem thêm</span> ({$item.parsed_msg.more_count} dòng) ▾
                                        </button>
                                    {/if}
                                </div>
                            {elseif !empty($item.message_text)}
                                <div class="zod-msg-wrapper">
                                    <div class="zod-msg-line">{$item.message_text|trim|escape|nl2br}</div>
                                </div>
                            {else}
                                <div class="zod-message-cleared" title="Nội dung không còn trong bản ghi này (đã hết hạn lưu trữ hoặc bị xoá thủ công)">
                                    <span>Nội dung không còn trong bản ghi này</span>
                                </div>
                            {/if}
                            {if $item.last_error}
                                <div class="zod-error-box" title="Chi tiết thông báo lỗi từ Zalo Gateway">
                                    <div class="zod-error-header">
                                        <strong>Lỗi gửi:</strong>
                                    </div>
                                    <div class="zod-error-content">{$item.last_error|escape}</div>
                                </div>
                            {/if}
                        </td>
                        <td class="zod-col-status">
                            <div class="zod-status-wrap">
                                {if $item.status == 'sent'}
                                    <span class="zod-badge zod-badge-sent">Đã gửi</span>
                                {elseif $item.status == 'failed'}
                                    <span class="zod-badge zod-badge-failed">Thất bại</span>
                                {elseif $item.status == 'retry'}
                                    <span class="zod-badge zod-badge-retry">Đang thử lại</span>
                                {elseif $item.status == 'processing'}
                                    <span class="zod-badge zod-badge-processing">Đang gửi</span>
                                {else}
                                    <span class="zod-badge zod-badge-pending">Chờ gửi</span>
                                {/if}
                                <div class="zod-attempts-info">
                                    <span class="zod-attempts-chip">
                                        Đã thử: <strong>{$item.attempts}</strong>/{$item.max_attempts}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="zod-col-time">
                            <div class="zod-time-item" title="Thời gian tạo tin">{$item.created_at|escape}</div>
                            {if $item.status == 'retry'}
                                <div class="zod-next-try" title="Thời điểm tự động thử lại tiếp theo">Retry: {$item.available_at|escape}</div>
                            {elseif $item.sent_at}
                                <div class="zod-sent-time" title="Thời điểm gửi thành công">Gửi: {$item.sent_at|escape}</div>
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
/* 1. Dashboard Container (Không khung viền bao quanh) */
.zalo-outbox-dashboard {
    padding: 4px 2px 14px !important;
    color: #25323d;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

/* 2. Heading & Summary Pills */
.zod-heading {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: flex-start;
    margin-bottom: 18px;
    flex-wrap: wrap;
    border-bottom: none !important;
}

.zod-heading h3 {
    color: #0066a6;
    margin: 0 0 5px;
    font-size: 1.35em;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.25;
}

.zod-heading h3 i {
    color: #0066a6;
    font-size: 1.1em;
}

.zod-heading p {
    margin: 0;
    color: #65717c;
    font-size: 14px;
    line-height: 1.4;
}

/* Metrics pills */
.zod-metrics {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
}

.zod-metric-item {
    background: #eef5fa;
    border: 1px solid #d8e7f1;
    border-radius: 18px;
    padding: 6px 13px;
    font-size: 13.5px;
    white-space: nowrap;
    color: #1e293b;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    line-height: 1.4;
    transition: all .15s ease;
}

.zod-metric-item strong {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
}

.zod-metric-item.is-pending {
    background: #fefce8;
    border-color: #fef08a;
    color: #854d0e;
}
.zod-metric-item.is-pending strong {
    color: #854d0e;
}

.zod-metric-item.is-retry {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}
.zod-metric-item.is-retry strong {
    color: #1d4ed8;
}

.zod-metric-item.is-failed {
    background: #fff1f0;
    border-color: #ffc9c5;
    color: #a42620;
}
.zod-metric-item.is-failed strong {
    color: #a42620;
}

.zod-metric-item.is-sent {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #15803d;
}
.zod-metric-item.is-sent strong {
    color: #15803d;
}

/* 3. Notice bar */
.zod-notice {
    display: none;
    margin: 0 0 16px;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 500;
}
.zod-notice.success {
    display: block;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}
.zod-notice.error {
    display: block;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #9f1239;
}

/* 4. Toolbar */
.zod-tools {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    padding: 10px 12px;
    margin: 0 0 14px;
    background: #f6f9fb;
    border: 1px solid #dce5eb;
    border-radius: 6px;
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
    top: 12px;
    color: #7d8b95;
    font-size: 14px;
}

.zod-search input {
    box-sizing: border-box;
    width: 100%;
    height: 38px;
    padding: 0 12px 0 34px;
    border: 1px solid #b8c5ce;
    border-radius: 4px;
    background: #ffffff;
    font-size: 13.5px;
    color: #1e293b;
    outline: none;
    transition: all 0.15s ease;
}

.zod-search input:focus {
    border-color: #0066a6;
    box-shadow: 0 0 0 2px rgba(0, 102, 166, 0.15);
}

.zod-tools select {
    height: 38px;
    border: 1px solid #b8c5ce;
    border-radius: 4px;
    background: #ffffff;
    padding: 0 12px;
    font-size: 13.5px;
    color: #334155;
    outline: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.zod-tools select:focus {
    border-color: #0066a6;
    box-shadow: 0 0 0 2px rgba(0, 102, 166, 0.15);
}

.zod-bulk-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.zod-bulk-actions button {
    height: 38px;
    padding: 0 14px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .15s ease;
}

/* 5. Result count & Empty states */
.zod-result-count {
    color: #64727d;
    font-size: 13px;
    margin: 4px 4px 10px;
    font-weight: 500;
}

.zod-no-results {
    display: none;
    text-align: center;
    padding: 30px 20px;
    background: #f7f9fa;
    border: 1px dashed #bdc8d0;
    border-radius: 6px;
    color: #667681;
    font-size: 13.5px;
}

.zod-empty {
    text-align: center;
    padding: 35px 20px;
    background: #f1faf4;
    border: 1px solid #b9dfc3;
    border-radius: 6px;
    color: #347445;
    font-size: 14px;
    font-weight: 500;
}

/* 6. Table wrap & Table styling */
.zod-table-wrap {
    overflow-x: auto;
    background: #ffffff;
    border: 1px solid #d8e0e6;
    border-radius: 7px;
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
    min-width: 1080px;
    border-collapse: collapse;
    font-size: 13.5px;
    text-align: left;
}

.zod-table th {
    background: #fbfcfd;
    padding: 12px 14px;
    font-weight: 700;
    color: #52616d;
    border-bottom: 1px solid #dfe5e9;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-size: 12.5px;
    white-space: nowrap !important;
}

.zod-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #edf0f2;
    vertical-align: top;
    color: #25323d;
    background: #ffffff;
    line-height: 1.5;
}

.zod-table tbody tr {
    transition: background-color 0.12s ease;
}

.zod-table tbody tr:hover td {
    background: #f8fafc;
}

.zod-table tbody tr:last-child td {
    border-bottom: 0;
}

/* Col: ID */
.zod-col-id {
    text-align: center;
}

.zod-id-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
}

/* Col: Event & Status Badges */
.zod-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.zod-badge-event {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #dbeafe;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    border-radius: 4px;
}

.zod-badge-sent {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.zod-badge-failed {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.zod-badge-retry {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.zod-badge-pending {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.zod-badge-processing {
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

/* Col: Recipients */
.zod-recipients-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.zod-recipients-header {
    font-size: 13px;
    color: #1e293b;
    font-weight: 600;
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
    padding: 2px 7px;
    font-size: 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    color: #334155;
    display: inline-flex;
    align-items: center;
}

.zod-recipients-sent {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: #15803d;
    font-size: 13px;
    font-weight: 600;
}

.zod-recipients-sent small {
    color: #64748b;
    font-size: 11.5px;
    font-weight: 400;
}

.zod-recipients-empty {
    display: inline-flex;
    align-items: center;
    color: #94a3b8;
    font-size: 12.5px;
    font-style: italic;
}

/* Col: Message & Preview */
.zod-col-message {
    min-width: 320px;
    max-width: 480px;
    vertical-align: top;
}

.zod-msg-wrapper {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 12px;
    background: #f8fafc;
    border-left: 3px solid #0066a6;
    border-radius: 6px;
    font-size: 13.5px;
    line-height: 1.5;
    color: #1e293b;
    text-align: left;
    box-sizing: border-box;
}

.zod-msg-header {
    font-size: 13.5px;
    font-weight: 700;
    color: #0066a6;
    margin-bottom: 2px;
    line-height: 1.35;
    word-break: break-word;
}

.zod-msg-body {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.zod-msg-line {
    font-size: 13px;
    color: #334155;
    line-height: 1.45;
    word-break: break-word;
    text-align: left;
}

.zod-msg-line.secondary {
    color: #64748b;
    font-size: 12.5px;
}

.zod-msg-more {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: 4px;
    padding-top: 6px;
    border-top: 1px dashed #cbd5e1;
}

.zod-msg-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    padding: 2px 0;
    font-size: 12px;
    font-weight: 600;
    color: #0066a6;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    width: fit-content;
    transition: color 0.15s ease;
}

.zod-msg-toggle:hover {
    color: #004b7a;
    text-decoration: underline;
}

.zod-msg-wrapper a.zod-link {
    color: #0066a6;
    text-decoration: underline;
    word-break: break-all;
}

.zod-msg-wrapper a.zod-link:hover {
    color: #004b7a;
}

.zod-message-preview {
    padding: 8px 12px;
    background: #f8fafc;
    border-left: 3px solid #0066a6;
    border-radius: 6px;
    font-size: 13px;
    color: #1e293b;
    line-height: 1.5;
    white-space: pre-line;
    word-break: break-word;
    text-align: left;
}

.zod-message-cleared {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    color: #64748b;
    font-size: 12.5px;
    font-style: italic;
    width: 100%;
    box-sizing: border-box;
}

.zod-error-box {
    margin-top: 6px;
    padding: 8px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 4px;
    color: #991b1b;
    font-size: 12.5px;
    line-height: 1.45;
    word-break: break-word;
}

.zod-error-header {
    font-weight: 700;
    margin-bottom: 2px;
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
    font-size: 12px;
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
    font-size: 12.5px;
    white-space: nowrap;
}

.zod-time-item {
    color: #334155;
    font-weight: 500;
    font-size: 13px;
}

.zod-next-try {
    margin-top: 4px;
    color: #b45309;
    font-weight: 600;
    font-size: 12px;
    background: #fef3c7;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid #fde68a;
    width: fit-content;
}

.zod-sent-time {
    margin-top: 4px;
    color: #15803d;
    font-size: 12px;
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
    height: 32px;
    padding: 0 12px !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: #ffffff !important;
    background: #0066a6 !important;
    border: 1px solid #0066a6 !important;
    border-radius: 4px !important;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}

button.zod-btn-retry:hover {
    background: #005082 !important;
    border-color: #005082 !important;
}

button.zod-btn-delete {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0 !important;
    font-size: 13px !important;
    color: #e11d48 !important;
    background: #ffffff !important;
    border: 1px solid #fecdd3 !important;
    border-radius: 4px !important;
    cursor: pointer;
    transition: all 0.15s ease;
}

button.zod-btn-delete:hover {
    background: #fff1f2 !important;
    border-color: #fda4af !important;
    color: #be123c !important;
}

/* 7. Pagination */
.zod-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin: 18px 0 6px;
}

.zod-pagination button {
    height: 34px;
    padding: 0 15px;
    border-radius: 4px;
    border: 1px solid #b8c5ce;
    background: #ffffff;
    color: #334155;
    font-size: 13px;
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
    color: #586773;
    font-size: 13px;
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
(function ($) {
    'use strict';

    function initOutboxDashboard() {
        var $form = $('#zaloOutboxActionForm');
        if (!$form.length) {
            return;
        }

        var $notice = $('#zodNotice');
        var $rows = $('.zod-row');
        var page = 1;
        var pageSize = 10;
        var filteredRows = [];
        var namespace = '.zaloOutbox';

        function showNotice(type, text) {
            $notice.removeClass('success error').addClass(type).text(text).show();
            setTimeout(function () {
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
            $rows.each(function () {
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

        $('#zodSearch').off(namespace).on('input' + namespace, function () {
            page = 1;
            applyFilters();
        });

        $('#zodStatusFilter').off(namespace).on('change' + namespace, function () {
            page = 1;
            applyFilters();
        });

        $('#zodPrev').off(namespace).on('click' + namespace, function () {
            if (page > 1) {
                page--;
                renderPage();
            }
        });

        $('#zodNext').off(namespace).on('click' + namespace, function () {
            page++;
            renderPage();
        });

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
                success: function (res) {
                    if ($btn) $btn.prop('disabled', false);
                    if (res && res.status) {
                        showNotice('success', res.content || 'Thao tác thành công.');
                        if (successCallback) successCallback(res);
                    } else {
                        showNotice('error', (res && res.content) ? res.content : 'Thao tác thất bại.');
                    }
                },
                error: function () {
                    if ($btn) $btn.prop('disabled', false);
                    showNotice('error', 'Lỗi kết nối máy chủ khi thực hiện thao tác.');
                }
            });
        }

        $(document).off(namespace, '.zod-msg-toggle')
            .on('click' + namespace, '.zod-msg-toggle', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $card = $btn.closest('.zod-msg-wrapper');
                var $more = $card.find('.zod-msg-more');
                var count = $btn.data('count');
                if ($more.is(':visible')) {
                    $more.slideUp(150);
                    $btn.html('<span class="zod-toggle-text">Xem thêm</span> (' + count + ' dòng) ▾');
                } else {
                    $more.slideDown(150);
                    $btn.html('<span class="zod-toggle-text">Thu gọn</span> ▴');
                }
            });

        $(document).off(namespace, '.zod-btn-retry')
            .on('click' + namespace, '.zod-btn-retry', function () {
                var $btn = $(this);
                var outboxId = $btn.data('id');
                sendOutboxAction('retryOutboxItem', { outboxId: outboxId }, $btn, function () {
                    setTimeout(function () { location.reload(); }, 1000);
                });
            });

        $(document).off(namespace, '.zod-btn-delete')
            .on('click' + namespace, '.zod-btn-delete', function () {
                if (!confirm('Bạn có chắc chắn muốn xóa tin nhắn này khỏi hàng đợi?')) {
                    return;
                }
                var $btn = $(this);
                var outboxId = $btn.data('id');
                sendOutboxAction('deleteOutboxItem', { outboxId: outboxId }, $btn, function () {
                    $btn.closest('tr').remove();
                    $rows = $('.zod-row');
                    applyFilters();
                });
            });

        $('#zodRetryAllBtn').off(namespace).on('click' + namespace, function () {
            if (!confirm('Bạn có chắc chắn muốn đưa tất cả tin nhắn lỗi về trạng thái Chờ gửi để thử lại?')) {
                return;
            }
            var $btn = $(this);
            sendOutboxAction('retryAllOutbox', {}, $btn, function () {
                setTimeout(function () { location.reload(); }, 1200);
            });
        });

        $('#zodClearSentBtn').off(namespace).on('click' + namespace, function () {
            if (!confirm('Bạn có muốn xóa tất cả tin nhắn đã gửi thành công để dọn dẹp cơ sở dữ liệu?')) {
                return;
            }
            var $btn = $(this);
            sendOutboxAction('clearSentOutbox', {}, $btn, function () {
                setTimeout(function () { location.reload(); }, 1000);
            });
        });

        $('#zodRefreshBtn').off(namespace).on('click' + namespace, function () {
            location.reload();
        });

        applyFilters();
    }

    if (window.jQuery) {
        window.jQuery(initOutboxDashboard);
    }
}(window.jQuery));
</script>
{/literal}
<script src="{$baseUrl}/plugins/generic/zaloNotification/js/outboxDashboard.js"></script>
