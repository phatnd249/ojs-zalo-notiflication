(function($) {
    'use strict';

    function mountReviewerAssessmentPrototype() {
        var $form = $('#reviewStep3Form');
        if (!$form.length || $form.find('#zaloReviewerAssessmentPrototype').length) {
            return;
        }

        var $comments = $form.find('[name="comments"]').first();
        if (!$comments.length) {
            return;
        }

        var $target = $comments.closest('.section');
        if (!$target.length) {
            return;
        }

        var $prototype = $(
            '<div id="zaloReviewerAssessmentPrototype" class="zalo-review-assessment-prototype">' +
                '<button type="button" class="pkp_button zalo-review-assessment-toggle" aria-haspopup="dialog" aria-controls="zaloReviewerAssessmentModal">' +
                    'Mở bảng đánh giá' +
                '</button>' +
                '<div id="zaloReviewerAssessmentModal" class="zalo-review-assessment-modal" role="dialog" aria-modal="true" aria-labelledby="zaloReviewerAssessmentTitle" hidden>' +
                    '<div class="zalo-review-assessment-dialog">' +
                        '<div class="zalo-review-assessment-header">' +
                            '<h3 id="zaloReviewerAssessmentTitle">Bảng đánh giá thử nghiệm</h3>' +
                            '<button type="button" class="zalo-review-assessment-close" aria-label="Đóng bảng đánh giá">&times;</button>' +
                        '</div>' +
                        '<div class="zalo-review-assessment-body">' +
                            '<p class="zalo-review-assessment-greeting">Hi phản biện viên</p>' +
                            '<div class="zalo-review-assessment-table-wrap">' +
                                '<table>' +
                                    '<thead>' +
                                        '<tr><th rowspan="2" class="zalo-col-number">STT</th><th rowspan="2" class="zalo-col-criterion">Tiêu chí</th><th colspan="4">Mức độ đánh giá</th></tr>' +
                                        '<tr><th>Không đạt</th><th>Trung bình</th><th>Khá</th><th>Tốt</th></tr>' +
                                    '</thead>' +
                                    '<tbody>' +
                                        '<tr><td>1</td><td class="zalo-criterion-text">Tính mới, tính độc đáo</td>' +
                                            '<td><input type="radio" name="zaloCriterion1" value="not_met" aria-label="Tính mới: Không đạt"></td><td><input type="radio" name="zaloCriterion1" value="average" aria-label="Tính mới: Trung bình"></td><td><input type="radio" name="zaloCriterion1" value="good" aria-label="Tính mới: Khá"></td><td><input type="radio" name="zaloCriterion1" value="excellent" aria-label="Tính mới: Tốt"></td></tr>' +
                                        '<tr><td>2</td><td class="zalo-criterion-text">Chất lượng và hàm lượng khoa học của bài viết</td>' +
                                            '<td><input type="radio" name="zaloCriterion2" value="not_met" aria-label="Chất lượng: Không đạt"></td><td><input type="radio" name="zaloCriterion2" value="average" aria-label="Chất lượng: Trung bình"></td><td><input type="radio" name="zaloCriterion2" value="good" aria-label="Chất lượng: Khá"></td><td><input type="radio" name="zaloCriterion2" value="excellent" aria-label="Chất lượng: Tốt"></td></tr>' +
                                        '<tr><td>3</td><td class="zalo-criterion-text">Cách trình bày bài viết</td>' +
                                            '<td><input type="radio" name="zaloCriterion3" value="not_met" aria-label="Trình bày: Không đạt"></td><td><input type="radio" name="zaloCriterion3" value="average" aria-label="Trình bày: Trung bình"></td><td><input type="radio" name="zaloCriterion3" value="good" aria-label="Trình bày: Khá"></td><td><input type="radio" name="zaloCriterion3" value="excellent" aria-label="Trình bày: Tốt"></td></tr>' +
                                        '<tr><td>4</td><td class="zalo-criterion-text">Tổng quan và tài liệu tham khảo</td>' +
                                            '<td><input type="radio" name="zaloCriterion4" value="not_met" aria-label="Tài liệu tham khảo: Không đạt"></td><td><input type="radio" name="zaloCriterion4" value="average" aria-label="Tài liệu tham khảo: Trung bình"></td><td><input type="radio" name="zaloCriterion4" value="good" aria-label="Tài liệu tham khảo: Khá"></td><td><input type="radio" name="zaloCriterion4" value="excellent" aria-label="Tài liệu tham khảo: Tốt"></td></tr>' +
                                    '</tbody>' +
                                '</table>' +
                            '</div>' +
                            '<fieldset class="zalo-review-overall-rating">' +
                                '<legend>Đánh giá chung</legend>' +
                                '<label><input type="radio" name="zaloOverallRating" value="accept"> <span>Chấp nhận</span></label>' +
                                '<label><input type="radio" name="zaloOverallRating" value="minor_revision"> <span>Chỉnh sửa không cần gửi lại phản biện</span></label>' +
                                '<label><input type="radio" name="zaloOverallRating" value="major_revision"> <span>Yêu cầu chỉnh sửa</span></label>' +
                                '<label><input type="radio" name="zaloOverallRating" value="reject"> <span>Không chấp nhận</span></label>' +
                            '</fieldset>' +
                            '<div class="zalo-review-assessment-note">' +
                                '<strong>Lưu ý:</strong>' +
                                '<ul>' +
                                    '<li>Nhà phản biện cần tuân thủ tính bảo mật thông tin bài báo.</li>' +
                                    '<li>Các tài liệu hoặc thông tin liên quan được nêu trong bản thảo gửi đến Tạp chí mà chưa được công bố thì không được sử dụng cho mục đích riêng của bất kỳ thành viên nào nếu chưa được sự đồng ý của tác giả.</li>' +
                                '</ul>' +
                            '</div>' +
                            '<div class="zalo-review-assessment-signature">' +
                                '<span>............, ngày ...... tháng ...... năm ......</span>' +
                                '<strong>Người phản biện</strong>' +
                            '</div>' +
                            '<div class="zalo-review-assessment-status" role="status" aria-live="polite"></div>' +
                        '</div>' +
                        '<div class="zalo-review-assessment-footer">' +
                            '<button type="button" class="pkp_button zalo-review-assessment-export">Xuất file</button>' +
                            '<button type="button" class="pkp_button pkp_button_primary zalo-review-assessment-save">Lưu</button>' +
                            '<button type="button" class="pkp_button zalo-review-assessment-close-footer">Đóng</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        var $openButton = $prototype.find('.zalo-review-assessment-toggle');
        var $modal = $prototype.find('.zalo-review-assessment-modal');
        var $closeButton = $prototype.find('.zalo-review-assessment-close');
        var $footerCloseButton = $prototype.find('.zalo-review-assessment-close-footer');
        var $status = $prototype.find('.zalo-review-assessment-status');
        var storageKey = 'zaloReviewerAssessmentDraft:' + window.location.pathname + ':' + ($form.attr('action') || 'review');
        var ratingLabels = {
            not_met: 'Không đạt',
            average: 'Trung bình',
            good: 'Khá',
            excellent: 'Tốt'
        };
        var overallLabels = {
            accept: 'Chấp nhận',
            minor_revision: 'Chỉnh sửa không cần gửi lại phản biện',
            major_revision: 'Yêu cầu chỉnh sửa',
            reject: 'Không chấp nhận'
        };

        function collectAssessment() {
            var values = {};
            $prototype.find('input[type="radio"]:checked').each(function() {
                values[this.name] = this.value;
            });
            return values;
        }

        function restoreDraft() {
            try {
                var values = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
                $.each(values, function(name, value) {
                    $prototype.find('input[type="radio"]').filter(function() {
                        return this.name === name && this.value === value;
                    }).prop('checked', true);
                });
            } catch (error) {
                // Trình duyệt không cho phép localStorage hoặc dữ liệu cũ không hợp lệ.
            }
        }

        function saveDraft() {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(collectAssessment()));
                $status.text('Đã lưu bản nháp trên trình duyệt.');
            } catch (error) {
                $status.text('Không thể lưu bản nháp trên trình duyệt này.');
            }
        }

        function exportAssessment() {
            var values = collectAssessment();
            var lines = [
                'PHIẾU ĐÁNH GIÁ',
                '',
                '1. Tính mới, tính độc đáo: ' + (ratingLabels[values.zaloCriterion1] || 'Chưa chọn'),
                '2. Chất lượng và hàm lượng khoa học của bài viết: ' + (ratingLabels[values.zaloCriterion2] || 'Chưa chọn'),
                '3. Cách trình bày bài viết: ' + (ratingLabels[values.zaloCriterion3] || 'Chưa chọn'),
                '4. Tổng quan và tài liệu tham khảo: ' + (ratingLabels[values.zaloCriterion4] || 'Chưa chọn'),
                '',
                'Đánh giá chung: ' + (overallLabels[values.zaloOverallRating] || 'Chưa chọn'),
                '',
                'Lưu ý:',
                '- Nhà phản biện cần tuân thủ tính bảo mật thông tin bài báo.',
                '- Các tài liệu hoặc thông tin liên quan được nêu trong bản thảo gửi đến Tạp chí mà chưa được công bố thì không được sử dụng cho mục đích riêng của bất kỳ thành viên nào nếu chưa được sự đồng ý của tác giả.',
                '',
                '............, ngày ...... tháng ...... năm ......',
                'Người phản biện'
            ];
            var blob = new Blob(['\uFEFF' + lines.join('\r\n')], {type: 'text/plain;charset=utf-8'});
            var url = window.URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'phieu-danh-gia.txt';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            $status.text('Đã xuất tệp phiếu đánh giá.');
        }

        function openModal() {
            $modal.prop('hidden', false);
            $('body').addClass('zalo-review-assessment-open');
            $closeButton.focus();
        }

        function closeModal() {
            $modal.prop('hidden', true);
            $('body').removeClass('zalo-review-assessment-open');
            $openButton.focus();
        }

        $openButton.on('click', openModal);
        $closeButton.on('click', closeModal);
        $footerCloseButton.on('click', closeModal);
        $prototype.find('.zalo-review-assessment-save').on('click', saveDraft);
        $prototype.find('.zalo-review-assessment-export').on('click', exportAssessment);
        $modal.on('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });
        $(document).off('keydown.zaloReviewerAssessment').on('keydown.zaloReviewerAssessment', function(event) {
            if (event.key === 'Escape' && !$modal.prop('hidden')) {
                closeModal();
            }
        });

        restoreDraft();

        $target.before($prototype);
    }

    $(mountReviewerAssessmentPrototype);
    $(document).ajaxComplete(mountReviewerAssessmentPrototype);

    if (window.MutationObserver && document.body) {
        new MutationObserver(mountReviewerAssessmentPrototype).observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})(jQuery);
