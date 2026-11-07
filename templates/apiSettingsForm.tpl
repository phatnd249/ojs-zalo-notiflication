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
			{if $apiKeyConfigured}
				<label style="display: flex; align-items: center; gap: 6px; margin-top: 8px; color: #dc2626; font-size: 0.85em; cursor: pointer;">
					<input type="checkbox" name="clearApiKey" id="clearApiKey" value="1" style="cursor: pointer;" />
					<span>Xóa / Thu hồi API Key đã lưu (Plugin sẽ ngừng gửi tin cho đến khi nhập lại key mới)</span>
				</label>
			{/if}
		</div>

		{* ========== CHẾ ĐỘ GỬI TIN NHẮN (QUEUE-FIRST MODE) ========== *}
		<div id="zaloDispatchModeSection" style="margin-bottom: 20px; padding: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
			<h4 style="margin: 0 0 6px; color: #166534; font-size: 1.05em; display: flex; align-items: center; gap: 8px;">
				<i class="fa fa-bolt" style="color: #16a34a;"></i> Chế độ gửi tin bất đồng bộ (Queue-First)
				<span style="font-size: 0.75em; background: #22c55e; color: #ffffff; padding: 2px 7px; border-radius: 12px; font-weight: 600;">Khuyên dùng</span>
			</h4>
			<p style="color: #15803d; margin: 0 0 10px; font-size: 0.85em; line-height: 1.45;">
				Khi kích hoạt, mọi thao tác chuyển giai đoạn bài báo, ra quyết định, mời phản biện hoặc nộp bài sẽ đưa tin nhắn vào Hàng đợi Outbox trong vòng vài mili-giây, giúp thao tác của Biên tập viên và Tác giả diễn ra tức thì mà <strong>không bị đơ hoặc chậm trang web</strong>. Tiến trình chạy ngầm (Cron) sẽ tự động gửi tin đến Zalo.
			</p>
			<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #166534; font-size: 0.9em;">
				<input type="checkbox" name="queueFirstMode" id="queueFirstMode" value="1" {if $queueFirstMode}checked="checked"{/if} style="width: 17px; height: 17px; cursor: pointer;" />
				<span>Bật chế độ Hàng đợi bất đồng bộ (Queue-First Dispatch) — Tối ưu tốc độ chuyển giai đoạn</span>
			</label>
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

		{* ========== LỊCH NHẮC HẠN PHẢN BIỆN TỰ ĐỘNG ========== *}
		<div id="zaloReviewScheduleSection" style="margin-bottom: 20px; padding: 16px; background: #f0f7ff; border: 1px solid #bae0ff; border-radius: 8px;">
			<h4 style="margin: 0 0 6px; color: #0050b3; font-size: 1.05em; display: flex; align-items: center; gap: 8px;">
				<i class="fa fa-bell" style="color: #1890ff;"></i> Lịch nhắc hạn phản biện tự động
			</h4>
			<p style="color: #264a7f; margin: 0 0 12px; font-size: 0.85em; line-height: 1.45;">
				Hệ thống tự động quét và gửi tin nhắn Zalo nhắc nhở cho Phản biện viên và Biên tập viên khi sắp đến hạn hoặc quá hạn nộp bài phản biện.
			</p>

			<!-- Nhắc trước hạn chót: Mặc định 4 ngày, có thể điều chỉnh -->
			<div style="background: #ffffff; border: 1px solid #d9d9d9; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
				<label for="reviewReminderBeforeDays" style="display:block; font-weight: 600; margin-bottom: 6px; color: #262626; font-size: 0.9em;">
					<i class="fa fa-calendar-check-o" style="color: #52c41a;"></i> Nhắc trước hạn chót (Upcoming Reminder):
				</label>
				<div style="display: flex; align-items: center; gap: 8px;">
					<span style="color: #595959; font-size: 0.85em;">Trước</span>
					<input type="number" name="reviewReminderBeforeDays" id="reviewReminderBeforeDays" min="0" max="30" value="{$reviewReminderBeforeDays|escape}" style="width: 70px; padding: 6px 8px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 14px; text-align: center;" />
					<span style="color: #595959; font-size: 0.85em; font-weight: 500;">ngày</span>
				</div>
				<small style="color: #8c8c8c; display: block; margin-top: 5px; font-size: 0.78em;">Mặc định: 4 ngày. Nhập 0 nếu không muốn gửi thông báo trước hạn.</small>
			</div>

			<!-- Nhắc đúng ngày hết hạn -->
			<div style="background: #ffffff; border: 1px solid #d9d9d9; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px;">
				<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #262626; font-size: 0.9em;">
					<input type="checkbox" name="reviewReminderOnDueDate" id="reviewReminderOnDueDate" value="1" {if $reviewReminderOnDueDate}checked="checked"{/if} style="width: 16px; height: 16px; cursor: pointer;" />
					<span>Nhắc vào đúng ngày hết hạn (Due Date Reminder)</span>
				</label>
				<p style="color: #595959; margin: 4px 0 0 24px; font-size: 0.82em;">Gửi tin nhắc vào đúng ngày hạn chót nộp kết quả phản biện (Hôm nay là hạn chót).</p>
			</div>

			<!-- Tần suất quá hạn & Số lần tối đa -->
			<div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px;">
				<div style="flex: 1; min-width: 220px; background: #ffffff; border: 1px solid #d9d9d9; border-radius: 6px; padding: 12px;">
					<label for="reviewReminderOverdueFrequency" style="display:block; font-weight: 600; margin-bottom: 6px; color: #262626; font-size: 0.9em;">
						<i class="fa fa-repeat" style="color: #fa8c16;"></i> Tần suất nhắc khi quá hạn:
					</label>
					<div style="display: flex; align-items: center; gap: 8px;">
						<span style="color: #595959; font-size: 0.85em;">Sau mỗi</span>
						<input type="number" name="reviewReminderOverdueFrequency" id="reviewReminderOverdueFrequency" min="1" max="30" value="{$reviewReminderOverdueFrequency|escape}" style="width: 70px; padding: 6px 8px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 14px; text-align: center;" />
						<span style="color: #595959; font-size: 0.85em; font-weight: 500;">ngày</span>
					</div>
					<small style="color: #8c8c8c; display: block; margin-top: 5px; font-size: 0.78em;">Mặc định: 2 ngày. Cách 2 ngày nhắc 1 lần để tránh làm phiền.</small>
				</div>

				<div style="flex: 1; min-width: 220px; background: #ffffff; border: 1px solid #d9d9d9; border-radius: 6px; padding: 12px;">
					<label for="reviewReminderMaxOverdueCount" style="display:block; font-weight: 600; margin-bottom: 6px; color: #262626; font-size: 0.9em;">
						<i class="fa fa-ban" style="color: #f5222d;"></i> Số lần nhắc quá hạn tối đa:
					</label>
					<div style="display: flex; align-items: center; gap: 8px;">
						<span style="color: #595959; font-size: 0.85em;">Tối đa</span>
						<input type="number" name="reviewReminderMaxOverdueCount" id="reviewReminderMaxOverdueCount" min="1" max="20" value="{$reviewReminderMaxOverdueCount|escape}" style="width: 70px; padding: 6px 8px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 14px; text-align: center;" />
						<span style="color: #595959; font-size: 0.85em; font-weight: 500;">lần</span>
					</div>
					<small style="color: #8c8c8c; display: block; margin-top: 5px; font-size: 0.78em;">Mặc định: 3 lần. Sau 3 lần tự động dừng để BTV tìm người thay thế.</small>
				</div>
			</div>

			<!-- Đối tượng nhận tin -->
			<div style="background: #ffffff; border: 1px solid #d9d9d9; border-radius: 6px; padding: 10px 14px;">
				<span style="display: block; font-weight: 600; color: #262626; font-size: 0.9em; margin-bottom: 8px;">
					<i class="fa fa-users" style="color: #1890ff;"></i> Đối tượng nhận tin nhắc tự động:
				</span>
				<div style="display: flex; gap: 20px; flex-wrap: wrap;">
					<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85em; color: #262626;">
						<input type="checkbox" name="reviewReminderSendReviewer" id="reviewReminderSendReviewer" value="1" {if $reviewReminderSendReviewer}checked="checked"{/if} style="cursor: pointer;" />
						<span>Phản biện viên (Reviewer)</span>
					</label>
					<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85em; color: #262626;">
						<input type="checkbox" name="reviewReminderSendEditor" id="reviewReminderSendEditor" value="1" {if $reviewReminderSendEditor}checked="checked"{/if} style="cursor: pointer;" />
						<span>Biên tập viên phụ trách (Editor)</span>
					</label>
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
