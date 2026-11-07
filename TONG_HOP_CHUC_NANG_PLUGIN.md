# TỔNG HỢP TOÀN BỘ CHỨC NĂNG CỦA PLUGIN OJS ZALO NOTIFICATION
### Hướng dẫn chi tiết chức năng, cơ chế vận hành và thông tin đầu ra (Outputs)

Tài liệu này tổng hợp đầy đủ và chi tiết toàn bộ các tính năng của plugin **Zalo Notification** dành cho hệ thống quản lý tạp chí khoa học OJS 3.3, bao gồm mục đích, thời điểm kích hoạt, dữ liệu đầu vào và **toàn bộ các thông tin sinh ra (đầu ra)** khi sử dụng từng chức năng.

---

## MỤC LỤC
1. [Nhóm 1: Các sự kiện trong Quy trình Xuất bản (Editorial Workflow)](#nhóm-1-các-sự-kiện-trong-quy-trình-xuất-bản)
2. [Nhóm 2: Hệ thống Nhắc hạn Phản biện Tự động & Thủ công](#nhóm-2-hệ-thống-nhắc-hạn-phản-biện)
3. [Nhóm 3: Quản lý Hàng đợi & Xử lý Sự cố (Outbox & Queue-First)](#nhóm-3-quản-lý-hàng-đợi--xử-lý-sự-cố-outbox)
4. [Nhóm 4: Nhật ký Hoạt động & Tự động Bảo trì (Activity Log)](#nhóm-4-nhật-ký-hoạt-động--tự-động-bảo-trì)
5. [Nhóm 5: Cấu hình Hệ thống, Nhóm nhận tin & Mẫu thông báo](#nhóm-5-cấu-hình-hệ-thống--mẫu-thông-báo)
6. [Nhóm 6: Hệ thống Biểu mẫu Báo cáo Phản biện Hàng tháng (Monthly Reports & Office Exports)](#nhóm-6-hệ-thống-biểu-mẫu-báo-cáo-phản-biện-hàng-tháng)
7. [Bảng tra cứu Biến số Động (Template Variables Reference)](#bảng-tra-cứu-biến-số-động)

---

## NHÓM 1: CÁC SỰ KIỆN TRONG QUY TRÌNH XUẤT BẢN

### 1. Tác giả Nộp bài mới (New Submission)
* **Mục đích:** Thông báo tức thì khi có tác giả nộp bài thành công lên tạp chí.
* **Thời điểm kích hoạt:** Tác giả hoàn thành Bước 4 của quy trình nộp bài (`submissionsubmitstep4form::execute` hoặc hook email `SUBMISSION_ACK`).
* **Dữ liệu đầu vào:** ID bài nộp, Tiêu đề bài báo, Danh sách tác giả, Tóm tắt (Abstract), Số điện thoại tác giả (từ Profile).
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Tác giả:**
     - *Nội dung:* Xác nhận nộp bài thành công.
     - *Thông tin hiển thị:* Mã bài báo (`{submissionId}`), Tiêu đề bài (`{title}`), Ngày giờ nộp (`{timestamp}`).
  2. **Tin nhắn Zalo gửi Ban Biên tập / Nhóm cấu hình:**
     - *Nội dung:* Thông báo có bản thảo mới gửi đến tòa soạn để sơ duyệt.
     - *Thông tin hiển thị:* Mã bài báo, Tiêu đề, Tác giả liên hệ (`{author}`), Tóm tắt trích đoạn (`{abstract_if_any}`), Đường link truy cập nhanh hồ sơ biên tập (`{workflowUrl}`).
  3. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_SUBMISSION` (Cấp độ: INFO, Tác giả: Tên người nộp, ID bài, Trạng thái Zalo Gateway).
  4. **Bản ghi Outbox:** Tạo 1 bản ghi `event_type = 'SUBMISSION'` chuyển vào trạng thái `pending` hoặc gửi ngay.

---

### 2. Phân công Biên tập viên phụ trách (Editor Assignment)
* **Mục đích:** Báo cho Biên tập viên biết họ vừa được Tổng biên tập giao phụ trách theo dõi một bài báo.
* **Thời điểm kích hoạt:** Tổng biên tập hoặc BTV quản lý thêm người vào phân công giai đoạn (`addparticipantform::execute`).
* **Dữ liệu đầu vào:** ID bài báo, Tên bài, BTV được phân công, Số điện thoại của BTV trong hồ sơ OJS.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Biên tập viên được phân công:**
     - *Nội dung:* Thông báo giao bài mới để quản lý quy trình.
     - *Thông tin hiển thị:* Mã bài báo, Tiêu đề bài báo, Tác giả liên hệ, Đường dẫn trực tiếp vào trang xử lý bài (`{workflowUrl}`).
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_EDITOR_ASSIGNED` (Ghi rõ tên BTV nhận nhiệm vụ, mã bài).

---

### 3. Từ chối sơ duyệt (Initial Decline - Desk Reject)
* **Mục đích:** Thông báo nhanh cho tác giả khi bản thảo không phù hợp với tôn chỉ mục đích hoặc không đạt chuẩn định dạng ở khâu thẩm định ban đầu.
* **Thời điểm kích hoạt:** Biên tập viên ra quyết định Từ chối ở giai đoạn Nộp bài (`SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE`).
* **Dữ liệu đầu vào:** ID bài báo, Tên bài báo, Tác giả liên hệ, SĐT tác giả.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Tác giả:**
     - *Nội dung:* Thông báo bài báo không được tiếp nhận vào vòng phản biện.
     - *Thông tin hiển thị:* Mã bài báo, Tiêu đề bài báo, Trạng thái: Từ chối sơ duyệt, Lời cảm ơn và khuyến nghị tác giả kiểm tra email để xem góp ý chi tiết của BTV.
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_INITIAL_DECLINE` (Khóa chống gửi trùng trong 5 phút).

---

### 4. Chuyển sang giai đoạn Phản biện (Move to Review)
* **Mục đích:** Thông báo bài báo đã vượt qua vòng sơ duyệt và chính thức bước vào quy trình bình duyệt chuyên gia.
* **Thời điểm kích hoạt:** BTV bấm quyết định chuyển bài sang phản biện (`SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW`).
* **Dữ liệu đầu vào:** ID bài báo, Tên bài báo, Vòng phản biện (`round`), Tác giả liên hệ.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Tác giả:**
     - *Nội dung:* Thông báo bài báo đang được tìm kiếm chuyên gia phản biện.
     - *Thông tin hiển thị:* Mã bài, Tiêu đề, Vòng phản biện (`Vòng 1`, `Vòng 2`), Thời gian cập nhật trạng thái.
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_REVIEW_STARTED`.

---

### 5. Gửi lời mời Phản biện bài báo (Review Request / Invitation)
* **Mục đích:** Mời nhà khoa học tham gia phản biện bài báo với thông tin hạn chót rõ ràng.
* **Thời điểm kích hoạt:** BTV phân công phản biện viên và xác nhận ngày hết hạn (`EditorAction::setDueDates`).
* **Dữ liệu đầu vào:** Phản biện viên (Họ tên, SĐT trong OJS), Tiêu đề bài báo, Hạn chót nộp bài (`date_due`).
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Phản biện viên:**
     - *Nội dung:* Lời mời trang trọng tham gia thẩm định bài báo.
     - *Thông tin hiển thị:* Tiêu đề bài báo (`{title}`), Mã bài báo, Tác giả (`{author}`), Hạn chót hoàn thành (`{deadline}`), Đường link đăng nhập OJS để xem bản thảo và xác nhận lời mời (`{workflowUrl}`).
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_REVIEW_REQUEST` (Kèm thời hạn nộp bài).

---

### 6. Phản biện viên Phản hồi Lời mời (Review Response: Accept / Decline)
* **Mục đích:** Cập nhật ngay lập tức cho Biên tập viên biết phản biện viên có đồng ý tham gia hay từ chối.
* **Thời điểm kích hoạt:** Phản biện viên bấm nút Chấp nhận hoặc Từ chối trên giao diện OJS (`reviewassignmentform::execute`).
* **Dữ liệu đầu vào:** Review Assignment, Quyết định phản hồi, Tên phản biện viên, Tên bài báo.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Biên tập viên phụ trách:**
     - *Nội dung:* Thông báo kết quả phản hồi của chuyên gia.
     - *Thông tin hiển thị:* Họ tên phản biện viên (`{reviewerName}`), Trạng thái (`Đã đồng ý tiếp nhận` hoặc `Đã từ chối phản biện`), Tiêu đề bài báo, Hạn chót nộp bài đã cam kết.
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_REVIEW_RESPONSE`.

---

### 7. Phản biện viên Hoàn tất và Nộp bản nhận xét (Review Completed)
* **Mục đích:** Báo cho Biên tập viên biết đã có kết quả phản biện để vào đọc và tổng hợp.
* **Thời điểm kích hoạt:** Phản biện viên bấm nộp bản đánh giá ở Bước 3 (`reviewerreviewstep3form::execute`).
* **Dữ liệu đầu vào:** Review Assignment, Kết luận đề xuất của PBV (Accept, Minor, Major, Decline), Tên PBV.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Biên tập viên phụ trách:**
     - *Nội dung:* Thông báo chuyên gia đã nộp xong bản nhận xét.
     - *Thông tin hiển thị:* Tên chuyên gia phản biện (`{reviewerName}`), Tiêu đề bài báo, Đề xuất học thuật sơ bộ (`{recommendation}`: Chấp nhận / Yêu cầu sửa / Từ chối), Đường link mở trực tiếp bản nhận xét trên OJS (`{workflowUrl}`).
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_REVIEW_COMPLETED` (Kèm mức đề xuất học thuật).

---

### 8. Tác giả Tải lên Bản thảo Chỉnh sửa (Author Revision Uploaded)
* **Mục đích:** Thông báo cho BTV khi tác giả đã sửa bài theo góp ý của phản biện và upload bản thảo mới.
* **Thời điểm kích hoạt:** Tác giả thêm tệp mới vào mục *Revisions* ở giai đoạn Review (`SubmissionFile::onAdd`).
* **Dữ liệu đầu vào:** Tệp bản thảo mới, Submission, Thông tin tác giả.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Biên tập viên phụ trách:**
     - *Nội dung:* Tác giả đã nộp bản thảo chỉnh sửa.
     - *Thông tin hiển thị:* Tên tác giả nộp bài, Mã bài báo, Tiêu đề bài báo, Đường dẫn kiểm tra tệp mới trên OJS.
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_AUTHOR_REVISION`.

---

### 9. Quyết định Biên tập (Editorial Decision: Accept / Revisions / Decline)
* **Mục đích:** Thông báo kết luận chính thức của Tạp chí cho tác giả sau quá trình phản biện.
* **Thời điểm kích hoạt:** BTV bấm gửi thông báo quyết định biên tập (`EditorAction::recordDecision`).
* **Dữ liệu đầu vào:** Quyết định (Chấp nhận, Sửa chữa nhỏ, Sửa chữa lớn, Từ chối), Nội dung thư, Tác giả.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Tác giả:**
     - *Nội dung:* Kết quả thẩm định bài báo.
     - *Thông tin hiển thị:* Mã bài báo, Tiêu đề bài báo, Yêu cầu biên tập (`{decisionDesc}`: Bài báo đã được chấp nhận đăng / Yêu cầu chỉnh sửa / Bài báo bị từ chối), Lời nhắc tác giả kiểm tra email để xem hướng dẫn sửa bài.
  2. **Rào chắn bảo mật phản biện (Blind Review Protection):**
     - Tuyệt đối **không gửi** kết quả này cho các phản biện viên để bảo đảm quy tắc bình duyệt ẩn danh và tính độc lập của các vòng đánh giá.
  3. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_DECISION` (Kèm chi tiết loại quyết định biên tập).

---

### 10. Xuất bản Bài báo Chính thức (Publication Published)
* **Mục đích:** Báo tin vui cho tác giả và truyền thông bài báo mới đến mạng lưới độc giả/nhóm quan tâm.
* **Thời điểm kích hoạt:** Bài báo được đưa vào số và bấm Xuất bản (`Publication::publish`).
* **Dữ liệu đầu vào:** Submission, Publication, Tập/Số (Volume/Issue/Year), Tác giả, URL công khai của bài.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Tác giả:**
     - *Nội dung:* Chúc mừng bài báo đã chính thức phát hành.
     - *Thông tin hiển thị:* Tiêu đề bài báo, Tác giả, Tập/Số/Năm xuất bản (`{issue}`), Đường link bài viết công khai trên trang web Tạp chí (`{url}`).
  2. **Tin nhắn Zalo gửi Nhóm nhận tin (nếu đăng ký sự kiện PUBLISH):**
     - *Nội dung:* Thông báo có bài báo mới vừa xuất bản trên tạp chí.
  3. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_PUBLISH` (Lưu lại đường dẫn URL bài báo).

---

### 11. Thu hồi / Gỡ xuất bản Bài báo (Publication Unpublished)
* **Mục đích:** Thông báo nội bộ cho Ban biên tập khi một bài báo đã phát hành bị gỡ hoặc rút bài.
* **Thời điểm kích hoạt:** BTV bấm Gỡ xuất bản (`Publication::unpublish`).
* **Dữ liệu đầu vào:** Submission, Publication.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Ban biên tập / Quản trị viên:**
     - *Nội dung:* Thông báo bài báo đã được gỡ khỏi số xuất bản.
     - *Thông tin hiển thị:* Mã bài, Tiêu đề bài báo, Thời gian thực hiện gỡ bài.
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận sự kiện `TYPE_UNPUBLISH`.

---

## NHÓM 2: HỆ THỐNG NHẮC HẠN PHẢN BIỆN

### 12. Tự động Quét và Nhắc Trước Hạn Chót (Upcoming Reminder)
* **Mục đích:** Nhắc nhở sớm để chuyên gia phản biện kịp thời hoàn thành bài viết đúng hẹn, không để trễ bài.
* **Thời điểm kích hoạt:** Tiến trình ngầm (`ZaloOverdueReviewTask`) quét định kỳ qua Cron hoặc Acron.
* **Điều kiện kích hoạt:** Ngày hiện tại cách ngày hết hạn đúng số ngày cấu hình (`reviewReminderBeforeDays`, mặc định: **4 ngày**).
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Phản biện viên:**
     - *Tiêu đề:* `▣ BÀI BÁO SẮP ĐẾN HẠN PHẢN BIỆN`
     - *Nội dung:* Nhắc nhở chuyên gia bài báo còn **4 ngày** nữa là đến hạn nộp kết quả.
     - *Thông tin hiển thị:* Tiêu đề bài báo, Hạn nộp chính xác (`{deadline}`), Số ngày còn lại: `"Còn 4 ngày"`, Đường dẫn đăng nhập OJS.
  2. **Tin nhắn Zalo gửi Biên tập viên phụ trách (nếu bật tùy chọn):**
     - Báo cho BTV biết bài báo sắp đến hạn để tiện theo dõi.
  3. **Bản ghi Nhật ký Activity Log:** Ghi nhận hành động `Nhắc nhở phản biện (Sắp đến hạn (còn 4 ngày))`.
  4. **Khóa chống trùng lặp (Deduplication):** Sinh mã khóa `REMINDER_UPCOMING_{contextId}_{reviewId}_{dateDue}` với thời hạn lưu 5 ngày, bảo đảm trong suốt kỳ hạn chỉ gửi duy nhất 1 lần.

---

### 13. Tự động Nhắc Vào Đúng Ngày Hết Hạn (Due Date Reminder)
* **Mục đích:** Cảnh báo phản biện viên hôm nay là ngày cuối cùng để nộp nhận xét.
* **Thời điểm kích hoạt:** Quét định kỳ khi `DATE(date_due) == CURRENT_DATE` (nếu bật checkbox *Nhắc vào đúng ngày hết hạn*).
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Phản biện viên & Biên tập viên:**
     - *Tiêu đề:* `▣ HÔM NAY LÀ HẠN CHÓT NỘP PHẢN BIỆN`
     - *Thông tin hiển thị:* Tiêu đề bài báo, Trạng thái: `"Hôm nay là hạn chót"`, Lời đề nghị nộp nhận xét trong ngày.
  2. **Bản ghi Nhật ký Activity Log:** Ghi nhận hành động `Nhắc nhở phản biện (Đến hạn hôm nay)`.
  3. **Khóa chống trùng:** Lưu khóa `REMINDER_TODAY_...` trong 48 giờ.

---

### 14. Tự động Quét Nhắc Quá Hạn theo Chu kỳ & Giới hạn Dừng (Overdue Reminder)
* **Mục đích:** Tự động đôn đốc bài bị trễ hạn với tần suất hợp lý, tránh gửi dồn dập gây phiền phức và tự dừng khi quá giới hạn.
* **Thời điểm kích hoạt:** Quét định kỳ khi `DATE(date_due) < CURRENT_DATE`.
* **Điều kiện kích hoạt:**
  - Khoảng cách từ lần nhắc gần nhất $\ge$ `reviewReminderOverdueFrequency` ngày (mặc định: **mỗi 2 ngày/lần**).
  - Tổng số lần đã nhắc quá hạn $<$ `reviewReminderMaxOverdueCount` (mặc định: **tối đa 3 lần**).
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Tin nhắn Zalo gửi Phản biện viên:**
     - *Tiêu đề:* `▣ NHẮC LẠI: BÀI BÁO ĐÃ QUÁ HẠN PHẢN BIỆN`
     - *Thông tin hiển thị:* Tiêu đề bài báo, Số ngày trễ hạn thực tế, Số thứ tự lần nhắc: `"Quá hạn X ngày (Nhắc lần Y/Z)"`, Đề nghị nộp gấp hoặc liên hệ tòa soạn nếu cần gia hạn.
  2. **Tin nhắn Zalo gửi Biên tập viên phụ trách:**
     - Báo BTV biết chuyên gia nào đang trễ bài để chủ động liên hệ hoặc chuẩn bị phương án mời người khác.
  3. **Cập nhật CSDL OJS:**
     - Cập nhật trường `date_reminded = NOW()` và `reminder_was_automatic = 1` trong bảng `review_assignments`.
  4. **Tệp lưu trữ trạng thái:**
     - Lưu trạng thái vào `data/review_reminder_state_{contextId}.json` gồm `overdue_count`, `last_overdue_time`. Tự động nhận diện nếu BTV thay đổi hạn nộp (`date_due`) để reset số lần đếm về 0.
  5. **Bản ghi Nhật ký Activity Log:** Ghi nhận hành động `Nhắc nhở phản biện (Quá hạn lần Y)`.

---

### 15. Dashboard Nhắc Phản biện & Nhắc Thủ công 1-Click (Manual Trigger)
* **Mục đích:** Cung cấp không gian làm việc trực quan cho Ban biên tập theo dõi toàn bộ các lượt phản biện đang mở và chủ động gửi tin Zalo nhắc nhở tức thì.
* **Thời điểm kích hoạt:** Biên tập viên mở tab **"Nhắc hạn phản biện"** trên menu website OJS.
* **THÔNG TIN ĐẦU RA TRÊN GIAO DIỆN:**
  1. **Thanh thống kê tổng thể:**
     - Tổng số bài báo đang phản biện (`submissionCount`).
     - Tổng số chuyên gia đang giữ bài (`reviewerCount`).
     - Số chuyên gia đang bị quá hạn (`overdueCount`).
  2. **Thanh tóm tắt lịch nhắc tự động:**
     - Hiển thị trực quan cấu hình hiện tại:
       > ⏱️ *Lịch nhắc tự động: Nhắc trước 4 ngày ➔ Nhắc đúng ngày hết hạn ➔ Quá hạn nhắc mỗi 2 ngày (tối đa 3 lần).*
  3. **Bộ lọc & Tìm kiếm nhanh:**
     - Ô tìm kiếm theo Mã bài, Tiêu đề, Tác giả, Tên phản biện viên.
     - Lọc trạng thái: *Tất cả*, *Đang quá hạn (đỏ)*, *Hết hạn hôm nay (vàng)*, *Hết hạn trong 7 ngày tới (xanh)*, *Có thể gửi nhắc*, *Thiếu SĐT*.
  4. **Thẻ bài báo chi tiết (Card View):**
     - Thông tin bài báo: ID, Tiêu đề, Tác giả, Chuyên mục, Ngày nộp.
     - Danh sách từng phản biện viên: Họ tên, Email, SĐT, Trạng thái lời mời, Hạn nộp, Ngày giờ đã nhắc lần cuối, Nhãn tiến độ (*Còn X ngày / Quá hạn Y ngày*).
* **THÔNG TIN ĐẦU RA KHI BẤM NÚT "NHẮC NHỞ":**
  1. **Nút "Nhắc nhở" (theo từng chuyên gia):** Gửi ngay 1 tin Zalo đến số điện thoại của chuyên gia đó; hiển thị Toast thông báo thành công hoặc lỗi chi tiết; cập nhật ngay dòng *"Đã nhắc lúc: DD/MM/YYYY HH:mm"*.
  2. **Nút "Nhắc tất cả":** Gửi tin Zalo đồng loạt cho tất cả các phản biện viên đủ điều kiện trong bài báo đó chỉ bằng 1 click.

---

## NHÓM 3: QUẢN LÝ HÀNG ĐỢI & XỬ LÝ SỰ CỐ (OUTBOX)

### 16. Chế độ Hàng đợi Bất đồng bộ (Queue-First Dispatch)
* **Mục đích:** Loại bỏ triệt để độ trễ (lag) mạng khi thực hiện các thao tác chuyển giai đoạn hoặc nộp bài trong OJS.
* **Cơ chế:**
  - Thay vì kết nối trực tiếp đến Zalo Gateway (mất từ 0.5s – 2s), hệ thống chỉ mất **2 mili-giây** để lưu tin nhắn vào bảng CSDL `zalo_notification_outbox`.
  - Trình duyệt đóng kết nối ngay lập tức, người dùng thấy trang web phản hồi tức thì.
* **THÔNG TIN ĐẦU RA:**
  - Tạo bản ghi mới trong bảng `zalo_notification_outbox` với trạng thái `status = 'pending'`, `available_at = NOW()`, mã hóa danh sách người nhận `recipients_json`, và lưu nguyên vẹn nội dung `message_text`.
  - Trả về mã thông báo: `Đã xếp hàng outbox #X`.

---

### 17. Tự động Thử lại Thông minh khi Lỗi Mạng (Smart Exponential Backoff Retry)
* **Mục đích:** Bảo đảm không bao giờ bị mất tin nhắn khi cổng Zalo Gateway quá tải, bảo trì hoặc mạng internet bị rớt tạm thời.
* **Cơ chế:** Phân loại mã phản hồi từ Gateway:
  - Nếu lỗi vĩnh viễn (HTTP 401 sai API Key, HTTP 400 sai số điện thoại): Đánh dấu `failed` vĩnh viễn để không gửi lại vô ích.
  - Nếu lỗi tạm thời (HTTP 5xx server bận, cURL timeout, mất kết nối): Tự động tính toán thời gian thử lại lùi dần:
    - *Lần 1:* Sau 1 phút
    - *Lần 2:* Sau 5 phút
    - *Lần 3:* Sau 15 phút
    - *Lần 4:* Sau 60 phút
    - *Lần 5:* Sau 4 giờ (Tối đa 5 lần thử).
* **THÔNG TIN ĐẦU RA:**
  - Cập nhật trường `attempts = attempts + 1`, `available_at = [thời điểm mới]`, `status = 'pending'`, ghi nhận lý do lỗi vào `last_error`.

---

### 18. Giao diện Giám sát Hàng đợi Outbox Dashboard
* **Mục đích:** Cung cấp bức tranh toàn cảnh về tình trạng phát tin nhắn Zalo của tạp chí và công cụ can thiệp thủ công khi cần.
* **Thời điểm kích hoạt:** Quản trị viên mở tab **"Hàng đợi Outbox"** trên menu website OJS.
* **THÔNG TIN ĐẦU RA TRÊN GIAO DIỆN:**
  1. **4 Khối thống kê thời gian thực (KPI Tiles):**
     - 🟡 **Chờ gửi (Pending):** Số tin đang xếp hàng chờ worker phát đi.
     - 🟢 **Đã gửi (Sent):** Số tin nhắn đã gửi thành công tới Zalo.
     - 🔴 **Thất bại (Failed):** Số tin bị từ chối hoặc hết số lần thử.
     - 🟠 **Đang thử lại (Retrying):** Số tin gặp sự cố tạm thời đang chờ đến giờ gửi lại.
  2. **Banner Cảnh báo Tiến trình ngầm (Stale Cron Warning):**
     - Tự động xuất hiện hộp cảnh báo màu vàng nếu phát hiện có tin nhắn nằm chờ quá 15 phút chưa được giải phóng:
       > ⚠️ *Cảnh báo tiến trình ngầm (Cron / Acron): Phát hiện X tin nhắn đang chờ gửi quá 15 phút chưa được giải phóng. Hãy kiểm tra đảm bảo Cron Job hoặc plugin Acron của OJS đang hoạt động.*
  3. **Bảng chi tiết từng tin nhắn:**
     - Hiển thị ID outbox, Loại sự kiện, Danh sách số điện thoại nhận, Nội dung tin nhắn (bảo đảm an toàn kiểm toán), Số lần đã thử, Trạng thái và Thời gian gửi.
* **THÔNG TIN ĐẦU RA KHI THỰC HIỆN CÁC THAO TÁC QUẢN TRỊ:**
  1. **Nút "Gửi lại" (Retry Item):** Đưa 1 tin nhắn lỗi về trạng thái chờ gửi ngay lập tức.
  2. **Nút "Gửi lại tất cả" (Retry All):** Cưỡng bức đưa toàn bộ các tin nhắn lỗi về trạng thái chờ gửi để kích hoạt phát lại đồng loạt.
  3. **Nút "Xóa tin" (Delete):** Xóa vĩnh viễn 1 bản ghi khỏi hàng đợi outbox.
  4. **Nút "Dọn dẹp tin đã gửi" (Clear Sent):** Xóa toàn bộ các bản ghi đã gửi thành công để làm sạch bảng CSDL.

---

## NHÓM 4: NHẬT KÝ HOẠT ĐỘNG & TỰ ĐỘNG BẢO TRÌ

### 19. Nhật ký Hoạt động Chuyên sâu (Activity Log Viewer)
* **Mục đích:** Lưu vết toàn bộ các hoạt động gửi tin, kết quả phản hồi từ Zalo Gateway và lịch sử thao tác để đối soát và kiểm toán.
* **Thời điểm kích hoạt:** Tự động ghi sau mỗi sự kiện trong hệ thống; xem tại tab **"Nhật ký hoạt động Zalo"**.
* **THÔNG TIN ĐẦU RA:**
  1. **Giao diện bảng nhật ký:**
     - Bộ lọc loại sự kiện: Bài nộp mới, Phân công BTV, Mời phản biện, Nhắc hạn, Quyết định, Xuất bản, Lỗi hệ thống.
     - Bộ lọc theo ngày tháng cụ thể.
     - Bảng hiển thị: Thời gian, Cấp độ (`INFO`, `WARNING`, `ERROR`), Tác nhân thực hiện, Tên bài báo / Mã bài, Mô tả hành động và Chi tiết phản hồi Gateway.
  2. **Khối tóm tắt thống kê trong ngày (Daily Summary):**
     - Đếm tổng số tin gửi thành công, thất bại, phân loại theo từng loại sự kiện trong ngày đã chọn.
  3. **Bảo mật PII (Privacy by Design):**
     - Dữ liệu nhật ký tự động loại bỏ số điện thoại cá nhân và nội dung nhạy cảm của bài báo, chỉ lưu trữ các tham số kỹ thuật phục vụ đối soát.

---

### 20. Xuất Dữ liệu Nhật ký (Export Activity Log)
* **Mục đích:** Tải toàn bộ dữ liệu nhật ký về máy tính cá nhân để lưu trữ phục vụ báo cáo định kỳ của tòa soạn.
* **Thời điểm kích hoạt:** Quản trị viên bấm nút **"Xuất nhật ký"** trên giao diện Activity Log.
* **THÔNG TIN ĐẦU RA:**
  - Tải về 1 tệp tin văn bản dạng `.txt` hoặc `.json` chứa toàn bộ các dòng nhật ký đã được lọc theo điều kiện hiện tại.
  - Tên tệp có tiền tố rõ ràng: `[FilterType]_activity_log_context_[id].txt`.

---

### 21. Cơ chế Tự động Dọn dẹp & Tự phục hồi (Auto-Maintenance)
* **Mục đích:** Bảo đảm máy chủ không bao giờ bị đầy ổ cứng hay phình to cơ sở dữ liệu sau nhiều năm vận hành.
* **Cơ chế vận hành:**
  1. **Tự động thanh lọc Outbox:** Các bản ghi tin nhắn đã gửi thành công hoặc đã hoàn tất sẽ tự động bị xóa sau số ngày cấu hình (`outboxRetentionDays`, mặc định: **30 ngày**).
  2. **Tự động thanh lọc Nhật ký:** Các dòng log cũ hơn số ngày quy định (`logRetentionDays`, mặc định: **90 ngày**) sẽ tự động được làm sạch.
  3. **Tự xoay vòng tệp nhật ký (Log Rotation):** Khi tệp nhật ký vượt quá dung lượng **2MB**, hệ thống tự động lưu trữ sang tệp sao lưu `.1`, `.2` và tạo tệp mới, giữ tối đa 5 tệp gần nhất.

---

## NHÓM 5: CẤU HÌNH HỆ THỐNG & MẪU THÔNG BÁO

### 22. Tab Cấu hình API Zalo (API Settings Form)
* **Mục đích:** Thiết lập kết nối đến Zalo Gateway và điều chỉnh các tham số vận hành cốt lõi của plugin.
* **THÔNG TIN ĐẦU RA KHI LƯU CẤU HÌNH:**
  - **Bot ID & API Key:** Lưu an toàn vào bảng `plugin_settings`. Khóa API Key sau khi lưu sẽ được che giấu trên giao diện để chống nhìn trộm (có tùy chọn tích vào *Xóa / Thu hồi API Key* khi cần hủy khóa).
  - **Queue-First Mode:** Bật hoặc tắt chế độ gửi tin bất đồng bộ.
  - **Thời gian lưu trữ dữ liệu:** Lưu số ngày duy trì Log (1–730 ngày) và Outbox (1–365 ngày).
  - **Lịch nhắc hạn phản biện:**
    - Lưu số ngày nhắc trước hạn (`reviewReminderBeforeDays`, mặc định: **4 ngày**, nhập 0 để tắt).
    - Lưu trạng thái nhắc đúng ngày hết hạn (`reviewReminderOnDueDate`).
    - Lưu tần suất nhắc khi quá hạn (`reviewReminderOverdueFrequency`, mặc định: **2 ngày/lần**).
    - Lưu số lần nhắc quá hạn tối đa (`reviewReminderMaxOverdueCount`, mặc định: **3 lần**).
    - Lưu đối tượng nhận tin: Phản biện viên / Biên tập viên.

---

### 23. Tab Cài đặt Nhóm Nhận Thông báo (Recipient Groups)
* **Mục đích:** Tạo danh sách các nhóm nhận tin riêng biệt (ví dụ: *Ban Thư ký*, *Ban Trị sự*, *Tổng biên tập*) và gán quyền nhận các sự kiện cụ thể.
* **THÔNG TIN ĐẦU RA KHI LƯU CẤU HÌNH:**
  - Lưu cấu trúc JSON danh sách nhóm vào CSDL.
  - Tự động chuẩn hóa toàn bộ các số điện thoại trong danh sách nhóm về chuẩn quốc tế (`849xxxxxxxxx`).
  - Khi có sự kiện xảy ra, plugin tự động so khớp loại sự kiện với danh sách nhóm đăng ký để chỉ gửi đúng người, tránh spam tin nhắn.

---

### 24. Tab Quản lý Mẫu Tin nhắn (Template Engine)
* **Mục đích:** Cho phép Tòa soạn biên soạn, chỉnh sửa câu chữ hoặc bật/tắt từng mẫu tin Zalo cho phù hợp với văn phong của Tạp chí.
* **Hỗ trợ 9 mẫu sự kiện chuẩn mực cho 3 vai trò:**
  - **Tác giả (Author):** Nộp bài mới (`submission`), Quyết định biên tập (`decision`), Xuất bản bài báo (`publish`).
  - **Phản biện viên (Reviewer):** Mời phản biện (`review_request`), Nhắc hạn phản biện (`reminder`).
  - **Biên tập viên (Editor):** Bài nộp mới (`submission`), Phản hồi lời mời (`review_response`), Phản biện nộp bài (`review_completed`), Tác giả sửa bài (`author_revision`), Nhắc hạn phản biện (`reminder`), Phân công BTV (`editor_assignment`).
* **THÔNG TIN ĐẦU RA:**
  - Lưu toàn bộ văn bản mẫu tin vào CSDL của tạp chí.
  - Khi người dùng gửi tin, hệ thống tự động quét và thay thế chính xác các biến số động trong dấu ngoặc nhọn `{...}` thành dữ liệu thực tế của bài báo.

---

## NHÓM 6: HỆ THỐNG BIỂU MẪU BÁO CÁO PHẢN BIỆN HÀNG THÁNG

### 25. Báo cáo Chi tiết Tiến độ Phản biện trong Tháng (Mẫu 1 - Detail Progress Report)
* **Mục đích:** Thống kê chi tiết từng bài báo, từng chuyên gia phản biện đã tham gia trong tháng, phục vụ theo dõi sát sao tiến độ và đối soát học thuật của Tòa soạn.
* **Thời điểm sử dụng:** Định kỳ cuối mỗi tháng hoặc bất cứ khi nào Ban Thư ký cần rà soát tiến độ phản biện.
* **Định dạng tệp hỗ trợ:**
  - `reports/Mau_1_Chi_tiet_tien_do_phan_bien.csv`: Mở trực tiếp trên Microsoft Excel (mã hóa chuẩn UTF-8 BOM, không bị lỗi font tiếng Việt).
  - `reports/Mau_1_Chi_tiet_tien_do_phan_bien.doc`: Văn bản Word chuẩn khổ A4 nằm ngang (Landscape), sẵn sàng in ấn hoặc lưu PDF.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG (Bảng 12 cột dữ liệu):**
  1. **STT:** Số thứ tự lượt phản biện trong tháng.
  2. **Mã bài báo (ID):** Mã định danh số của bài báo trên OJS (Ví dụ: `#1042`).
  3. **Tiêu đề bài báo:** Tên bài báo đầy đủ.
  4. **Họ và tên Phản biện viên:** Danh tính chuyên gia được phân công.
  5. **Đơn vị công tác / Chức danh:** Học hàm, học vị, cơ quan của chuyên gia (Ví dụ: *PGS. TS - Đại học Bách Khoa*).
  6. **Ngày gửi lời mời:** Thời điểm BTV gửi yêu cầu phản biện (`date_notified`).
  7. **Ngày nhận lời:** Thời điểm chuyên gia bấm chấp thuận (`date_confirmed`).
  8. **Hạn chót nộp bài:** Hạn nộp cam kết hoặc được chỉ định (`date_due`).
  9. **Ngày hoàn tất nộp bài:** Thời điểm chuyên gia gửi bản nhận xét lên OJS (`date_completed`).
  10. **Số ngày thực hiện:** Tổng số ngày từ khi nhận lời đến khi nộp bài thực tế.
  11. **Tiến độ & Trạng thái:** Nhãn đánh giá mức độ đúng hạn:
      - `Đúng hạn` (hoặc `Sớm X ngày`)
      - `Quá hạn Y ngày`
      - `Đang phản biện` (kèm số ngày còn lại hoặc số ngày trễ)
  12. **Kết luận đề xuất:** Ý kiến học thuật của chuyên gia:
      - `Chấp nhận đăng` (Accept)
      - `Chỉnh sửa nhỏ` (Minor Revisions)
      - `Chỉnh sửa lớn` (Major Revisions)
      - `Từ chối đăng` (Decline)
  13. **Chữ ký xác nhận:** Khung ký tên của Người lập biểu, Trưởng Ban Thư ký và Tổng Biên tập duyệt.

---

### 26. Báo cáo Thống kê Hiệu suất & Chỉ số KPI Phản biện (Mẫu 2 - Executive KPI Report)
* **Mục đích:** Cung cấp bản báo cáo quản trị cấp cao trình Tổng biên tập và Hội đồng Biên tập nhằm đánh giá tổng thể hiệu suất, chất lượng và thời gian hoàn thành bình duyệt trong tháng.
* **Thời điểm sử dụng:** Phiên họp định kỳ hàng tháng/quý của Ban Biên tập Tạp chí.
* **Định dạng tệp hỗ trợ:**
  - `reports/Mau_2_Bao_cao_hieu_suat_KPI_phan_bien.doc`: Văn bản Word chuẩn khổ A4 đứng (Portrait), thiết kế chuyên nghiệp với quốc hiệu, tiêu ngữ và bố cục hành chính học thuật.
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG (4 Khối số liệu & Phân tích chuyên sâu):**
  1. **Khối Chỉ số Hiệu suất Cốt lõi (Core Review KPIs):**
     - Tổng số lời mời phản biện đã phát đi trong tháng.
     - Số lượt đồng ý tiếp nhận & **Tỷ lệ chấp thuận (%)** (Chỉ số đo lường mức độ sẵn sàng cộng tác của mạng lưới chuyên gia).
     - Số lượt từ chối phản biện & **Tỷ lệ từ chối (%)**.
     - Tổng số bản nhận xét phản biện đã hoàn thành nộp trong tháng.
     - Số lượt nộp đúng/trước hạn & **Tỷ lệ đúng hạn (%)**.
     - Số lượt trễ hạn & **Tỷ lệ quá hạn (%)**.
     - **Thời gian phản biện trung bình (Turnaround Time):** Tính bằng số ngày bình quân/bài.
     - **Tổng số lượt thông báo/nhắc nhở Zalo đã gửi:** Đo lường mức độ can thiệp tự động của hệ thống.
  2. **Khối Phân bổ Kết quả Đề xuất Học thuật:**
     - Tỷ lệ % và số lượng bài được đề xuất *Chấp nhận đăng ngay*.
     - Tỷ lệ % và số lượng bài yêu cầu *Chỉnh sửa nhỏ*.
     - Tỷ lệ % và số lượng bài yêu cầu *Chỉnh sửa lớn & phản biện lại*.
     - Tỷ lệ % và số lượng bài bị đề xuất *Từ chối đăng*.
  3. **Khối Nhận xét & Đánh giá của Ban Thư ký:**
     - Phân tích những tiến bộ và điểm nghẽn trong khâu phản biện tháng qua.
     - Đánh giá hiệu quả giảm thiểu trễ hạn nhờ các thông báo nhắc hạn Zalo tự động (trước 4 ngày, đúng hạn và sau hạn).
  4. **Khối Đề xuất & Giải pháp Cải tiến:**
     - Kiến nghị mở rộng cơ sở dữ liệu chuyên gia cho các chuyên ngành hẹp còn thiếu người.
     - Kế hoạch điều chỉnh thời hạn hoặc phân công lại các bài đang ách tắc.
     - Khung chữ ký chính thức của Ban Thư ký Tòa soạn và Tổng Biên tập phê duyệt.

---

### 27. Báo cáo Đánh giá Chất lượng & Xếp loại Phản biện viên (Mẫu 3 - Quality Ranking Report)
* **Mục đích:** Chấm điểm, đánh giá và phân hạng đội ngũ nhà khoa học tham gia phản biện trong tháng, phục vụ công tác xây dựng mạng lưới phản biện nòng cốt (Core Reviewer Pool) và quy hoạch khen thưởng, vinh danh hàng năm.
* **Thời điểm sử dụng:** Đánh giá cuối kỳ mỗi tháng hoặc tổng kết năm của Tòa soạn.
* **Định dạng tệp hỗ trợ:**
  - `reports/Mau_3_Danh_gia_xep_loai_phan_bien_vien.csv`: Mở trực tiếp bằng Microsoft Excel (UTF-8 BOM).
  - `reports/Mau_3_Danh_gia_xep_loai_phan_bien_vien.doc`: Văn bản Word chuẩn khổ A4 ngang (Landscape).
* **THÔNG TIN ĐẦU RA KHI SỬ DỤNG:**
  1. **Bảng Đánh giá & Xếp loại Chi tiết (10 cột thông tin):**
     - **STT:** Số thứ tự chuyên gia.
     - **Họ và tên Phản biện viên:** Danh tính nhà khoa học.
     - **Học hàm, Học vị:** GS, PGS, TS, ThS,...
     - **Đơn vị công tác:** Trường Đại học, Viện Nghiên cứu chủ quản.
     - **Chuyên ngành chuyên sâu:** Lĩnh vực khoa học phụ trách thẩm định.
     - **Số bài hoàn thành:** Tổng số bản thảo đã nhận xét xong trong tháng.
     - **Tỷ lệ đúng hạn (%):** Mức độ tuân thủ thời hạn đã cam kết.
     - **Điểm chất lượng trung bình (Thang điểm 1 - 5 sao):** Được đánh giá dựa trên độ sâu sắc, tính xây dựng, cơ sở học thuật và tính nghiêm cẩn của bản nhận xét.
     - **Phân loại / Xếp hạng:**
       - ⭐ **Hạng A (Xuất sắc):** Điểm chất lượng $\ge 4.5$, đúng hạn $\ge 90\%$.
       - 🔷 **Hạng B (Tốt):** Điểm chất lượng từ $3.5 - 4.4$, đúng hạn $\ge 75\%$.
       - 🔶 **Hạng C (Đạt yêu cầu):** Điểm chất lượng từ $2.5 - 3.4$, còn tình trạng trễ hạn.
       - 🔻 **Hạng D (Chưa đạt / Cần cân nhắc):** Nhận xét sơ sài, thiếu cơ sở hoặc trễ hạn nghiêm trọng.
     - **Đề xuất của Ban Biên tập:**
       - *Ưu tiên giao bài trọng điểm / Mời tham gia Ban Cố vấn Khoa học* (đối với Hạng A).
       - *Tiếp tục cộng tác thường xuyên* (đối với Hạng B).
       - *Theo dõi thêm chất lượng ở các vòng sau* (đối với Hạng C).
       - *Tạm dừng mời phản biện trong các kỳ tới* (đối với Hạng D).
  2. **Bảng Tiêu chí Đánh giá 5 Sao Chuẩn mực:** Bảng hướng dẫn chi tiết các tiêu chuẩn chấm điểm nhận xét phản biện để BTV áp dụng thống nhất.
  3. **Khung Ký duyệt Chính thức:** Xác nhận của Thư ký theo dõi và Tổng Biên tập Tạp chí.

---

## BẢNG TRA CỨU BIẾN SỐ ĐỘNG (TEMPLATE VARIABLES REFERENCE)

Dưới đây là danh mục toàn bộ các biến số động có thể sử dụng khi soạn thảo Mẫu tin nhắn Zalo:

| Biến số | Dữ liệu sinh ra khi gửi tin | Ví dụ kết quả thực tế |
| :--- | :--- | :--- |
| `{title}` | Tên bài báo (đã lọc sạch thẻ HTML) | *Nghiên cứu ứng dụng trí tuệ nhân tạo trong giáo dục* |
| `{submissionId}` | Mã định danh bài báo trong hệ thống OJS | *#1042* |
| `{author}` | Tên các tác giả bài báo (rút gọn chuẩn trích dẫn) | *Nguyễn Văn A, Trần Thị B* |
| `{reviewerName}` | Họ và tên của chuyên gia phản biện | *PGS. TS. Nguyễn Văn A* |
| `{deadline}` | Hạn chót nộp kết quả phản biện (Định dạng ngày VN) | *28/10/2026* |
| `{daysLeft}` | Số ngày còn lại hoặc số ngày trễ hẹn thực tế | *Còn 4 ngày* hoặc *Quá hạn 2 ngày (Nhắc lần 1/3)* |
| `{round}` | Vòng phản biện hiện tại | *Vòng 1* hoặc *Vòng 2* |
| `{decisionDesc}` | Tên kết luận của Biên tập viên | *Bài báo đã được chấp nhận đăng* hoặc *Yêu cầu chỉnh sửa lớn* |
| `{stageName}` | Tên giai đoạn biên tập hiện tại | *Nộp bài*, *Phản biện ngoài*, *Biên tập bản thảo* |
| `{issue}` | Tập/Số/Năm xuất bản của tạp chí | *Tập 12, Số 3 (2026)* |
| `{url}` | Đường link công khai để đọc bài báo trên website | *https://tapchi.edu.vn/article/view/1042* |
| `{workflowUrl}` | Đường link đăng nhập trực tiếp vào trang xử trị bài trên OJS | *https://tapchi.edu.vn/workflow/index/1042/3* |
| `{abstract_if_any}` | Đoạn tóm tắt nội dung bài báo (nếu có) | *📄 Tóm tắt: Nghiên cứu này phân tích dữ liệu...* |
| `{timestamp}` | Thời điểm chính xác sự kiện diễn ra | *22/10/2026 15:30:45* |
| `{recommendation}` | Đề xuất học thuật của phản biện viên | *Chấp nhận đăng* hoặc *Yêu cầu sửa chữa* |

---
*Tài liệu được cập nhật tự động và đồng bộ với phiên bản mã nguồn của plugin Zalo Notification OJS 3.3.*
