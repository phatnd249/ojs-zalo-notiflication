# Zalo Notification Plugin cho OJS 3.3

Plugin tích hợp thông báo Zalo tự động cho hệ thống **Open Journal Systems (OJS 3.3)**, phục vụ quy trình biên tập, phản biện và xuất bản bài báo khoa học.

---

## 📌 Thông tin chung
- **Tên plugin:** `Zalo Notification Plugin`
- **Phiên bản:** `1.5.1.0`
- **Nền tảng:** OJS 3.3.x (PHP 7.4, 8.0, 8.1)
- **Loại plugin:** Generic Plugin
- **Zalo Gateway:** `https://sms-service.talab.io.vn`
- **Liên hệ cấp Bot ID & API Key:** `+84812305046` (Zalo/Hotline)

---

## 🚀 Tính năng chính
- **Thông báo sự kiện biên tập tự động:** Gửi tin Zalo ngay khi có bài nộp mới, ra quyết định biên tập, mời phản biện, phản biện nộp đánh giá, xuất bản hoặc hủy xuất bản bài báo.
- **Tích hợp đồng bộ 6 tab tại `Settings → Website`:**
  1. `Cấu hình Zalo API`: Nhập Bot ID & API Key do gateway cấp.
  2. `Cài đặt Nhóm Zalo`: Quản lý danh sách nhận tin theo nhóm (Tổng biên tập, Thư ký...).
  3. `Mẫu tin Zalo`: Soạn thảo và tùy biến mẫu tin gửi cho BTV, Phản biện viên, Tác giả.
  4. `Zalo Activity Log`: Giám sát nhật ký hoạt động với thanh KPI ngang tinh gọn.
  5. `Nhắc phản biện`: Dashboard theo dõi hạn và gửi tin nhắc phản biện 1-click.
  6. `Hàng đợi & Thử lại`: Outbox Dashboard theo dõi và gửi lại các tin nhắn lỗi.
- **Gửi tin trực tiếp & Outbox Retry:** Gửi tin tức thì qua API; nếu lỗi mạng, hệ thống tự động đưa vào hàng đợi Outbox và thử lại theo cơ chế giãn cách số mũ (Exponential Backoff, tối đa 5 lần).
- **An toàn & Phân quyền:** Chỉ Quản lý tạp chí và Admin mới có quyền truy cập; CSRF token; API key được che giấu.

---

## 🔧 Yêu cầu hệ thống

| Thành phần | Yêu cầu |
|---|---|
| OJS | 3.3.0-x |
| PHP | 7.4 / 8.0 / 8.1 |
| PHP Extensions | `curl`, `json`, `mbstring`, `openssl` |
| Database | MySQL 5.7+ hoặc MariaDB 10.3+ |
| Mạng | Outbound HTTPS (port 443) đến `sms-service.talab.io.vn` |

Kiểm tra kết nối mạng:
```bash
curl -I https://sms-service.talab.io.vn
# Trả về HTTP 200/404/405 = OK | Timeout = Firewall chặn port 443
```

---

## 🛠️ Cài đặt

### Cách 1: Cài trực tiếp qua mã nguồn (Khuyên dùng)

1. **Sao chép thư mục plugin:**
   ```text
   <OJS_ROOT>/plugins/generic/zaloNotification
   ```

2. **Phân quyền (Linux):**
   ```bash
   cd <OJS_ROOT>/plugins/generic
   chown -R www-data:www-data zaloNotification
   chmod -R 755 zaloNotification
   ```

3. **Xóa cache OJS:**
   ```bash
   cd <OJS_ROOT>
   php tools/clearDataCache.php
   php tools/clearTemplateCache.php
   ```

4. **Kích hoạt plugin:**
   Vào **Settings → Website → Plugins → Installed Plugins**, tìm **Zalo Notification Plugin** và tích chọn **Enable**. Hệ thống sẽ tự động tạo bảng `zalo_notification_outbox`.

### Cách 2: Cài qua file nén `.tar.gz` (qua giao diện Web)

1. Đóng gói:
   ```bash
   tar -czvf zaloNotification.tar.gz zaloNotification/
   ```
2. Vào **Settings → Website → Plugins → Upload A New Plugin**, chọn file `.tar.gz` rồi nhấn Save.
3. Bật kích hoạt plugin trong danh sách Installed Plugins.

---

## ⚙️ Cấu hình ban đầu

