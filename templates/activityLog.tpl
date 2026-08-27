{**
 * plugins/generic/zaloNotification/templates/activityLog.tpl
 *
 * Activity Log viewer cho Zalo Notification Plugin
 * Hiển thị bảng log hoạt động plugin trong modal OJS
 *}

<style>
    .zalo-log-container {ldelim}
    font-family: -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    Roboto,
    sans-serif;
    font-size: 13px;
    max-height: 70vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    {rdelim}

    .zalo-log-header {ldelim}
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    {rdelim}

    .zalo-log-header h3 {ldelim}
    margin: 0;
    font-size: 15px;
    color: #1e293b;
    {rdelim}

    .zalo-log-header .log-meta {ldelim}
    font-size: 12px;
    color: #64748b;
    {rdelim}

    .zalo-log-toolbar {ldelim}
    display: flex;
    gap: 8px;
    padding: 10px 16px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    flex-wrap: wrap;
    align-items: center;
    {rdelim}

    .zalo-log-toolbar select,
    .zalo-log-toolbar button {ldelim}
    font-size: 12px;
    padding: 5px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    {rdelim}

    .zalo-log-toolbar input[type="text"] {ldelim}
    font-size: 12px;
    padding: 5px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    width: 200px;
    {rdelim}

    .zalo-log-toolbar button:hover {ldelim}
    background: #f1f5f9;
    {rdelim}

    .zalo-log-table-wrap {ldelim}
    overflow-y: auto;
    flex: 1;
    {rdelim}

    .zalo-log-table {ldelim}
    width: 100%;
    border-collapse: collapse;
    {rdelim}

    .zalo-log-table thead th {ldelim}
    position: sticky;
    top: 0;
    background: #f1f5f9;
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    white-space: nowrap;
    {rdelim}

    .zalo-log-table tbody td {ldelim}
    padding: 7px 10px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: top;
    {rdelim}

    .zalo-log-table tbody tr:hover {ldelim}
    background: #f8fafc;
    {rdelim}

    .zalo-log-table .col-time {ldelim}
    white-space: nowrap;
    color: #64748b;
    font-size: 12px;
    {rdelim}

    .zalo-log-table .col-id {ldelim}
    text-align: center;
    font-weight: 600;
    {rdelim}

    /* Badges */
    .badge {ldelim}
    display: inline-block;
    padding: 2px 7px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    {rdelim}

    .badge-info    {ldelim} background: #dbeafe; color: #1d4ed8; {rdelim}
    .badge-warning {ldelim} background: #fef3c7; color: #b45309; {rdelim}
    .badge-error   {ldelim} background: #fee2e2; color: #dc2626; {rdelim}

    .badge-submission {ldelim} background: #d1fae5; color: #059669; {rdelim}
    .badge-decision   {ldelim} background: #e0e7ff; color: #4338ca; {rdelim}
    .badge-publish    {ldelim} background: #fce7f3; color: #be185d; {rdelim}
    .badge-unpublish  {ldelim} background: #fee2e2; color: #b91c1c; {rdelim}
    .badge-zalo       {ldelim} background: #cffafe; color: #0891b2; {rdelim}
    .badge-err-type   {ldelim} background: #fee2e2; color: #dc2626; {rdelim}
    .badge-reminder   {ldelim} background: #fff7ed; color: #c2410c; {rdelim}
    .badge-review-response {ldelim} background: #ecfeff; color: #0e7490; {rdelim}
    .badge-review-completed {ldelim} background: #dcfce7; color: #15803d; {rdelim}

    .zalo-log-toolbar .btn-check-deadline {ldelim}
    background: #fff7ed;
    border-color: #fb923c;
    color: #c2410c;
    font-weight: 600;
    {rdelim}
    .zalo-log-toolbar .btn-check-deadline:hover {ldelim}
    background: #ffedd5;
    {rdelim}
    .zalo-log-toolbar .btn-check-deadline:disabled {ldelim}
    opacity: 0.6;
    cursor: wait;
    {rdelim}

    .zalo-log-empty {ldelim}
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
    {rdelim}

    .zalo-log-empty .empty-icon {ldelim}
    font-size: 36px;
    margin-bottom: 8px;
    {rdelim}

    .zalo-detail-toggle {ldelim}
    cursor: pointer;
    color: #3b82f6;
    font-size: 11px;
    text-decoration: underline;
    {rdelim}

    .zalo-detail-content {ldelim}
    display: none;
    margin-top: 4px;
    padding: 6px 8px;
    background: #f8fafc;
    border-radius: 3px;
    font-size: 11px;
    color: #475569;
    word-break: break-all;
    {rdelim}

    .zalo-log-pagination {ldelim}
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 10px 16px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
    {rdelim}

    .zalo-log-pagination button {ldelim}
    min-width: 82px;
    padding: 6px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    color: #334155;
    background: #fff;
    cursor: pointer;
    {rdelim}

    .zalo-log-pagination button:hover:not(:disabled) {ldelim}
    color: #0068ff;
    border-color: #80b4ff;
    background: #f7faff;
    {rdelim}

    .zalo-log-pagination button:disabled {ldelim}
    opacity: .45;
    cursor: default;
    {rdelim}

    .zalo-log-page-info {ldelim}
    min-width: 150px;
    color: #64748b;
    text-align: center;
    {rdelim}
