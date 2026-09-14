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
                        <th style="width:70px;">ID</th>
                        <th style="width:130px;">Sự kiện</th>
                        <th style="width:160px;">Người nhận</th>
                        <th>Nội dung tin nhắn</th>
                        <th style="width:140px;">Trạng thái & Thử</th>
                        <th style="width:150px;">Thời gian</th>
                        <th style="width:140px; text-align:right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$entries item=item}
                    <tr class="zod-row" data-id="{$item.outbox_id}" data-status="{$item.status|escape}" data-event="{$item.event_type|escape}">
                        <td class="zod-col-id"><strong>#{$item.outbox_id}</strong></td>
                        <td>
                            <span class="zod-badge zod-badge-event">{$item.event_type|escape}</span>
                        </td>
                        <td>
                            <div class="zod-recipients-wrap">
                                <strong>{$item.recipient_count} người nhận</strong>
                                <div class="zod-phones">
                                    {foreach from=$item.recipients item=phone}
                                        <span class="zod-phone-badge">{$phone|escape}</span>
                                    {/foreach}
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="zod-message-preview">
                                {$item.message_text|escape|nl2br}
                            </div>
                            {if $item.last_error}
                                <div class="zod-error-box">
                                    <i class="fa fa-exclamation-circle"></i> <strong>Lỗi:</strong> {$item.last_error|escape}
                                </div>
                            {/if}
                        </td>
                        <td>
                            {if $item.status == 'sent'}
                                <span class="zod-badge zod-badge-sent"><i class="fa fa-check"></i> Đã gửi</span>
                            {elseif $item.status == 'failed'}
                                <span class="zod-badge zod-badge-failed"><i class="fa fa-times-circle"></i> Thất bại</span>
                            {elseif $item.status == 'retry'}
                                <span class="zod-badge zod-badge-retry"><i class="fa fa-clock-o"></i> Thử lại</span>
                            {elseif $item.status == 'processing'}
                                <span class="zod-badge zod-badge-processing"><i class="fa fa-spinner fa-spin"></i> Đang gửi</span>
                            {else}
                                <span class="zod-badge zod-badge-pending"><i class="fa fa-hourglass-half"></i> Chờ gửi</span>
                            {/if}
                            <small class="zod-attempts-info">Đã thử: {$item.attempts}/{$item.max_attempts}</small>
                        </td>
                        <td class="zod-col-time">
                            <div><i class="fa fa-calendar"></i> {$item.created_at|escape}</div>
                            {if $item.status == 'retry'}
                                <small class="zod-next-try"><i class="fa fa-clock-o"></i> Retry: {$item.available_at|escape}</small>
                            {elseif $item.sent_at}
                                <small class="zod-sent-time"><i class="fa fa-check"></i> Gửi: {$item.sent_at|escape}</small>
                            {/if}
                        </td>
                        <td class="zod-col-actions">
                            <div class="zod-action-buttons">
                                {if $item.status != 'sent'}
                                    <button type="button" class="pkp_button pkp_button_primary zod-btn-retry" data-id="{$item.outbox_id}" title="Thử gửi lại ngay lập tức">
                                        <i class="fa fa-refresh"></i> Thử lại
                                    </button>
                                {/if}
                                <button type="button" class="pkp_button zod-btn-delete" data-id="{$item.outbox_id}" title="Xóa bản ghi này khỏi hàng đợi">
                                    <i class="fa fa-trash"></i>
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
.zalo-outbox-dashboard {
    padding: 4px 2px 14px;
    color: #25323d;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 13px;
}
.zod-heading {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 16px;
}
.zod-heading h3 {
    color: #0066a6;
    margin: 0 0 4px;
    font-size: 1.25em;
    font-weight: 600;
}
.zod-heading p {
    margin: 0;
    color: #65717c;
    font-size: 0.95em;
}
.zod-metrics {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.zod-metric-item {
    background: #eef5fa;
    border: 1px solid #d8e7f1;
    border-radius: 16px;
    padding: 6px 12px;
    font-size: 0.88em;
    white-space: nowrap;
    color: #334155;
}
.zod-metric-item.is-pending { background: #fefce8; border-color: #fef08a; color: #854d0e; }
.zod-metric-item.is-retry   { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.zod-metric-item.is-failed  { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.zod-metric-item.is-sent    { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }

.zod-notice {
    display: none;
    margin: 0 0 14px;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 0.92em;
}
.zod-notice.success { display: block; background: #eaf7ee; border: 1px solid #a8d8b5; color: #246b38; }
.zod-notice.error   { display: block; background: #fff0ef; border: 1px solid #e8aaa5; color: #8d2721; }

.zod-tools {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    padding: 10px 12px;
    margin: 0 0 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}
.zod-tools select, .zod-search input {
    height: 34px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    padding: 0 10px;
    font-size: 12px;
}
.zod-search {
    position: relative;
    flex: 1;
    min-width: 250px;
}
.zod-search i {
    position: absolute;
    left: 10px;
    top: 10px;
    color: #94a3b8;
}
.zod-search input {
    box-sizing: border-box;
    width: 100%;
    padding-left: 30px;
}
.zod-bulk-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}
.zod-result-count {
    color: #64748b;
    font-size: 0.88em;
    margin: 4px 2px 8px;
}
.zod-no-results {
    display: none;
    text-align: center;
    padding: 30px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    color: #64748b;
}

.zod-table-wrap {
    overflow-x: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}
.zod-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.zod-table th {
    background: #f1f5f9;
    padding: 9px 12px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-size: 11px;
}
.zod-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #334155;
}
.zod-table tbody tr:hover {
    background: #f8fafc;
}
.zod-col-id {
    color: #64748b;
}
.zod-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
}
.zod-badge-event      { background: #e0f2fe; color: #0369a1; }
.zod-badge-sent       { background: #dcfce7; color: #15803d; }
.zod-badge-failed     { background: #fee2e2; color: #b91c1c; }
.zod-badge-retry      { background: #fef3c7; color: #b45309; }
.zod-badge-pending    { background: #f1f5f9; color: #475569; }
.zod-badge-processing { background: #e0e7ff; color: #4338ca; }

.zod-recipients-wrap strong {
    display: block;
    margin-bottom: 3px;
    color: #1e293b;
}
.zod-phones {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}
.zod-phone-badge {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 10.5px;
    font-family: Consolas, monospace;
    color: #475569;
}
.zod-message-preview {
    max-height: 80px;
    overflow-y: auto;
    padding: 6px 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11.5px;
    color: #334155;
    line-height: 1.4;
    white-space: pre-wrap;
    word-break: break-word;
}
.zod-error-box {
    margin-top: 5px;
    padding: 5px 8px;
    background: #fef2f2;
    border-left: 3px solid #ef4444;
    border-radius: 0 4px 4px 0;
    color: #991b1b;
    font-size: 11px;
}
.zod-attempts-info {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 11px;
}
.zod-col-time {
    color: #64748b;
    font-size: 11.5px;
    white-space: nowrap;
}
.zod-next-try {
    display: block;
    margin-top: 3px;
    color: #b45309;
    font-weight: 600;
}
.zod-sent-time {
    display: block;
    margin-top: 3px;
    color: #15803d;
}
.zod-action-buttons {
    display: flex;
    gap: 4px;
    justify-content: flex-end;
}
.zod-action-buttons button {
    padding: 4px 8px;
    font-size: 11px;
}
.zod-btn-delete {
    color: #dc2626;
    border-color: #fca5a5;
}
.zod-btn-delete:hover {
    background: #fef2f2;
}

.zod-empty {
    text-align: center;
    padding: 35px 20px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    color: #166534;
    font-size: 1.05em;
}
.zod-empty i { margin-right: 6px; }

.zod-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 14px;
    margin: 16px 0 4px;
}
.zod-pagination span {
    min-width: 120px;
    text-align: center;
    color: #64748b;
    font-size: 12px;
}
.zod-pagination button[disabled] { opacity: .45; }

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
