{**
 * notificationTemplatesForm.tpl
 *
 * Tab chứa giao diện cấu hình mẫu thông báo Zalo.
 *}

<script>
	$(function() {ldelim}
		$('#zaloTemplatesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="zaloTemplatesForm" method="post" action="{$saveUrl|escape}">
	{csrf}
    <input type="hidden" name="templatesJson" id="templatesJson" value="" />

    <div class="zalo-editor-container">
        <div class="zalo-sidebar">
            <div class="zalo-sidebar-header">
                <h2><i class="fa fa-envelope-o" style="color:#0066a6;"></i> Mẫu tin Zalo</h2>
                <p>Cấu hình mẫu gửi tin nhắn</p>
            </div>
            <ul class="zalo-role-list">
                <li class="zalo-role-item active" data-role="editor">
                    Ban biên tập
                </li>
                <li class="zalo-role-item" data-role="reviewer">
                    Phản biện viên
                </li>
                <li class="zalo-role-item" data-role="author">
                    Tác giả
                </li>
            </ul>
        </div>

        <div class="zalo-main-content">
            <div class="zalo-content-header">
                <div>
                    <h3 class="zalo-content-title" id="roleTitle">Mẫu thông báo Ban biên tập</h3>
                    <p class="zalo-content-subtitle">Quản lý nội dung tin nhắn Zalo gửi cho các vai trò.</p>
                </div>
            </div>

            <div class="zalo-event-tabs" id="eventTabs">
                <div class="zalo-event-tab active" data-event="submission">Bài nộp mới</div>
                <div class="zalo-event-tab" data-event="decision">Quyết định</div>
                <div class="zalo-event-tab" data-event="initial_decline">Từ chối ban đầu</div>
                <div class="zalo-event-tab" data-event="review_started">Bắt đầu phản biện</div>
                <div class="zalo-event-tab" data-event="publish">Xuất bản</div>
                <div class="zalo-event-tab" data-event="unpublish">Hủy xuất bản</div>
                <div class="zalo-event-tab" data-event="reminder">Nhắc nhở</div>
                <div class="zalo-event-tab" data-event="review_request">Mời phản biện</div>
                <div class="zalo-event-tab" data-event="review_response">PB phản hồi</div>
                <div class="zalo-event-tab" data-event="review_completed">PB đã nộp</div>
                <div class="zalo-event-tab" data-event="author_revision">Tác giả đã sửa</div>
                <div class="zalo-event-tab" data-event="editor_assignment">Phân công BTV</div>
            </div>

            <div class="zalo-editor-wrapper" style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label style="font-size: 0.9rem; font-weight: 600; color: #333;">Nội dung tin nhắn</label>
                    <label class="zalo-preview-toggle">
                        <input type="checkbox" id="togglePreview">
                        <i class="fa fa-eye"></i> Xem trước hiển thị trên Zalo
                    </label>
                </div>

                <textarea id="templateEditor" class="zalo-textarea" placeholder="Nhập nội dung mẫu tin nhắn tại đây..."></textarea>

                <div id="previewArea" class="zalo-preview">
                    <div class="zalo-bubble" id="bubbleText"></div>
                </div>
            </div>

            <details class="zalo-variables-panel" id="variablesPanel">
                <summary class="zalo-variables-title"><i class="fa fa-code"></i> Biến dùng cho mẫu này <span id="variableCount"></span><small>Nhấn để mở</small></summary>
                <div class="zalo-variable-list" id="varList">
                    <!-- Biến được load bằng JS -->
                </div>
            </details>

            <div class="zalo-template-actions">
                <div class="zalo-template-actions-secondary">
                    <button type="button" class="pkp_button zalo-reset-button" id="resetBtn" title="Khôi phục nội dung mẫu đang xem">
                        <i class="fa fa-undo" aria-hidden="true"></i><span>Khôi phục mẫu này</span>
                    </button>
                    <button type="button" class="pkp_button zalo-reset-button zalo-reset-all-button" id="resetAllBtn" title="Khôi phục tất cả mẫu về nội dung mặc định">
                        <i class="fa fa-refresh" aria-hidden="true"></i><span>Khôi phục toàn bộ</span>
                    </button>
                </div>
                <div class="zalo-template-actions-primary">
                    {fbvFormButtons submitText="common.save"}
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    var zaloTemplatesData = {$templatesJson};
    var defaultZaloTemplatesData = {$defaultTemplatesJson};
</script>

{literal}
<style>
    .zalo-editor-container {
        display: flex;
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        gap: 24px;
        padding: 4px 2px 14px !important;
        margin-bottom: 15px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    .zalo-sidebar {
        width: 210px;
        flex-shrink: 0;
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
    }
    .zalo-sidebar-header {
        padding: 0 0 14px !important;
        border-bottom: none !important;
        margin-bottom: 8px;
    }
    .zalo-sidebar-header h2 {
        font-size: 1.35em !important;
        margin: 0 0 4px !important;
        color: #0066a6 !important;
        font-weight: 700 !important;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .zalo-sidebar-header p {
        font-size: 14px !important;
        color: #65717c !important;
        margin: 0 !important;
    }
    .zalo-role-list {
        list-style: none;
        padding: 0 !important;
        margin: 0 !important;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .zalo-role-item {
        padding: 9px 14px !important;
        margin-bottom: 0 !important;
        border-radius: 6px !important;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px !important;
        color: #334155;
        background: #f6f9fb;
        border: 1px solid #dce5eb !important;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.15s ease;
    }
    .zalo-role-item:hover {
        background: #f1f5f9;
        border-color: #94a3b8 !important;
        color: #0066a6;
    }
    .zalo-role-item.active {
        background: #0066a6 !important;
        color: #ffffff !important;
        font-weight: 600;
        border-color: #0066a6 !important;
        border-left: 1px solid #0066a6 !important;
    }
    .zalo-main-content {
        padding: 0 !important;
        flex: 1;
        min-width: 0;
        background: transparent !important;
    }
    .zalo-content-header {
        margin-bottom: 14px !important;
    }
    .zalo-content-title {
        font-size: 1.25em !important;
        font-weight: 700;
        margin: 0 0 4px !important;
        color: #0066a6 !important;
    }
    .zalo-content-subtitle {
        font-size: 14px !important;
        color: #65717c !important;
        margin: 0 !important;
    }
    .zalo-event-tabs {
        display: flex;
        gap: 6px;
        border-bottom: none !important;
        margin-bottom: 14px !important;
        flex-wrap: wrap;
    }
    .zalo-event-tab {
        padding: 6px 13px !important;
        font-size: 13.5px !important;
        font-weight: 500;
        color: #1e293b;
        background: #eef5fa;
        border: 1px solid #d8e7f1 !important;
        border-radius: 18px !important;
        cursor: pointer;
        white-space: nowrap;
        line-height: 1.4;
        margin-bottom: 0 !important;
        border-bottom: 1px solid #d8e7f1 !important;
        transition: all 0.15s ease;
    }
    .zalo-event-tab:hover {
        background: #e0f2fe;
        color: #0066a6;
        border-color: #bae6fd !important;
    }
    .zalo-event-tab.active {
        color: #ffffff !important;
        background: #0066a6 !important;
        border-color: #0066a6 !important;
        border-bottom-color: #0066a6 !important;
        font-weight: 600;
    }
    .zalo-editor-wrapper { flex: 1; display: flex; flex-direction: column; }
    .zalo-textarea {
        width: 100%;
        height: 340px;
        min-height: 300px;
        padding: 14px !important;
        border: 1px solid #d8e0e6 !important;
        border-radius: 6px !important;
        font-family: inherit;
        font-size: 14px !important;
        line-height: 1.6;
        resize: vertical;
        background: #ffffff !important;
        box-sizing: border-box;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }
    .zalo-textarea:focus {
        border-color: #0066a6 !important;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 102, 166, 0.15) !important;
        background: #fff;
    }
    .zalo-variables-panel {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #dce5eb !important;
        border-radius: 6px !important;
        padding: 0;
        margin-top: 14px !important;
    }
    .zalo-variables-title {
        font-size: 13.5px !important;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        padding: 10px 14px !important;
        list-style: none;
        user-select: none;
    }
    .zalo-variables-title::-webkit-details-marker { display: none; }
    .zalo-variables-title::after { content: '\f078'; float: right; font-family: FontAwesome; color: #64748b; }
    .zalo-variables-panel[open] .zalo-variables-title::after { content: '\f077'; }
    .zalo-variables-panel[open] .zalo-variables-title small { display: none; }
    .zalo-variables-title small { margin-left: 8px; color: #7b8794; font-weight: 400; font-size: 12px; }
    #variableCount { display: inline-block; min-width: 18px; margin-left: 5px; padding: 2px 7px; border-radius: 10px; background: #e5edf3; text-align: center; font-size: 12px; font-weight: 600; }
    .zalo-variable-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        flex-direction: row;
        padding: 0 14px 14px;
    }
    .zalo-variable-item {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        padding: 4px 10px !important;
        border-radius: 18px !important;
        font-size: 13px !important;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s;
    }
    .zalo-variable-item:hover {
        border-color: #0066a6;
        background: #f0f9ff;
        transform: translateY(-1px);
    }
    .zalo-variable-code { color: #0066a6; font-family: monospace; font-weight: 600; font-size: 13px; }
    .zalo-variable-desc { color: #64748b; font-size: 12px; }
    .zalo-template-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
    }
    .zalo-template-actions-secondary,
    .zalo-template-actions-primary,
    .zalo-template-actions-primary .formButtons,
    .zalo-template-actions-primary .form_buttons {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .zalo-template-actions-primary .formButtons,
    .zalo-template-actions-primary .form_buttons {
        width: auto;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .zalo-template-actions-primary .pkp_spinner { margin: 0; }
    .zalo-template-actions button,
    .zalo-template-actions input[type="submit"],
    .zalo-template-actions .cancelButton {
        box-sizing: border-box;
        width: auto;
        min-width: 0;
        height: 38px;
        margin: 0;
        padding: 0 14px;
        border-radius: 4px;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 36px;
        white-space: nowrap;
    }
    .zalo-template-actions button,
    .zalo-template-actions .cancelButton {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
    }
    .zalo-template-actions .zalo-reset-button {
        color: #334155;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        transition: all 0.15s ease;
    }
    .zalo-template-actions .zalo-reset-button:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }
    .zalo-template-actions .zalo-reset-all-button {
        color: #b91c1c;
        background: #ffffff;
        border-color: #fecaca;
    }
    .zalo-template-actions .zalo-reset-all-button:hover {
        background: #fef2f2;
        border-color: #f87171;
    }
    .zalo-preview-toggle {
        font-size: 13.5px;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        user-select: none;
    }
    .zalo-preview {
        display: none;
        margin-top: 15px;
        padding: 18px;
        background: #eef2f6;
        border-radius: 8px;
        border: 1px solid #dce5eb;
    }
    .zalo-preview.active { display: block; }
    .zalo-bubble {
        background: #ffffff;
        border-radius: 8px 8px 8px 0;
        padding: 14px 16px;
        max-width: 480px;
        font-size: 13.5px;
        line-height: 1.5;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        color: #1e293b;
        position: relative;
        word-break: break-word;
        white-space: pre-wrap;
    }
    @media(max-width: 800px) {
        .zalo-editor-container { flex-direction: column; }
        .zalo-sidebar { width: 100%; }
        .zalo-role-list { flex-direction: row; }
        .zalo-template-actions { flex-direction: column; align-items: stretch; }
    }
</style>

<script>
(function() {
    var currentTemplates = zaloTemplatesData || {};

    const variablesConfig = {
        common: [
            { id: "{title}", desc: "Tiêu đề bài báo" },
            { id: "{author}", desc: "Tên các tác giả" },
            { id: "{abstract}", desc: "Tóm tắt (nếu có)" },
            { id: "{abstract_if_any}", desc: "Dòng tóm tắt nếu có" },
            { id: "{submissionId}", desc: "ID bài báo" },
            { id: "{stageId}", desc: "ID giai đoạn xử lý" },
            { id: "{publicationId}", desc: "ID phiên bản xuất bản" },
            { id: "{timestamp}", desc: "Giờ hiện tại" }
        ],
        editor: [
            { id: "{editorName}", desc: "Tên biên tập viên" },
            { id: "{decisionDesc}", desc: "Mô tả quyết định" },
            { id: "{stageName}", desc: "Tên giai đoạn" },
            { id: "{issueString}", desc: "Thông tin số báo" },
            { id: "{issueString_if_any}", desc: "Dòng số báo nếu có" },
            { id: "{datePublished}", desc: "Ngày xuất bản" },
            { id: "{reviewerName}", desc: "Tên phản biện viên" },
            { id: "{deadline}", desc: "Hạn chót" },
            { id: "{responseDeadline}", desc: "Hạn phản hồi lời mời" },
            { id: "{daysLeft}", desc: "Thời gian còn lại" },
            { id: "{round}", desc: "Vòng phản biện" },
            { id: "{responseStatus}", desc: "Trạng thái phản hồi lời mời" },
            { id: "{recommendationDesc}", desc: "Đề xuất phản biện" },
            { id: "{editorRole}", desc: "Vai trò biên tập được phân công" },
            { id: "{assignedBy}", desc: "Người thực hiện phân công" },
            { id: "{workflowUrl}", desc: "Link đúng giai đoạn cho biên tập viên" }
        ],
        reviewer: [
            { id: "{reviewerName}", desc: "Tên phản biện viên" },
            { id: "{decisionDesc}", desc: "Mô tả quyết định" },
            { id: "{deadline}", desc: "Hạn chót" },
            { id: "{responseDeadline}", desc: "Hạn phản hồi lời mời" },
            { id: "{daysLeft}", desc: "Thời gian còn lại" },
            { id: "{round}", desc: "Vòng phản biện" },
            { id: "{reviewId}", desc: "ID nhiệm vụ phản biện" },
            { id: "{reviewerUrl}", desc: "Link mở nhiệm vụ phản biện" }
        ],
        author: [
            { id: "{decisionDesc}", desc: "Mô tả quyết định" },
            { id: "{issueString}", desc: "Thông tin số báo" },
            { id: "{issueString_if_any}", desc: "Dòng số báo nếu có" },
            { id: "{datePublished}", desc: "Ngày xuất bản" },
            { id: "{recommendationDesc}", desc: "Đề xuất phản biện" },
            { id: "{authorUrl}", desc: "Link bài viết cho tác giả" },
            { id: "{publicUrl}", desc: "Link bài đã xuất bản công khai" }
        ]
    };

    // Chỉ hiển thị những sự kiện mà vai trò thực sự có thể nhận tin.
    // Mẫu của các sự kiện ẩn vẫn được giữ nguyên trong dữ liệu cấu hình.
    const roleEvents = {
        editor: [
            'submission', 'decision', 'publish', 'unpublish', 'reminder',
            'review_request', 'review_response', 'review_completed',
            'author_revision', 'editor_assignment'
        ],
        reviewer: ['decision', 'reminder', 'review_request'],
        author: ['submission', 'decision', 'initial_decline', 'review_started', 'publish', 'unpublish']
    };

    // Chỉ đưa ra những biến liên quan đến nghiệp vụ đang chỉnh sửa.
    const eventVariables = {
        submission: ['{title}', '{author}', '{abstract_if_any}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}', '{authorUrl}'],
        decision: ['{title}', '{author}', '{decisionDesc}', '{editorName}', '{stageName}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}', '{authorUrl}'],
        initial_decline: ['{title}', '{author}', '{decisionDesc}', '{submissionId}', '{timestamp}', '{authorUrl}'],
        review_started: ['{title}', '{author}', '{stageName}', '{submissionId}', '{stageId}', '{timestamp}', '{authorUrl}'],
        publish: ['{title}', '{author}', '{issueString_if_any}', '{datePublished}', '{submissionId}', '{timestamp}', '{workflowUrl}', '{publicUrl}'],
        unpublish: ['{title}', '{author}', '{submissionId}', '{timestamp}', '{workflowUrl}', '{authorUrl}'],
        reminder: ['{title}', '{author}', '{reviewerName}', '{deadline}', '{daysLeft}', '{round}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}', '{reviewerUrl}'],
        review_request: ['{title}', '{author}', '{reviewerName}', '{deadline}', '{responseDeadline}', '{round}', '{submissionId}', '{stageId}', '{reviewId}', '{timestamp}', '{workflowUrl}', '{reviewerUrl}'],
        review_response: ['{title}', '{author}', '{reviewerName}', '{responseStatus}', '{deadline}', '{round}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}'],
        review_completed: ['{title}', '{author}', '{reviewerName}', '{recommendationDesc}', '{round}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}'],
        author_revision: ['{title}', '{author}', '{stageName}', '{round}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}'],
        editor_assignment: ['{title}', '{author}', '{editorName}', '{editorRole}', '{assignedBy}', '{stageName}', '{submissionId}', '{stageId}', '{timestamp}', '{workflowUrl}']
    };

    let currentRole = 'editor';
    let currentEvent = 'submission';

    const editor = document.getElementById('templateEditor');
    const roleTabs = document.querySelectorAll('.zalo-role-item');
    const eventTabs = document.querySelectorAll('.zalo-event-tab');
    const varList = document.getElementById('varList');
    const variableCount = document.getElementById('variableCount');
    const roleTitle = document.getElementById('roleTitle');
    const togglePreview = document.getElementById('togglePreview');
    const previewArea = document.getElementById('previewArea');
    const bubbleText = document.getElementById('bubbleText');
    const templatesJsonInput = document.getElementById('templatesJson');

    function updateHiddenInput() {
        templatesJsonInput.value = JSON.stringify(currentTemplates);
    }

    function updateVisibleEvents() {
        const allowedEvents = roleEvents[currentRole] || [];
        if (!allowedEvents.includes(currentEvent)) {
            currentEvent = allowedEvents[0] || '';
        }
        eventTabs.forEach(tab => {
            const visible = allowedEvents.includes(tab.dataset.event);
            tab.style.display = visible ? '' : 'none';
            tab.classList.toggle('active', visible && tab.dataset.event === currentEvent);
        });
    }

    function loadVariables() {
        varList.innerHTML = '';
        let vars = variablesConfig.common.slice();
        if (variablesConfig[currentRole]) {
            vars = vars.concat(variablesConfig[currentRole]);
        }
        const allowedVariables = eventVariables[currentEvent] || [];
        vars = vars.filter(v => allowedVariables.includes(v.id));
        variableCount.textContent = vars.length;

        vars.forEach(v => {
            const el = document.createElement('div');
            el.className = 'zalo-variable-item';
            el.innerHTML = '<span class="zalo-variable-code">' + v.id + '</span><span class="zalo-variable-desc">' + v.desc + '</span>';
            el.onclick = () => insertVariable(v.id);
            varList.appendChild(el);
        });
    }

    function insertVariable(val) {
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        const text = editor.value;
        editor.value = text.substring(0, start) + val + text.substring(end);
        editor.focus();
        editor.selectionEnd = start + val.length;
        saveCurrentEditorValue();
        updatePreview();
    }

    function updateEditorContent() {
        if (!currentTemplates[currentRole]) currentTemplates[currentRole] = {};
        editor.value = currentTemplates[currentRole][currentEvent] || "";
        updatePreview();
    }

    function saveCurrentEditorValue() {
        if (!currentTemplates[currentRole]) currentTemplates[currentRole] = {};
        currentTemplates[currentRole][currentEvent] = editor.value;
        updateHiddenInput();
    }

    function updatePreview() {
        let text = editor.value;
        if (!text) {
            bubbleText.innerHTML = "<em>(Chưa có nội dung mẫu)</em>";
            return;
        }

        const mockData = {
            "{title}": "Nghiên cứu ứng dụng AI",
            "{author}": "Nguyễn Văn A",
            "{abstract}": "Tóm tắt ngắn gọn của bài...",
            "{abstract_if_any}": "Tóm tắt: Tóm tắt ngắn gọn của bài...\n",
            "{submissionId}": "1045",
            "{stageId}": "3",
            "{reviewId}": "278",
            "{publicationId}": "1092",
            "{workflowUrl}": "https://tapchi.example.vn/index.php/tapchi/workflow/index/1045/3",
            "{authorUrl}": "https://tapchi.example.vn/index.php/tapchi/authorDashboard/submission/1045",
            "{reviewerUrl}": "https://tapchi.example.vn/index.php/tapchi/reviewer/submission?submissionId=1045",
            "{publicUrl}": "https://tapchi.example.vn/index.php/tapchi/article/view/1045",
            "{timestamp}": "10/05/2026 14:30",
            "{editorName}": "Trần Thị B",
            "{editorRole}": "Biên tập viên chuyên mục",
            "{assignedBy}": "Nguyễn Văn Quản lý",
            "{decisionDesc}": "Chấp nhận bài",
            "{stageName}": "Phản biện",
            "{reviewerName}": "Lê Văn C",
            "{deadline}": "15/05/2026",
            "{daysLeft}": "Còn 5 ngày",
            "{round}": "1",
            "{issueString}": "Vol 1 No 1 (2026)",
            "{issueString_if_any}": "Số báo: Vol 1 No 1 (2026)\n",
            "{recommendationDesc}": "Chấp nhận bài",
            "{datePublished}": "10/05/2026"
        };

        for (const [key, val] of Object.entries(mockData)) {
            text = text.replaceAll(key, val);
        }
        bubbleText.textContent = text;
    }

    roleTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            roleTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentRole = tab.dataset.role;
            roleTitle.innerText = 'Mẫu thông báo ' + tab.innerText.trim();
            updateVisibleEvents();
            loadVariables();
            updateEditorContent();
        });
    });

    eventTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            eventTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentEvent = tab.dataset.event;
            loadVariables();
            updateEditorContent();
        });
    });

    editor.addEventListener('input', () => {
        saveCurrentEditorValue();
        updatePreview();
    });

    $('#zaloTemplatesForm').on('submit', function() {
        updateHiddenInput();
    });

    togglePreview.addEventListener('change', (e) => {
        if (e.target.checked) {
            editor.style.display = 'none';
            previewArea.classList.add('active');
        } else {
            editor.style.display = 'block';
            previewArea.classList.remove('active');
        }
    });

    document.getElementById('resetAllBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm("Bạn có chắc chắn muốn khôi phục toàn bộ mẫu thông báo về mặc định hệ thống? Sau khi lưu sẽ không thể hoàn tác.")) {
            currentTemplates = JSON.parse(JSON.stringify(defaultZaloTemplatesData));
            updateHiddenInput();
            updateEditorContent();
            alert("Đã khôi phục toàn bộ mẫu tin nhắn mặc định. Hãy nhấn nút Lưu để hoàn tất.");
        }
    });

    document.getElementById('resetBtn').addEventListener('click', function(e) {
        e.preventDefault();
        var roleName = document.querySelector('.zalo-role-item.active').innerText.trim();
        var eventName = document.querySelector('.zalo-event-tab.active').innerText.trim();
        if (confirm("Bạn có chắc chắn muốn khôi phục mẫu thông báo mặc định cho [" + roleName + " - " + eventName + "]? Sau khi lưu sẽ không thể hoàn tác.")) {
            if (!currentTemplates[currentRole]) currentTemplates[currentRole] = {};
            currentTemplates[currentRole][currentEvent] = defaultZaloTemplatesData[currentRole] && defaultZaloTemplatesData[currentRole][currentEvent] ? defaultZaloTemplatesData[currentRole][currentEvent] : "";
            updateHiddenInput();
            updateEditorContent();
            alert("Đã khôi phục mẫu tin nhắn mặc định cho phần này. Hãy nhấn nút Lưu để hoàn tất.");
        }
    });

    updateHiddenInput();
    updateVisibleEvents();
    loadVariables();
    updateEditorContent();
})();
</script>
{/literal}