Sau khi kích hoạt, truy cập **Settings → Website** để cấu hình theo thứ tự:

1. **Cấu hình Zalo API:** Nhập **Bot ID** và **API Key** (liên hệ **`+84812305046`** để được cấp), nhấn **Lưu**.
   > *Sau khi lưu, API Key sẽ được ẩn đi vì lý do bảo mật.*

2. **Cài đặt Nhóm Zalo:** Tạo nhóm nhận tin (BTV, Thư ký), nhập số điện thoại (tự động chuẩn hóa `849xxxxxxxx`), chọn sự kiện nhận tin.

3. **Mẫu tin Zalo:** Tùy chỉnh nội dung tin nhắn cho từng vai trò:
   - **Biến chung:** `{title}`, `{author}`, `{submissionId}`, `{stageName}`, `{timestamp}`
   - **Liên kết điều hướng:** `{workflowUrl}` (BTV), `{reviewerUrl}` (Phản biện), `{authorUrl}` (Tác giả), `{publicUrl}`
   - **Biến phản biện & thời hạn:** `{reviewerName}`, `{deadline}`, `{daysLeft}`, `{round}`

---

## ⏰ Cấu hình Cron Job

Plugin có 2 tác vụ chạy ngầm được khai báo trong `scheduledTasks.xml`:
- **ZaloOutboxTask:** Hàng đợi gửi tin và thử lại khi lỗi (`minute="*"` — chạy mỗi phút).
- **ZaloOverdueReviewTask:** Tự động quét và nhắc phản biện quá hạn (`minute="0"` — chạy mỗi giờ).

### Thiết lập trên Linux:
```bash
# Chạy mỗi phút để Outbox xử lý kịp thời (crontab -e dưới quyền www-data/apache)
* * * * * php <OJS_ROOT>/tools/runScheduledTasks.php <OJS_ROOT>/plugins/generic/zaloNotification/scheduledTasks.xml > /dev/null 2>&1
```

> **Lưu ý:** Nếu dùng plugin **Acron** mặc định của OJS, plugin đã tự đăng ký hook `AcronPlugin::parseCronTab` nên không cần cấu hình cron thủ công.

---

## 🔒 Bảo mật & Phân quyền

- **Phân quyền:** Chỉ **Journal Manager** và **Site Administrator** mới truy cập được các tab cấu hình.
- **API Key:** Lưu trong bảng `plugin_settings` của database OJS và được ẩn đi (masked) trên giao diện cấu hình sau khi lưu.
- **CSRF:** Mọi thao tác ghi dữ liệu đều yêu cầu CSRF token hợp lệ.
- **Tự động dọn dẹp:** Activity Log tự xóa bản ghi cũ hơn **90 ngày**.

---

## 🧪 Kiểm thử

```bash
php tests/run.php
```

### Smoke Test sau triển khai:

| Bước | Hành động | Kết quả mong đợi |
|---|---|---|
| 1 | Vào `Settings > Website` | Thấy đủ 6 tab |
| 2 | Nộp bài thử nghiệm | BTV nhận tin Zalo, Tác giả nhận tin xác nhận |
| 3 | Mở tab Activity Log | Dòng log nộp bài xuất hiện với loại `SUBMISSION` |
| 4 | Nhắc phản biện 1-click | Phản biện viên nhận tin nhắc hạn |
| 5 | Mở tab Hàng đợi | Các nút Thử lại, Xóa, Dọn dẹp hoạt động |

---

## 🔧 Xử lý sự cố

| Sự cố | Nguyên nhân | Khắc phục |
|---|---|---|
| Chưa cấu hình API Key | Chưa nhập Bot ID/API Key hoặc hết ngạch | Nhập lại tại tab Cấu hình Zalo API |
| Không nhận được tin | SĐT sai định dạng / Mẫu tin trống | Kiểm tra SĐT, mẫu tin, và tab Hàng đợi |
| Timeout / Lỗi kết nối | Firewall chặn port 443 | Liên hệ admin hạ tầng mở outbound HTTPS |
| Giao diện không cập nhật | Cache cũ | Chạy `clearDataCache.php` + `clearTemplateCache.php`, Ctrl+F5 |

**Vị trí file log:**
- Activity Log: `<files_dir>/zalo_notification_activity_context_<ID>.log`
- Debug Log: `<files_dir>/zalo_notification_debug.log`
