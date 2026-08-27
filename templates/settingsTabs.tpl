<script type="text/javascript">
	$(function() {ldelim}
		$('#zaloSettingsTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>

<div id="zaloSettingsTabs" class="pkp_controllers_tab">
	<ul>
		<li><a name="apiTab" href="{$apiTabUrl|escape}">Cấu hình API</a></li>
		<li><a name="settingsTab" href="{$settingsTabUrl|escape}">Cài đặt Nhóm Thông Báo</a></li>
		<li><a name="templatesTab" href="{$templatesTabUrl|escape}">Mẫu thông báo</a></li>
	</ul>
</div>