</style>

<div class="zalo-log-container">
    {* Header *}
    <div class="zalo-log-header">
        <div>
            <h3>📋 Activity Log — Zalo Notification</h3>
            <span class="log-meta">Có {$totalEntries} bản ghi trong thời hạn lưu 90 ngày</span>
            <span class="log-meta" style="display:block;">
                Hàng đợi: {$outboxStats.pending} chờ, {$outboxStats.processing} đang gửi,
                {$outboxStats.retry} thử lại, {$outboxStats.failed} lỗi.
            </span>
            <span class="log-meta" style="display:block;">Nhật ký được bảo vệ và tự động xóa sau 90 ngày.</span>
        </div>
    </div>

    {* Toolbar: Filter + Actions *}
    <div class="zalo-log-toolbar" id="zaloLogToolbar"
        style="display:flex;gap:8px;align-items:center;flex:1;flex-wrap:wrap;">
        <label style="font-weight:600;color:#475569;">Lọc:</label>
        <select id="zaloFilterSelect">
            <option value="">— Tất cả —</option>
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


        <input type="text" id="zaloSearchInput" placeholder="Tìm kiếm ID, tiêu đề, tên...">

        <button type="button" id="zaloBtnRefresh" title="Làm mới">🔄 Refresh</button>
        <button type="button" id="zaloBtnExport" title="Xuất file TXT">📥 Xuất file log</button>
    </div>

    {* Table *}
    <div class="zalo-log-table-wrap">
        {if $totalEntries > 0}
            <table class="zalo-log-table">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Loại</th>
                        <th>Level</th>
                        <th>Actor</th>
                        <th style="text-align:center">ID</th>
                        <th>Tiêu đề bài báo</th>
                        <th>Chi tiết</th>
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
                            <td>
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
                            <td>
                                {if $entry.title}
                                    {$entry.title|escape|truncate:60:"..."}
                                {else}
                                    <span style="color:#94a3b8;">—</span>
                                {/if}
                            </td>
                            <td>
                                {* Hiển thị thông tin extra gọn gàng *}
                                {if isset($entry.extra)}
                                    {if isset($entry.extra.performed_by)}
                                        👤 {$entry.extra.performed_by|escape}
                                    {/if}
                                    {if isset($entry.extra.decision)}
                                        <br />{$entry.extra.decision|escape}
                                    {/if}
                                    {if isset($entry.extra.zalo_status)}
                                        <br />Zalo: <strong>{$entry.extra.zalo_status|escape}</strong>
                                    {/if}
                                    {if isset($entry.extra.status)}
                                        <br />Status: <strong>{$entry.extra.status|escape}</strong>
                                    {/if}
                                    {if isset($entry.extra.message)}
                                        <br />⚠️ {$entry.extra.message|escape|truncate:80:"..."}
                                    {/if}
                                    {if isset($entry.extra.reviewerName)}
                                        <br />👤 Reviewer: {$entry.extra.reviewerName|escape}
                                    {elseif isset($entry.extra.reviewer)}
                                        <br />👤 Reviewer: {$entry.extra.reviewer|escape}
                                    {/if}
                                    {if isset($entry.extra.deadline)}
                                        <br />📅 Hạn: {$entry.extra.deadline|escape}
                                        {if isset($entry.extra.daysLeft)}
                                            ({if $entry.extra.daysLeft < 0}quá hạn{elseif $entry.extra.daysLeft == 0}HÔM NAY{else}còn {$entry.extra.daysLeft} ngày{/if})
                                        {/if}
                                    {elseif isset($entry.extra.date_due)}
                                        <br />📅 Hạn: {$entry.extra.date_due|escape}
                                        {if isset($entry.extra.days_left)}
                                            ({if $entry.extra.days_left == 0}HÔM NAY{else}còn {$entry.extra.days_left} ngày{/if})
                                        {/if}
                                    {/if}
                                    {if isset($entry.extra.recommendation)}
                                        <br />📌 Đề xuất: {$entry.extra.recommendation|escape}
                                    {/if}
                                    {if isset($entry.extra.response_status)}
                                        <br />Phản hồi: {$entry.extra.response_status|escape}
                                    {/if}
                                    {if isset($entry.extra.action_detail) && $entry.type != 'REVIEW_REQUEST' && $entry.type != 'REVIEW_RESPONSE' && $entry.type != 'REVIEW_COMPLETED'}
                                        <br />{$entry.extra.action_detail|escape|truncate:100:"..."}
                                    {/if}
                                    {* Toggle chi tiết đầy đủ *}
                                    <span class="zalo-detail-toggle"
                                        onclick="var el=this.nextElementSibling; el.style.display=(el.style.display==='block'?'none':'block');">
                                        xem thêm
                                    </span>
                                    <div class="zalo-detail-content">
                                        <pre
                                            style="margin:0;white-space:pre-wrap;">{$entry.extra|@json_encode:JSON_PRETTY_PRINT|escape}</pre>
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
                <p>Chưa có hoạt động nào được ghi lại.</p>
                <p style="font-size:12px;">Log sẽ xuất hiện khi có bài nộp mới, quyết định biên tập, xuất bản, hủy xuất bản hoặc hoạt động phản biện.</p>
            </div>
        {/if}
    </div>
    {if $totalEntries > 0}
        <nav class="zalo-log-pagination" id="zaloLogPagination" aria-label="Phân trang Activity Log">
            <button type="button" id="zaloLogPrev"><i class="fa fa-chevron-left"></i> Trước</button>
            <span class="zalo-log-page-info" id="zaloLogPageInfo"></span>
            <button type="button" id="zaloLogNext">Sau <i class="fa fa-chevron-right"></i></button>
        </nav>
    {/if}

    <script>
        $(function() {ldelim}
        // Base URL cho AJAX
        var ajaxUrl = '{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.SettingsPluginGridHandler" op="manage" category="generic" plugin=$pluginName verb="activityLog" escape=false}';
        var exportUrl = '{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.SettingsPluginGridHandler" op="manage" category="generic" plugin=$pluginName verb="exportLog" escape=false}';
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
            var searchTerm = $.trim(container.find('#zaloSearchInput').val() || '').toLowerCase();
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

        // Hàm tải lại nội dung log
        function reloadLog(filterValue) {ldelim}
        var url = ajaxUrl;
        if (filterValue) {ldelim}
        url += '&filterType=' + encodeURIComponent(filterValue);
        {rdelim}

        // Thêm style mờ đi khi loading
        var currentContainer = getLogContainer();
        currentContainer.css('opacity', '0.5');

        $.get(url, function(response) {ldelim}
        if (response && response.status && response.content) {ldelim}
        var nextContainer = $('<div>').append($.parseHTML(response.content, document, true)).find('.zalo-log-container').first();
        if (nextContainer.length) {ldelim}
        currentContainer.replaceWith(nextContainer);
        renderLogPage(true);
        {rdelim} else {ldelim}
        currentContainer.css('opacity', '1');
        alert('Không thể tải dữ liệu log.');
        {rdelim}
        {rdelim} else {ldelim}
        currentContainer.css('opacity', '1');
        alert('Không thể tải dữ liệu log.');
        {rdelim}
        {rdelim}).fail(function() {ldelim}
        currentContainer.css('opacity', '1');
        alert('Có lỗi xảy ra khi kết nối với máy chủ.');
        {rdelim});
        {rdelim}

        // Sự kiện đổi filter
        $(document).off('change.zaloActivityLog', '#zaloFilterSelect').on('change.zaloActivityLog', '#zaloFilterSelect', function() {ldelim}
        reloadLog($(this).val());
        {rdelim});

        // Tìm kiếm trên toàn bộ log còn hạn, sau đó phân trang kết quả.
        $(document).off('input.zaloActivityLog', '#zaloSearchInput').on('input.zaloActivityLog', '#zaloSearchInput', function() {ldelim}
            renderLogPage(true);
        {rdelim});

        $(document).off('click.zaloActivityLog', '#zaloLogPrev').on('click.zaloActivityLog', '#zaloLogPrev', function() {ldelim}
            if (logPage > 1) {ldelim}
                logPage--;
                renderLogPage(false);
                getLogContainer().find('.zalo-log-table-wrap').scrollTop(0);
            {rdelim}
        {rdelim});

        $(document).off('click.zaloActivityLog', '#zaloLogNext').on('click.zaloActivityLog', '#zaloLogNext', function() {ldelim}
            var totalMatched = getLogContainer().find('.zalo-log-table tbody tr').filter(function() {ldelim}
                var term = $.trim(getLogContainer().find('#zaloSearchInput').val() || '').toLowerCase();
                return rowMatchesSearch($(this), term);
            {rdelim}).length;
            if (logPage * logPageSize < totalMatched) {ldelim}
                logPage++;
                renderLogPage(false);
                getLogContainer().find('.zalo-log-table-wrap').scrollTop(0);
            {rdelim}
        {rdelim});

        // Sự kiện bấm Refresh
        $(document).off('click.zaloActivityLog', '#zaloBtnRefresh').on('click.zaloActivityLog', '#zaloBtnRefresh', function(e) {ldelim}
        e.preventDefault();
        reloadLog($('#zaloFilterSelect').val());
        {rdelim});

        // Sự kiện xuất file log
        $(document).off('click.zaloActivityLog', '#zaloBtnExport').on('click.zaloActivityLog', '#zaloBtnExport', function(e) {ldelim}
        e.preventDefault();
        var filterValue = $('#zaloFilterSelect').val();
        window.location.href = filterValue
            ? exportUrl + '&filterType=' + encodeURIComponent(filterValue)
            : exportUrl;
        {rdelim});

        renderLogPage(true);

        {rdelim});
</script>
