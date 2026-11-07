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
