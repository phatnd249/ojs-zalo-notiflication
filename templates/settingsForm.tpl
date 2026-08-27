{**
 * settingsForm.tpl
 *
 * Zalo Notification Plugin — Cấu hình API và nhóm nhận thông báo.
 * Cho phép admin cấu hình Bot ID, API Key, và các nhóm người nhận
 * với loại thông báo tương ứng cho mỗi nhóm.
 *}

{* PKP AjaxFormHandler — xử lý submit form qua AJAX trong modal *}
<script>
	$(function() {ldelim}
		$('#zaloSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="zaloSettingsForm" method="post" action="{$saveUrl|escape}">
	{csrf}



	{* ========== NHÓM NHẬN THÔNG BÁO ========== *}
	<div id="zaloGroupsSection" style="margin-bottom: 24px;">
		<h3 style="margin: 0 0 8px; padding-bottom: 6px; border-bottom: 2px solid #28a745; color: #28a745;">
			<i class="fa fa-users"></i> Nhóm nhận thông báo
		</h3>
		<p style="color: #555; margin: 0 0 12px; font-size: 0.9em;">
			Thêm các nhóm người nhận và chọn loại thông báo cho từng nhóm. Mỗi nhóm có thể có nhiều số điện thoại.
		</p>

		<div id="recipientGroupsContainer"></div>

		<button type="button" id="addGroupBtn"
				style="margin-top: 8px; width: 100%; padding: 12px; border: 2px dashed #0066cc; background: transparent; color: #0066cc; font-weight: bold; cursor: pointer; border-radius: 8px; font-size: 14px; transition: background 0.2s;">
			<i class="fa fa-plus-circle"></i> Thêm nhóm nhận thông báo
		</button>
	</div>

	{* Hidden input chứa JSON data của tất cả nhóm *}
	<input type="hidden" name="recipientGroupsJson" id="recipientGroupsJson" value="" />

	{* Nút lưu *}
	<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; text-align: right;">
		{fbvFormButtons submitText="common.save"}
	</div>
</form>

{* ========== Truyền dữ liệu nhóm hiện có sang JavaScript ========== *}
<script>
	var zaloExistingGroups = {$recipientGroupsJson};
</script>

{literal}
<style>
	.zalo-group-card {
		border: 1px solid #d0d7de;
		border-radius: 8px;
		padding: 16px;
		margin-bottom: 12px;
		background: #f6f8fa;
		position: relative;
		transition: box-shadow 0.2s;
	}
	.zalo-group-card:hover {
		box-shadow: 0 2px 8px rgba(0,0,0,0.08);
	}
	.zalo-group-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 14px;
		padding-bottom: 8px;
		border-bottom: 1px solid #e1e4e8;
	}
	.zalo-group-header strong {
		font-size: 1.05em;
		color: #24292f;
	}
	.zalo-group-label {
		display: block;
		font-weight: 600;
		margin-bottom: 4px;
		color: #444;
		font-size: 0.9em;
	}
	.zalo-group-input {
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #ccc;
		border-radius: 4px;
		margin-bottom: 12px;
		font-size: 14px;
		box-sizing: border-box;
	}
	.zalo-group-input:focus {
		border-color: #0066cc;
		outline: none;
		box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
	}
	.zalo-events-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 4px;
	}
	.zalo-event-checkbox {
		display: flex;
		align-items: center;
		gap: 6px;
		padding: 6px 12px;
		background: #fff;
		border: 1px solid #d0d7de;
		border-radius: 6px;
		cursor: pointer;
		font-size: 0.88em;
		transition: all 0.15s;
		user-select: none;
	}
	.zalo-event-checkbox:hover {
		border-color: #0066cc;
		background: #f0f7ff;
	}
	.zalo-event-checkbox.checked {
		border-color: #0066cc;
		background: #e8f0fe;
	}
	.zalo-remove-btn {
		background: #dc3545;
		color: #fff;
		border: none;
		padding: 5px 14px;
		border-radius: 4px;
		cursor: pointer;
		font-size: 0.82em;
		transition: background 0.2s;
	}
	.zalo-remove-btn:hover {
		background: #b02a37;
	}
	#addGroupBtn:hover {
		background: #f0f7ff;
	}
	.zalo-no-groups {
		text-align: center;
		padding: 24px;
		color: #888;
		font-style: italic;
		border: 1px dashed #ccc;
		border-radius: 8px;
		margin-bottom: 8px;
	}
