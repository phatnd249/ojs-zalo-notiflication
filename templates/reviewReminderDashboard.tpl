<form id="zaloReviewReminderForm" method="post" action="{$sendReminderUrl|escape}">{csrf}</form>

<div class="zalo-review-dashboard">
    <div class="zrd-heading">
        <div>
            <h3><i class="fa fa-bell"></i> Dashboard nhắc phản biện</h3>
            <p>Các lượt phản biện chưa hoàn tất ở giai đoạn phản biện nội bộ hoặc phản biện ngoài.</p>
        </div>
        <div class="zrd-summary">
            <span><strong>{$submissionCount}</strong> bài báo</span>
            <span><strong>{$reviewerCount}</strong> lượt phản biện</span>
            <span class="is-overdue"><strong>{$overdueCount}</strong> quá hạn</span>
        </div>
    </div>

    <div id="zrdNotice" class="zrd-notice" role="status" aria-live="polite"></div>

    {if !empty($submissions)}
        <div class="zrd-tools">
            <label class="zrd-search"><i class="fa fa-search"></i><input type="search" id="zrdSearch" placeholder="Tìm ID, tiêu đề, tác giả, phản biện viên..." /></label>
            <select id="zrdFilter" aria-label="Lọc theo hạn phản biện">
                <option value="all">Tất cả trạng thái</option>
                <option value="overdue">Đang quá hạn</option>
                <option value="today">Hết hạn hôm nay</option>
                <option value="next7">Hết hạn trong 7 ngày</option>
                <option value="sendable">Có thể gửi nhắc</option>
                <option value="missingPhone">Thiếu số điện thoại</option>
            </select>
            <select id="zrdSort" aria-label="Sắp xếp danh sách bài">
                <option value="deadline">Hạn gần nhất</option>
                <option value="idDesc">Bài mới nhất</option>
                <option value="title">Theo tiêu đề</option>
            </select>
            <button type="button" class="pkp_button" id="zrdCollapseAll"><i class="fa fa-compress"></i> Thu gọn tất cả</button>
        </div>
        <div class="zrd-result-count" id="zrdResultCount"></div>
        <div class="zrd-no-results" id="zrdNoResults"><i class="fa fa-search"></i> Không tìm thấy bài báo phù hợp với điều kiện lọc.</div>
    {/if}

    {if empty($submissions)}
        <div class="zrd-empty"><i class="fa fa-check-circle"></i> Không có lượt phản biện nào đang mở.</div>
    {else}
        <div class="zrd-list-scroll" id="zrdListScroll" tabindex="0" aria-label="Danh sách bài báo cần nhắc phản biện">
        {foreach from=$submissions item=submission}
            <section class="zrd-card" data-submission-id="{$submission.id}">
                <header class="zrd-card-header">
                    <div>
                        <div class="zrd-id">Bài #{$submission.id} · {$submission.stageName|escape}</div>
                        <h4>{if $submission.workflowUrl}<a href="{$submission.workflowUrl|escape}" target="_blank" rel="noopener">{$submission.title|escape}</a>{else}{$submission.title|escape}{/if}</h4>
                        <div class="zrd-meta"><span><i class="fa fa-user"></i> {$submission.authors|escape}</span><span><i class="fa fa-calendar"></i> Nộp ngày {$submission.dateSubmitted|escape}</span></div>
                    </div>
                    <div class="zrd-header-actions">
                        <button type="button" class="pkp_button zrd-toggle" aria-expanded="true"><i class="fa fa-chevron-up"></i> Thu gọn</button>
                        <button type="button" class="pkp_button pkp_button_primary zrd-send" data-scope="submission" data-id="{$submission.id}" {if $submission.sendableCount == 0}disabled title="Không có phản biện viên đủ điều kiện nhận tin"{/if}>
                            <i class="fa fa-bell"></i> Nhắc cả bài ({$submission.sendableCount})
                        </button>
                    </div>
                </header>

                <div class="zrd-table-wrap zrd-card-body">
                    <table class="zrd-table">
                        <thead><tr><th>Phản biện viên</th><th>Tiến độ</th><th>Hạn phản biện</th><th>Nhắc gần nhất</th><th></th></tr></thead>
                        <tbody>
                        {foreach from=$submission.reviewers item=reviewer}
                            <tr data-review-id="{$reviewer.reviewId}" data-due-class="{$reviewer.dueClass|escape}" data-days-left="{$reviewer.daysLeft}" data-can-send="{if $reviewer.canSend}1{else}0{/if}" data-valid-phone="{if $reviewer.hasValidPhone}1{else}0{/if}">
                                <td>
                                    <strong>{$reviewer.name|escape}</strong>
                                    <small>{if $reviewer.email}<a href="mailto:{$reviewer.email|escape}">{$reviewer.email|escape}</a>{else}Không có email{/if}</small>
                                    <small><i class="fa fa-phone"></i> {if $reviewer.phone}{$reviewer.phone|escape}{else}<span class="zrd-warning">Chưa có số điện thoại</span>{/if}</small>
                                </td>
                                <td><span class="zrd-response">{$reviewer.responseStatus|escape}</span><small>Vòng {$reviewer.round} · Gán {$reviewer.dateAssigned|escape}</small></td>
                                <td><strong>{$reviewer.dateDue|escape}</strong><span class="zrd-due is-{$reviewer.dueClass|escape}">{$reviewer.dueLabel|escape}</span><small>Hạn phản hồi: {$reviewer.dateResponseDue|escape}</small></td>
                                <td class="zrd-last-reminded">{$reviewer.dateReminded|escape}</td>
                                <td class="zrd-action"><button type="button" class="pkp_button zrd-send" data-scope="reviewer" data-id="{$reviewer.reviewId}" {if !$reviewer.canSend}disabled title="{$reviewer.sendDisabledReason|escape}"{/if}><i class="fa fa-paper-plane"></i> Nhắc người này</button></td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                </div>
            </section>
        {/foreach}
        <nav class="zrd-pagination" id="zrdPagination" aria-label="Phân trang dashboard phản biện">
            <button type="button" class="pkp_button" id="zrdPrev"><i class="fa fa-chevron-left"></i> Trước</button>
            <span id="zrdPageInfo"></span>
            <button type="button" class="pkp_button" id="zrdNext">Sau <i class="fa fa-chevron-right"></i></button>
        </nav>
        </div>
    {/if}
