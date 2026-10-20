<script type="text/javascript">
	$(function() {
		$('#apiSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	});
</script>

<form class="pkp_form" id="apiSettingsForm" method="post" action="{$saveUrl|escape}">
	{csrf}
	<div id="zaloApiSection" style="margin-bottom: 24px;">
		<h3 style="margin: 0 0 8px; padding-bottom: 6px; border-bottom: 2px solid #0066cc; color: #0066cc;">
			<i class="fa fa-cogs"></i> Cấu hình API Zalo
		</h3>
		<p style="color: #555; margin: 0 0 12px; font-size: 0.9em;">
			Nhập Bot ID và API Key do đơn vị cung cấp gateway cấp.
		</p>
		
		<div style="margin-bottom: 15px;">
			<label style="display:block; font-weight: 600; margin-bottom: 5px;">Bot ID:</label>
			<input type="text" name="botId" id="botId" value="{$botId|escape}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="VD: 6393437175249673998" />
		</div>

		<div style="margin-bottom: 20px;">
			<label for="apiKey" style="display:block; font-weight: 600; margin-bottom: 5px;">API Key:</label>
			<input type="password" name="apiKey" id="apiKey" value="" autocomplete="new-password" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="{if $apiKeyConfigured}Đã cấu hình — để trống để giữ nguyên{else}Nhập API Key{/if}" />
			<p style="color: #666; margin: 5px 0 0; font-size: 0.85em;">
				{if $apiKeyConfigured}
					API Key đã được cấu hình. Vì lý do bảo mật, khóa hiện tại không được hiển thị lại.
				{else}
					Chưa cấu hình API Key. Plugin sẽ không gửi được tin cho đến khi khóa được lưu.
				{/if}
			</p>
		</div>

		{* ========== THỜI GIAN LƯU TRỮ DỮ LIỆU ========== *}
		<div id="zaloRetentionSection" style="margin-bottom: 20px; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
			<h4 style="margin: 0 0 6px; color: #0f172a; font-size: 1.05em; display: flex; align-items: center; gap: 8px;">
				<i class="fa fa-archive" style="color: #0284c7;"></i> Thời gian lưu trữ dữ liệu (Data Retention)
			</h4>
			<p style="color: #64748b; margin: 0 0 14px; font-size: 0.85em; line-height: 1.45;">
				Quy định thời hạn lưu giữ nhật ký và hàng đợi tin nhắn. Bản ghi cũ hơn số ngày cấu hình sẽ được tự động dọn dẹp để tối ưu hóa hiệu năng và dung lượng lưu trữ.
			</p>
			
			<div style="display: flex; gap: 16px; flex-wrap: wrap;">
				<div style="flex: 1; min-width: 220px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px;">
					<label for="logRetentionDays" style="display:block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9em;">
						<i class="fa fa-file-text-o" style="color: #0284c7;"></i> Nhật ký hoạt động (Activity Log):
					</label>
					<div style="display: flex; align-items: center; gap: 8px;">
						<input type="number" name="logRetentionDays" id="logRetentionDays" min="1" max="730" value="{$logRetentionDays|escape}" style="width: 100px; padding: 6px 10px; border: 1px solid #94a3b8; border-radius: 4px; font-size: 14px;" placeholder="90" />
						<span style="color: #475569; font-size: 0.9em; font-weight: 500;">ngày</span>
					</div>
					<small style="color: #64748b; display: block; margin-top: 5px; font-size: 0.8em;">Mặc định: 90 ngày. Tự động thanh lọc các bản ghi log cũ.</small>
				</div>

				<div style="flex: 1; min-width: 220px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px;">
					<label for="outboxRetentionDays" style="display:block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9em;">
						<i class="fa fa-history" style="color: #0284c7;"></i> Hàng đợi & Thử lại (Outbox):
					</label>
					<div style="display: flex; align-items: center; gap: 8px;">
						<input type="number" name="outboxRetentionDays" id="outboxRetentionDays" min="1" max="365" value="{$outboxRetentionDays|escape}" style="width: 100px; padding: 6px 10px; border: 1px solid #94a3b8; border-radius: 4px; font-size: 14px;" placeholder="30" />
						<span style="color: #475569; font-size: 0.9em; font-weight: 500;">ngày</span>
					</div>
					<small style="color: #64748b; display: block; margin-top: 5px; font-size: 0.8em;">Mặc định: 30 ngày. Dọn các tin nhắn đã gửi hoặc thất bại.</small>
				</div>
			</div>
		</div>

		<div style="padding:12px 14px;border:1px solid #f0ad4e;border-radius:6px;background:#fff8e5;color:#5f4600;line-height:1.5;">
			<strong><i class="fa fa-shield"></i> Lưu ý về quyền riêng tư</strong>
			<div style="margin-top:5px;">
				Số điện thoại và nội dung thông báo sẽ được truyền đến dịch vụ gateway Zalo của bên thứ ba.
				Quản trị viên cần thông báo cho người dùng, xác định cơ sở xử lý dữ liệu phù hợp và chỉ gửi thông tin thực sự cần thiết.
			</div>
		</div>
	</div>

	<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; text-align: right;">
		{fbvFormButtons submitText="common.save"}
	</div>
</form>
