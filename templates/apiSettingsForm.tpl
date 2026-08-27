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

		<div style="margin-bottom: 15px;">
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