</div>

{literal}
<style>
.zalo-review-dashboard{padding:4px 2px 12px;color:#25323d}.zrd-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:18px}.zrd-heading h3{color:#0066a6;margin:0 0 5px;font-size:1.3em}.zrd-heading p{margin:0;color:#65717c}.zrd-summary{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.zrd-summary span{background:#eef5fa;border:1px solid #d8e7f1;border-radius:18px;padding:7px 11px;white-space:nowrap}.zrd-summary .is-overdue{background:#fff1f0;border-color:#ffc9c5;color:#a42620}.zrd-notice{display:none;margin:0 0 14px;padding:10px 12px;border-radius:5px}.zrd-notice.success{display:block;background:#eaf7ee;border:1px solid #a8d8b5;color:#246b38}.zrd-notice.error{display:block;background:#fff0ef;border:1px solid #e8aaa5;color:#8d2721}.zrd-tools{position:sticky;top:0;z-index:5;display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:10px;margin:0 0 4px;background:#f6f9fb;border:1px solid #dce5eb;border-radius:6px}.zrd-tools select,.zrd-search input{height:35px;border:1px solid #b8c5ce;border-radius:4px;background:#fff;padding:0 9px}.zrd-search{position:relative;flex:1;min-width:260px}.zrd-search i{position:absolute;left:10px;top:10px;color:#7d8b95}.zrd-search input{box-sizing:border-box;width:100%;padding-left:30px}.zrd-result-count{color:#64727d;font-size:.87em;margin:6px 2px 10px}.zrd-no-results{display:none;text-align:center;padding:24px;margin-bottom:12px;background:#f7f9fa;border:1px dashed #bdc8d0;border-radius:6px;color:#667681}.zrd-list-scroll{max-height:65vh;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable;padding-right:7px}.zrd-list-scroll:focus{outline:2px solid #80b8da;outline-offset:2px}.zrd-card{border:1px solid #d8e0e6;border-radius:7px;margin:0 0 16px;background:#fff;overflow:hidden}.zrd-card-header{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:15px 17px;background:#f7fafc;border-bottom:1px solid #e2e8ed}.zrd-card.is-collapsed .zrd-card-header{border-bottom:0}.zrd-header-actions{display:flex;gap:7px;align-items:center;flex-shrink:0}.zrd-id{font-size:.82em;color:#697986;text-transform:uppercase;font-weight:600}.zrd-card h4{font-size:1.08em;margin:4px 0 6px}.zrd-card h4 a{color:#0066a6}.zrd-meta{display:flex;gap:18px;flex-wrap:wrap;color:#596875;font-size:.9em}.zrd-meta i{color:#85939e}.zrd-table-wrap{overflow-x:auto}.zrd-table{width:100%;border-collapse:collapse;min-width:850px}.zrd-table th{background:#fbfcfd;text-align:left;color:#52616d;font-size:.82em;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid #dfe5e9}.zrd-table td{padding:12px;vertical-align:top;border-bottom:1px solid #edf0f2}.zrd-table tbody tr:last-child td{border-bottom:0}.zrd-table small{display:block;margin-top:4px;color:#6b7882}.zrd-response{display:inline-block;background:#edf3f7;border-radius:12px;padding:3px 8px;font-size:.86em}.zrd-due{display:block;font-size:.85em;font-weight:600;margin-top:4px}.zrd-due.is-overdue{color:#b42318}.zrd-due.is-today{color:#b65e00}.zrd-due.is-upcoming{color:#287542}.zrd-due.is-none,.zrd-warning{color:#9a6715}.zrd-action{text-align:right}.zrd-send[disabled]{opacity:.48;cursor:not-allowed}.zrd-empty{text-align:center;padding:35px;background:#f1faf4;border:1px solid #b9dfc3;border-radius:6px;color:#347445;font-size:1.05em}.zrd-empty i{margin-right:6px}.zrd-pagination{display:flex;justify-content:center;align-items:center;gap:14px;margin:18px 0 4px}.zrd-pagination span{min-width:110px;text-align:center;color:#586773}.zrd-pagination button[disabled]{opacity:.45}@media(max-width:800px){.zrd-heading,.zrd-card-header{flex-direction:column;align-items:stretch}.zrd-summary{justify-content:flex-start}.zrd-header-actions{justify-content:space-between}.zrd-tools>*{width:100%}.zrd-list-scroll{max-height:70vh;padding-right:4px}}
</style>
<script>
$(function(){
    var $form=$('#zaloReviewReminderForm'),$notice=$('#zrdNotice'),$list=$('#zrdListScroll'),$cards=$('.zrd-card'),page=1,pageSize=6,filtered=[];

    function normalize(value){
        value=(value||'').toString().toLowerCase();
        return value.normalize?value.normalize('NFD').replace(/[\u0300-\u036f]/g,''):value;
    }
    $cards.each(function(index){
        var $card=$(this),deadlines=[];
        $card.attr('data-original-index',index).data('search',normalize($card.text()));
        $card.find('tr[data-review-id]').each(function(){var n=parseInt($(this).attr('data-days-left'),10);if($(this).attr('data-due-class')!=='none'&&!isNaN(n))deadlines.push(n);});
        $card.data('nearest',deadlines.length?Math.min.apply(Math,deadlines):999999);
    });
    function matchesFilter($card,filter){
        var $rows=$card.find('tr[data-review-id]');
        if(filter==='overdue')return $rows.filter('[data-due-class="overdue"]').length>0;
        if(filter==='today')return $rows.filter('[data-due-class="today"]').length>0;
        if(filter==='sendable')return $rows.filter('[data-can-send="1"]').length>0;
        if(filter==='missingPhone')return $rows.filter('[data-valid-phone="0"]').length>0;
        if(filter==='next7')return $rows.filter(function(){var n=parseInt($(this).attr('data-days-left'),10);return n>=0&&n<=7;}).length>0;
        return true;
    }
    function refresh(resetPage){
        if(resetPage){page=1;$list.scrollTop(0);}
        var query=normalize($('#zrdSearch').val()),filter=$('#zrdFilter').val(),sort=$('#zrdSort').val();
        filtered=$cards.filter(function(){var $card=$(this);return(!query||$card.data('search').indexOf(query)>-1)&&matchesFilter($card,filter);}).get();
        filtered.sort(function(a,b){
            var $a=$(a),$b=$(b);
            if(sort==='idDesc')return parseInt($b.attr('data-submission-id'),10)-parseInt($a.attr('data-submission-id'),10);
            if(sort==='title')return normalize($a.find('h4').text()).localeCompare(normalize($b.find('h4').text()));
            return $a.data('nearest')-$b.data('nearest');
        });
        $.each(filtered,function(){ $(this).insertBefore('#zrdPagination'); });
        var pages=Math.max(1,Math.ceil(filtered.length/pageSize));if(page>pages)page=pages;
        $cards.hide();$.each(filtered,function(i){if(i>=(page-1)*pageSize&&i<page*pageSize)$(this).show();});
        $('#zrdResultCount').text('Tìm thấy '+filtered.length+' bài báo');
        $('#zrdNoResults').toggle(filtered.length===0);
        $('#zrdPageInfo').text('Trang '+page+' / '+pages);
        $('#zrdPrev').prop('disabled',page<=1);$('#zrdNext').prop('disabled',page>=pages);$('#zrdPagination').toggle(filtered.length>pageSize);
    }
    var searchTimer;
    $('#zrdSearch').on('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){refresh(true);},180);});
    $('#zrdFilter,#zrdSort').on('change',function(){refresh(true);});
    $('#zrdPrev').on('click',function(){if(page>1){page--;refresh(false);$list.scrollTop(0);}});
    $('#zrdNext').on('click',function(){if(page*pageSize<filtered.length){page++;refresh(false);$list.scrollTop(0);}});
    $('.zrd-toggle').on('click',function(){
        var $button=$(this),$card=$button.closest('.zrd-card'),collapsed=!$card.hasClass('is-collapsed');
        $card.toggleClass('is-collapsed',collapsed).find('.zrd-card-body').toggle(!collapsed);
        $button.attr('aria-expanded',collapsed?'false':'true').html(collapsed?'<i class="fa fa-chevron-down"></i> Mở rộng':'<i class="fa fa-chevron-up"></i> Thu gọn');
    });
    $('#zrdCollapseAll').on('click',function(){
        var collapse=$(this).data('collapsed')!==true;
        $cards.toggleClass('is-collapsed',collapse).find('.zrd-card-body').toggle(!collapse);
        $('.zrd-toggle').attr('aria-expanded',collapse?'false':'true').html(collapse?'<i class="fa fa-chevron-down"></i> Mở rộng':'<i class="fa fa-chevron-up"></i> Thu gọn');
        $(this).data('collapsed',collapse).html(collapse?'<i class="fa fa-expand"></i> Mở rộng tất cả':'<i class="fa fa-compress"></i> Thu gọn tất cả');
    });
    refresh(true);

    $('.zrd-send').on('click',function(){
        var $button=$(this),scope=$button.data('scope'),id=$button.data('id');
        if(!window.confirm(scope==='submission'?'Gửi lời nhắc tới tất cả phản biện viên đủ điều kiện của bài này?':'Gửi lời nhắc tới phản biện viên này?'))return;
        var data=$form.serializeArray();
        data.push({name:'scope',value:scope});
        data.push({name:scope==='submission'?'submissionId':'reviewId',value:id});
        $button.prop('disabled',true);$notice.removeClass('success error').hide();
        $.ajax({url:$form.attr('action'),method:'POST',data:data,dataType:'json'}).done(function(response){
            var ok=response&&response.status===true;
            $notice.addClass(ok?'success':'error').text((response&&response.content)||'Không nhận được phản hồi từ hệ thống.').show();
            if(ok&&scope==='reviewer'){$('tr[data-review-id="'+id+'"]').find('.zrd-last-reminded').text('Vừa gửi');}
        }).fail(function(){$notice.addClass('error').text('Không thể gửi yêu cầu. Vui lòng thử lại.').show();}).always(function(){$button.prop('disabled',false);});
    });
});
</script>
{/literal}