</style>

<script>
(function() {
	// Các loại sự kiện có thể cấu hình
	var EVENT_TYPES = [
		{ id: 'SUBMISSION',      label: '<i class="fa fa-inbox"></i> Bài nộp mới',          desc: 'Khi tác giả nộp bài hoàn tất' },
		{ id: 'DECISION',        label: '<i class="fa fa-clipboard"></i> Quyết định biên tập',  desc: 'Khi editor ra quyết định (chấp nhận, từ chối, yêu cầu sửa...)' },
		{ id: 'PUBLISH',         label: '<i class="fa fa-bullhorn"></i> Xuất bản',             desc: 'Khi bài báo được xuất bản chính thức' },
		{ id: 'UNPUBLISH',        label: '<i class="fa fa-exclamation-triangle"></i> Hủy xuất bản',        desc: 'Khi bài báo bị gỡ xuất bản' },
		{ id: 'REVIEW_REQUEST',   label: '<i class="fa fa-user-plus"></i> Mời phản biện',       desc: 'Khi editor phân công hoặc gửi lời mời phản biện' },
		{ id: 'REVIEW_RESPONSE',  label: '<i class="fa fa-reply"></i> PB phản hồi',      desc: 'Khi phản biện viên chấp nhận hoặc từ chối lời mời' },
		{ id: 'REVIEW_REMINDER',  label: '<i class="fa fa-clock-o"></i> Nhắc phản biện',       desc: 'Khi OJS gửi nhắc nhở phản biện tự động' },
		{ id: 'REVIEW_COMPLETED', label: '<i class="fa fa-check-circle"></i> PB đã nộp',       desc: 'Khi phản biện viên nộp đánh giá' }
	];

	// Clone dữ liệu nhóm hiện có (tránh reference trực tiếp)
	var zaloGroups = (typeof zaloExistingGroups !== 'undefined' && Array.isArray(zaloExistingGroups))
					 ? JSON.parse(JSON.stringify(zaloExistingGroups))
					 : [];

	/**
	 * Cập nhật hidden input với JSON data mới nhất
	 */
	function updateHiddenInput() {
		document.getElementById('recipientGroupsJson').value = JSON.stringify(zaloGroups);
	}

	function setGroupTitle(title, index, groupName) {
		title.innerHTML = '<i class="fa fa-user"></i> ';
		title.appendChild(document.createTextNode('Nhóm #' + (index + 1) + (groupName ? ' — ' + groupName : '')));
	}

	/**
	 * Render lại toàn bộ danh sách nhóm
	 */
	function renderGroups() {
		var container = document.getElementById('recipientGroupsContainer');
		container.innerHTML = '';

		if (zaloGroups.length === 0) {
			var empty = document.createElement('div');
			empty.className = 'zalo-no-groups';
			empty.textContent = 'Chưa có nhóm nào. Nhấn nút bên dưới để thêm nhóm nhận thông báo.';
			container.appendChild(empty);
		} else {
			zaloGroups.forEach(function(group, index) {
				container.appendChild(createGroupCard(group, index));
			});
		}

		updateHiddenInput();
	}

	/**
	 * Tạo card HTML cho một nhóm nhận thông báo
	 */
	function createGroupCard(group, index) {
		var card = document.createElement('div');
		card.className = 'zalo-group-card';

		// === Header ===
		var header = document.createElement('div');
		header.className = 'zalo-group-header';

		var title = document.createElement('strong');
		setGroupTitle(title, index, group.name || '');
		header.appendChild(title);

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'zalo-remove-btn';
		removeBtn.innerHTML = '<i class="fa fa-trash"></i> Xóa';
		removeBtn.onclick = function() {
			if (confirm('Bạn có chắc muốn xóa nhóm "' + (group.name || 'Nhóm #' + (index+1)) + '"?')) {
				zaloGroups.splice(index, 1);
				renderGroups();
			}
		};
		header.appendChild(removeBtn);
		card.appendChild(header);

		// === Tên nhóm ===
		var nameLabel = document.createElement('label');
		nameLabel.className = 'zalo-group-label';
		nameLabel.innerHTML = '<i class="fa fa-pencil"></i> Tên nhóm:';
		card.appendChild(nameLabel);

		var nameInput = document.createElement('input');
		nameInput.type = 'text';
		nameInput.className = 'zalo-group-input';
		nameInput.value = group.name || '';
		nameInput.placeholder = 'VD: Tổng biên tập, Ban biên tập, Thư ký tòa soạn...';
		nameInput.onchange = function() {
			zaloGroups[index].name = this.value;
			// Cập nhật title hiển thị
			setGroupTitle(title, index, this.value);
			updateHiddenInput();
		};
		card.appendChild(nameInput);

		// === Số điện thoại ===
		var phonesLabel = document.createElement('label');
		phonesLabel.className = 'zalo-group-label';
		phonesLabel.innerHTML = '<i class="fa fa-mobile" style="font-size: 1.2em;"></i> Số điện thoại (nhiều số cách nhau bởi dấu phẩy):';
		card.appendChild(phonesLabel);

		var phonesInput = document.createElement('input');
		phonesInput.type = 'text';
		phonesInput.className = 'zalo-group-input';
		phonesInput.value = (group.phones || []).join(', ');
		phonesInput.placeholder = 'VD: 0912345678, 0987654321';
		phonesInput.onchange = function() {
			zaloGroups[index].phones = this.value
				.split(',')
				.map(function(p) { return p.trim(); })
				.filter(function(p) { return p !== ''; });
			updateHiddenInput();
		};
		card.appendChild(phonesInput);

		// === Loại thông báo ===
		var eventsLabel = document.createElement('label');
		eventsLabel.className = 'zalo-group-label';
		eventsLabel.innerHTML = '<i class="fa fa-envelope"></i> Loại thông báo muốn nhận:';
		card.appendChild(eventsLabel);

		var eventsGrid = document.createElement('div');
		eventsGrid.className = 'zalo-events-grid';

		EVENT_TYPES.forEach(function(evt) {
			var label = document.createElement('label');
			label.className = 'zalo-event-checkbox';
			label.title = evt.desc;

			var isChecked = (group.events || []).indexOf(evt.id) !== -1;
			if (isChecked) label.classList.add('checked');

			var cb = document.createElement('input');
			cb.type = 'checkbox';
			cb.checked = isChecked;
			cb.onchange = function() {
				if (!zaloGroups[index].events) zaloGroups[index].events = [];
				if (this.checked) {
					if (zaloGroups[index].events.indexOf(evt.id) === -1) {
						zaloGroups[index].events.push(evt.id);
					}
					label.classList.add('checked');
				} else {
					zaloGroups[index].events = zaloGroups[index].events.filter(function(e) { return e !== evt.id; });
					label.classList.remove('checked');
				}
				updateHiddenInput();
			};
			label.appendChild(cb);

			var span = document.createElement('span');
			span.innerHTML = ' ' + evt.label;
			label.appendChild(span);

			eventsGrid.appendChild(label);
		});

		card.appendChild(eventsGrid);
		return card;
	}

	// === Khởi tạo ===
	renderGroups();

	// === Nút thêm nhóm ===
	document.getElementById('addGroupBtn').onclick = function() {
		zaloGroups.push({
			name: '',
			phones: [],
			events: ['SUBMISSION', 'DECISION', 'PUBLISH', 'UNPUBLISH', 'REVIEW_REQUEST', 'REVIEW_RESPONSE', 'REVIEW_REMINDER', 'REVIEW_COMPLETED']
		});
		renderGroups();

		// Scroll xuống nhóm mới
		var container = document.getElementById('recipientGroupsContainer');
		var lastCard = container.lastElementChild;
		if (lastCard) {
			lastCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			// Focus vào ô tên nhóm
			var nameInput = lastCard.querySelector('input[type="text"]');
			if (nameInput) setTimeout(function() { nameInput.focus(); }, 300);
		}
	};

	// === Safety: cập nhật hidden input trước khi submit ===
	$('#zaloSettingsForm').on('submit', function() {
		updateHiddenInput();
	});
})();
</script>
{/literal}
