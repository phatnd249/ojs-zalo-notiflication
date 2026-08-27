# Zalo Notification Plugin cho OJS 3.3

Plugin theo dõi các thay đổi trong quy trình biên tập của OJS 3.3 và gửi thông báo Zalo đến Ban biên tập, phản biện viên và tác giả. Tin nhắn được gửi qua Zalo gateway theo số điện thoại lưu trong OJS hoặc trong nhóm nhận thông báo của plugin.

## Thông tin phiên bản

- Tên plugin: `Zalo Notification Plugin`
- Phiên bản: `1.5.1.0`
- Nền tảng: OJS 3.3
- Loại plugin: Generic Plugin
- API gateway: `https://sms-service.talab.io.vn`

## Chức năng chính

- Thông báo khi tác giả hoàn tất nộp bài.
- Thông báo khi biên tập viên ghi nhận quyết định biên tập.
- Thông báo khi bài báo được xuất bản hoặc hủy xuất bản.
- Gửi lời mời phản biện và thông báo khi phản biện viên chấp nhận hoặc từ chối.
- Thông báo khi phản biện viên đã nộp đánh giá.
- Nhắc phản biện quá hạn tự động.
- Dashboard theo dõi bài đang phản biện, hạn của từng người và gửi nhắc thủ công theo bài hoặc theo cá nhân.
- Cấu hình nhóm nhận tin theo từng loại sự kiện.
- Tùy chỉnh mẫu tin cho Ban biên tập, phản biện viên và tác giả.
- Theo dõi, lọc và xuất nhật ký hoạt động.
- Gửi trực tiếp tới gateway ngay khi sự kiện xảy ra, không phụ thuộc worker nền.

## Yêu cầu hệ thống

- OJS 3.3.x.
- PHP tương thích với bản OJS đang sử dụng.
- Extension PHP cURL đã được bật.
- Máy chủ cho phép kết nối HTTPS ra ngoài đến `sms-service.talab.io.vn` qua cổng 443.
- Bot ID và API key hợp lệ do đơn vị cung cấp gateway cấp.
- Người nhận có số điện thoại Việt Nam hợp lệ trong hồ sơ OJS hoặc trong cấu hình nhóm.

Plugin chấp nhận số ở dạng `0912345678`, `84912345678` hoặc `0084912345678` và chuẩn hóa thành `84912345678`. Các số không khớp định dạng Việt Nam `84` cộng 9 chữ số sẽ bị bỏ qua.

## Cài đặt

### Cách 1: Sao chép thư mục

1. Sao chép toàn bộ thư mục plugin vào:

   ```text
   plugins/generic/zaloNotification
   ```

2. Đảm bảo tài khoản chạy web server có quyền đọc mã plugin và có quyền ghi vào thư mục `files_dir` của OJS.
3. Đăng nhập OJS bằng tài khoản quản trị tạp chí.
4. Vào **Settings → Website → Plugins → Generic Plugins**.
5. Tìm **Zalo Notification Plugin** và bật plugin.

### Cách 2: Cài bằng gói nén

Nén thư mục `zaloNotification` thành `.tar.gz`, sau đó tải lên tại **Settings → Website → Plugins → Upload A New Plugin**. Tên thư mục gốc trong gói phải là `zaloNotification`.

Nếu OJS đang bật cache, hãy xóa cache OJS sau khi cài hoặc cập nhật plugin.

## Cấu hình

Tại **Settings → Website → Plugins**, tìm plugin và chọn **Settings**. Cửa sổ cấu hình gồm ba tab.

### 1. Cấu hình API

Các thao tác lưu API, nhóm nhận và mẫu thông báo chỉ chấp nhận yêu cầu POST có token CSRF hợp lệ. Yêu cầu GET hoặc token không hợp lệ sẽ bị từ chối trước khi thay đổi plugin settings.

- Nhập **Bot ID** và **API Key** được gateway cấp, sau đó nhấn **Lưu**.
- API Key được lưu trong setting của từng tạp chí và không được hiển thị lại trên biểu mẫu.
- Khi API Key đã được cấu hình, để trống trường này lúc lưu sẽ giữ nguyên khóa hiện tại. Nhập giá trị mới sẽ thay thế khóa cũ.
- Plugin không còn chứa API Key dự phòng trong mã nguồn. Nếu chưa cấu hình khóa, thao tác gửi sẽ được bỏ qua với trạng thái “Chưa cấu hình API”.

### 2. Cài đặt nhóm thông báo

Mỗi nhóm gồm:

- **Tên nhóm**: ví dụ `Tổng biên tập`, `Ban biên tập`, `Thư ký tòa soạn`.
- **Số điện thoại**: có thể nhập nhiều số, phân cách bằng dấu phẩy.
- **Loại thông báo**: chọn một hoặc nhiều sự kiện mà nhóm muốn nhận.

Các loại sự kiện hỗ trợ:

| Mã sự kiện | Nội dung |
| --- | --- |
| `SUBMISSION` | Tác giả hoàn tất nộp bài |
| `DECISION` | Biên tập viên ra quyết định |
| `PUBLISH` | Bài báo được xuất bản |
| `UNPUBLISH` | Bài báo bị hủy xuất bản |
| `REVIEW_REQUEST` | Phân công hoặc gửi lời mời phản biện |
| `REVIEW_RESPONSE` | Phản biện viên chấp nhận hoặc từ chối lời mời |
| `REVIEW_REMINDER` | Nhắc hạn phản biện |
| `REVIEW_COMPLETED` | Phản biện viên đã nộp đánh giá |

Số điện thoại trùng nhau được loại bỏ trước khi gửi. Ngoài nhóm cấu hình, plugin còn có thể gửi trực tiếp đến số điện thoại trong hồ sơ của tác giả, phản biện viên hoặc biên tập viên được phân công, tùy sự kiện và mẫu tin đang bật.

Khi tác giả tải bản chỉnh sửa vào vòng phản biện, sự kiện `author_revision` chỉ gửi trực tiếp cho biên tập viên được gán vào bài, không gửi tới nhóm cấu hình. Nhiều file của cùng bài và vòng được tải liên tiếp trong 5 phút chỉ tạo một tin.

Lưu ý về tương thích trong phiên bản hiện tại:

- `REVIEW_REQUEST` cũng gửi đến nhóm đã chọn `REVIEW_REMINDER`.
- `REVIEW_RESPONSE` cũng gửi đến nhóm đã chọn `REVIEW_REQUEST` hoặc `REVIEW_REMINDER`.
- `REVIEW_COMPLETED` cũng gửi đến nhóm đã chọn `REVIEW_REMINDER`.

### 3. Mẫu thông báo

Mẫu **Tác giả → Từ chối ban đầu** được dùng riêng cho quyết định `INITIAL_DECLINE`, tránh mô tả nhầm đây là kết quả phản biện. Plugin chỉ gửi mẫu này khi OJS thực sự gửi email `EDITOR_DECISION_INITIAL_DECLINE`; nếu chọn Skip Email thì Zalo cũng không được gửi.

Mẫu tin được chia theo ba vai trò:

- **Ban biên tập**: mọi thông báo dùng mẫu Ban biên tập được gửi tới cả nhóm cấu hình và biên tập viên được phân công vào bài; số trùng giữa hai nguồn chỉ nhận một lần.
- **Phản biện viên**: nhận trực tiếp theo số điện thoại trong hồ sơ OJS.
- **Tác giả**: nhận trực tiếp theo số điện thoại trong hồ sơ OJS.

Chọn vai trò, chọn sự kiện, sửa nội dung rồi nhấn **Lưu**. Có thể xem trước tin nhắn, khôi phục một mẫu hoặc khôi phục toàn bộ mẫu mặc định.

Nếu để mẫu trống hoặc đặt nội dung là `Bạn không có thông báo cho sự kiện này.`, plugin sẽ bỏ qua việc gửi cho vai trò đó.

### Dashboard nhắc phản biện

Trong **Settings → Website**, tab **Nhắc phản biện** nằm ngay bên cạnh **Zalo Activity Log**. Tab này liệt kê các bài đang ở giai đoạn phản biện nội bộ hoặc phản biện ngoài và có lượt phản biện chưa hoàn tất. Mỗi bài hiển thị tiêu đề, tác giả, ngày nộp và link mở quy trình; mỗi phản biện viên hiển thị email, số điện thoại, vòng phản biện, trạng thái phản hồi, hạn phản hồi, hạn nộp đánh giá và lần nhắc gần nhất.

Dashboard hỗ trợ tìm theo ID, tiêu đề, tác giả hoặc phản biện viên; lọc theo tình trạng hạn và khả năng gửi; sắp xếp theo hạn gần nhất, ID hoặc tiêu đề; phân trang sáu bài mỗi trang và thu gọn từng bài hoặc toàn bộ danh sách.

- **Nhắc người này** gửi ngay tin cho phản biện viên được chọn, không phụ thuộc Acron hoặc cron hệ thống.
- **Nhắc cả bài** gửi ngay một tin riêng cho từng phản biện viên đủ điều kiện của bài.
- Lượt chưa gửi lời mời hoặc người dùng thiếu số điện thoại Việt Nam hợp lệ sẽ bị bỏ qua và nút cá nhân bị vô hiệu hóa.
- Thao tác gửi dùng mẫu **Phản biện viên → Nhắc nhở**, ghi Activity Log và cập nhật thời điểm nhắc trong OJS. Tất cả thông báo của plugin đều được gửi trực tiếp, không phụ thuộc Acron hoặc cron hệ thống.
- Yêu cầu gửi chỉ chấp nhận phương thức POST hợp lệ kèm CSRF token và chỉ xử lý dữ liệu thuộc tạp chí hiện tại.

## Biến dùng trong mẫu tin

Các biến được thay thế khi sự kiện xảy ra. Không phải biến nào cũng có dữ liệu trong mọi sự kiện.

| Biến | Ý nghĩa |
| --- | --- |
| `{title}` | Tiêu đề bài báo |
| `{author}` | Danh sách tác giả |
| `{abstract}` | Tóm tắt |
| `{abstract_if_any}` | Một dòng tóm tắt, chỉ xuất hiện khi có dữ liệu |
| `{submissionId}` | ID bài nộp |
| `{stageId}` | ID giai đoạn xử lý hiện tại |
| `{reviewId}` | ID nhiệm vụ phản biện, có ở các sự kiện phản biện trực tiếp |
| `{publicationId}` | ID phiên bản xuất bản hiện tại |
| `{workflowUrl}` | Link mở đúng giai đoạn quy trình cho biên tập viên |
| `{authorUrl}` | Link mở bài trong bảng điều khiển của tác giả |
| `{reviewerUrl}` | Link mở nhiệm vụ phản biện; người dùng vẫn phải đăng nhập và có quyền |
| `{publicUrl}` | Link công khai của bài đã xuất bản |
| `{timestamp}` | Thời điểm tạo thông báo |
| `{editorName}` | Tên biên tập viên |
| `{editorRole}` | Tên vai trò biên tập vừa được phân công |
| `{assignedBy}` | Tên người thực hiện phân công |
| `{decisionDesc}` | Mô tả quyết định biên tập |
| `{stageName}` | Tên giai đoạn xử lý |
| `{issueString}` | Thông tin số báo |
| `{issueString_if_any}` | Một dòng thông tin số báo, chỉ xuất hiện khi có dữ liệu |
| `{datePublished}` | Ngày xuất bản |
| `{reviewerName}` | Tên phản biện viên |
| `{deadline}` | Hạn phản hồi hoặc hạn nộp đánh giá |
| `{responseDeadline}` | Hạn phản hồi lời mời phản biện |
| `{daysLeft}` | Số ngày còn lại hoặc số ngày quá hạn |
| `{round}` | Vòng phản biện |
| `{responseStatus}` | Trạng thái chấp nhận/từ chối lời mời |
| `{recommendationDesc}` | Đề xuất của phản biện viên |

Ví dụ:

```text
▣ BÀI NỘP MỚI

› Bài: {title}
› Tác giả: {author}
› ID bài: {submissionId}
→ Mở bài: {workflowUrl}

⏱ Thời gian: {timestamp}
```

Các link sâu không chứa mật khẩu hoặc khóa truy cập một lần. Nếu người nhận chưa đăng nhập, OJS sẽ yêu cầu đăng nhập và chỉ cho mở nội dung mà tài khoản đó có quyền xem.

## Luồng người nhận

Thông báo dành cho phản biện viên chỉ lấy người thuộc lượt phản biện đang hoạt động của giai đoạn và vòng phản biện mới nhất. Các lượt đã hủy, đã từ chối, đã hoàn tất, thuộc giai đoạn khác hoặc vòng cũ đều bị loại.

| Sự kiện | Nhóm cấu hình | Tác giả | Phản biện viên | Biên tập viên được phân công |
| --- | --- | --- | --- | --- |
| Nộp bài | Có | Có | Không | Có, nếu đã được gán |
| Quyết định biên tập | Có | Có | Có, nếu mẫu được bật | Có |
| Xuất bản | Có | Có | Không theo mẫu mặc định | Có |
| Hủy xuất bản | Có | Có | Không theo mẫu mặc định | Có |
| Mời phản biện | Có | Không | Có | Có |
| Phản hồi lời mời | Có | Không | Không | Có |
| Nhắc phản biện | Có với luồng tự động | Không | Có đối với nhắc tự động và thủ công | Có đối với luồng tự động |
| Phản biện đã nộp | Có | Không theo mẫu mặc định | Không | Có |
| Tác giả nộp bản chỉnh sửa | Không | Không | Không | Chỉ gửi cho BTV được gán |
| Phân công biên tập viên | Không | Không | Không | Gửi riêng cho biên tập viên vừa được phân công |

Việc gửi trực tiếp chỉ thực hiện khi hồ sơ người dùng có số điện thoại và mẫu tương ứng không bị tắt.

## Gửi trực tiếp và retry qua outbox

Từ phiên bản 1.5.0.0, plugin gọi gateway trực tiếp ngay khi sự kiện xảy ra. Các tin nộp bài, quyết định biên tập, phân công, mời hoặc nhắc phản biện và xuất bản không cần Acron, cron hệ thống hay Windows Task Scheduler.

- Kết quả trả về ngay cho thao tác hiện tại và được ghi vào Activity Log.
- Mỗi lần gọi gateway có thời gian chờ tối đa 8 giây và thời gian thiết lập kết nối tối đa 3 giây.
- Nếu gateway hoặc mạng chậm, thao tác OJS hiện tại có thể chậm thêm vài giây.
- Nếu lần gửi trực tiếp thất bại, plugin tự động đưa tin vào outbox theo đúng context để worker thử lại.
- Outbox chống xếp trùng trong cửa sổ 5 phút. Worker thử tối đa 5 lần, giãn cách theo cấp số nhân 1, 2, 4 và 8 phút.
- Lỗi đầu vào xảy ra trước khi gọi gateway, như chưa cấu hình API hoặc không có số điện thoại hợp lệ, không được xếp vào outbox.

### Xác minh sau khi OJS lưu

Ba hook `EditorAction::recordDecision`, `EditorAction::setDueDates` và `ReviewerAction::confirmReview` của OJS 3.3 chạy trước thao tác ghi database. Plugin chỉ ghi nhận ý định tại các hook này. Cuối request, `PostSaveNotificationDispatcher` tải lại quyết định hoặc lượt phản biện từ database và chỉ gửi khi đúng thay đổi mới đã tồn tại. Nếu OJS không lưu thành công, callback tự bỏ qua và không gọi gateway.

Khi quyết định đã lưu đồng thời làm stage chuyển từ `WORKFLOW_STAGE_ID_SUBMISSION` sang phản biện nội bộ hoặc phản biện ngoài, plugin gửi mẫu `author.review_started` cho tác giả. Thông báo này không phụ thuộc email OJS, nhưng vẫn chỉ chạy sau khi stage mới được xác minh trong database.

Các hook form hoàn tất, phân công biên tập và `Publication::publish`/`Publication::unpublish` vốn đã chạy sau thao tác ghi của OJS. Chúng tiếp tục xử lý ngay tại hook hậu lưu và vẫn dùng khóa chống trùng.

## Nhắc phản biện quá hạn

Hook email nhắc tự động và luồng quét quá hạn dùng chung khóa theo context, review ID và ngày. Plugin đồng thời kiểm tra `date_reminded`, vì vậy một lượt phản biện đã được nhắc trong ngày sẽ không nhận thêm Zalo từ luồng còn lại.

Khi plugin được nạp, hệ thống kiểm tra các lượt phản biện thỏa các điều kiện:

- Chưa bị hủy.
- Không bị từ chối.
- Chưa hoàn tất.
- Có hạn nộp và hạn đã qua.

Scheduled task quét mỗi giờ, duyệt riêng từng tạp chí đang bật plugin, xử lý tối đa 25 lượt quá hạn thuộc đúng context trong mỗi lần và chỉ gửi một lần cho mỗi lượt phản biện trong ngày. Cấu hình API, nhóm nhận và mẫu thông báo luôn được đọc theo context của bài. Việc nạp plugin trong request web không còn kích hoạt quét.

Tệp khóa `deadline_scan_context_{contextId}.lock` chỉ ngăn hai worker quét cùng một tạp chí đồng thời.

## Nhật ký hoạt động

Mở **Settings → Website → Zalo Activity Log** để xem toàn bộ bản ghi còn trong thời hạn lưu 90 ngày, phân trang 20 bản ghi mỗi trang. Có thể:

- Lọc theo loại sự kiện.
- Làm mới danh sách.
- Xuất toàn bộ log hoặc phần đang lọc.
- Xem trạng thái gửi và lỗi liên quan.

Plugin ghi hai loại tệp chính:

- `zalo_notification_activity.log`: nhật ký nghiệp vụ dạng JSON Lines.
- `debug.txt`: thông tin kỹ thuật đã rút gọn như loại sự kiện, số lượng người nhận, mã HTTP và lỗi cURL. Plugin không ghi payload, nội dung tin, API key, phản hồi API hoặc danh sách số điện thoại vào debug log.

Thư mục lưu dữ liệu được chọn theo thứ tự:

1. `<files_dir>/zalo_notification`.
2. `<thư_mục_tạm_hệ_thống>/zalo_notification`.
3. Thư mục plugin.

Activity log được tách thành file riêng cho từng context (`zalo_notification_activity_context_<contextId>.log`) và xoay khi đạt 5 MB. Tất cả tệp xoay còn chứa bản ghi trong hạn đều được giữ; bản ghi quá 90 ngày được dọn tự động mỗi ngày. Trang xem và xuất log đọc toàn bộ tệp thuộc tạp chí hiện tại. Debug log được xoay khi đạt 1 MB và giữ tối đa hai tệp cũ. Các tệp log được đặt quyền `0600` khi hệ điều hành hỗ trợ; thư mục dữ liệu có tệp `.htaccess` chặn truy cập HTTP trực tiếp.

## Xử lý sự cố

### Không gửi được và báo “PHP cURL chưa bật”

Bật extension cURL trong `php.ini`, sau đó khởi động lại Apache/PHP-FPM. Với XAMPP trên Windows, kiểm tra dòng `extension=curl` không bị vô hiệu hóa.

### Báo “Chưa cấu hình API”

Kiểm tra Bot ID và API Key trong tab **Cấu hình API** của plugin. Nếu vừa nâng cấp từ phiên bản cũ, quản trị viên phải nhập và lưu API Key trước khi thử gửi lại.

### Báo “Không có người nhận”

Kiểm tra:

- Nhóm đã chọn đúng loại sự kiện.
- Danh sách có số điện thoại hợp lệ.
- Hồ sơ tác giả/phản biện/biên tập viên có trường điện thoại.
- Số điện thoại sau chuẩn hóa có dạng `84` cộng 9 chữ số.

### API trả mã lỗi HTTP

Mở `debug.txt` để xem mã HTTP và lỗi kết nối đã được rút gọn. Vì lý do bảo vệ dữ liệu, plugin không lưu nội dung phản hồi gateway. Kiểm tra Bot ID, API key, quyền của bot, giới hạn gửi và kết nối ra ngoài của máy chủ.

### Nhận tin trùng

Plugin loại số điện thoại trùng trong từng thông báo. Các handler dùng khóa nghiệp vụ trong Activity Log để hạn chế hook trùng cho cùng một sự kiện. Một người vẫn có thể xuất hiện trong hai thông báo nghiệp vụ khác nhau; kiểm tra Activity Log, `debug.txt` và cấu hình nhóm nếu vẫn nhận nhiều tin.

### Nhắc quá hạn không chạy

Kiểm tra:

- Plugin đang được bật.
- OJS có request trong khoảng thời gian cần quét.
- Web server ghi được `deadline_scan.lock`.
- Lượt phản biện có `date_due`, chưa hoàn tất, chưa hủy và chưa từ chối.
- Activity log chưa ghi khóa nhắc của ngày hiện tại.

## Bảo mật và vận hành production

Trước khi dùng trong môi trường production, nên thực hiện các việc sau:

- Không lưu API key trực tiếp trong repository; luân chuyển khóa nếu khóa đã từng được chia sẻ.
- Xác nhận quyền `0750` cho thư mục dữ liệu và `0600` cho log phù hợp với tài khoản chạy PHP trên máy chủ.
- Điều chỉnh thời hạn lưu 90 ngày trong `ActivityLogger.inc.php` nếu chính sách của đơn vị yêu cầu thời hạn ngắn hơn.
- Thông báo cho người dùng về việc số điện thoại và nội dung nghiệp vụ được gửi đến dịch vụ gateway bên thứ ba.
- Duy trì kho chứng chỉ CA của PHP/cURL để xác minh TLS hoạt động. Không tắt `CURLOPT_SSL_VERIFYPEER` hoặc `CURLOPT_SSL_VERIFYHOST` để xử lý lỗi chứng chỉ.
- Không đưa các tệp phát sinh khi gỡ lỗi hoặc khôi phục mã vào gói phát hành.

## Cấu trúc mã nguồn

| Tệp/thư mục | Vai trò |
| --- | --- |
| `index.php` | Điểm khởi tạo plugin |
| `version.xml` | Metadata và phiên bản plugin |
| `ZaloNotificationPlugin.inc.php` | Đăng ký hook, giao diện cấu hình và đọc setting |
| `StageChangeHandler.inc.php` | Facade ổn định cho các hook OJS; chuyển tiếp sự kiện đến handler phù hợp |
| `handlers/SubmissionNotificationHandler.inc.php` | Xử lý sự kiện nộp bài |
| `handlers/ReviewNotificationHandler.inc.php` | Xử lý lời mời, phản hồi, hạn và hoàn tất phản biện |
| `handlers/PublicationNotificationHandler.inc.php` | Xử lý xuất bản và hủy xuất bản |
| `handlers/EditorialNotificationHandler.inc.php` | Xử lý phân công biên tập, quyết định và email nghiệp vụ |
| `services/NotificationRecipientResolver.inc.php` | Tìm và khử trùng số điện thoại tác giả, biên tập viên và phản biện viên |
| `services/ZaloSettingsProvider.inc.php` | Đọc cấu hình gateway theo context hiện tại |
| `services/ZaloOutboxRepository.inc.php` | Lưu tin gửi lỗi, chống trùng và lên lịch retry |
| `services/PostSaveNotificationDispatcher.inc.php` | Xác minh thay đổi đã được OJS lưu trước khi gửi |
| `migrations/ZaloNotificationMigration.inc.php` | Tạo bảng outbox bền vững |
| `tasks/ZaloOutboxTask.inc.php` | Worker gửi lại các tin lỗi và ghi nhận kết quả |
| `tasks/ZaloOverdueReviewTask.inc.php` | Worker quét phản biện quá hạn theo từng context |
| `scheduledTasks.xml` | Đăng ký outbox mỗi phút và quét quá hạn mỗi giờ |
| `ZaloApiClient.inc.php` | Chuẩn hóa số, gửi trực tiếp và xếp outbox khi gateway lỗi |
| `MessageHelper.inc.php` | Tạo nội dung từ mẫu và lấy dữ liệu hiển thị |
| `ActivityLogger.inc.php` | Ghi, đọc, lọc và xoay activity log |
| `templates/` | Giao diện cấu hình, mẫu tin và activity log |
| `tests/architecture_smoke.php` | Kiểm tra điểm nạp lớp, luồng gửi trực tiếp và các phương thức facade/handler bắt buộc |
| `tests/decision_notification_regression.php` | Kiểm thử Skip Email, ẩn danh, hook trùng, lỗi API và giới hạn stage phản biện |
| `tests/navigation_link_test.php` | Kiểm tra link tác giả dùng đúng journal và submission |
| `tests/outbox_integration.php` | Kiểm tra hai enqueue giống nhau chỉ tạo một dòng; transaction luôn rollback |
| `tests/post_save_dispatcher_test.php` | Kiểm tra không gửi khi OJS chưa lưu và chỉ gửi một lần sau khi lưu |
| `tests/run.php` | Chạy toàn bộ bộ kiểm thử plugin |

### Kiến trúc xử lý sự kiện

```text
OJS Hook
   → ZaloNotificationPlugin
   → StageChangeHandler (facade tương thích)
   → Handler theo nhóm sự kiện
   → NotificationRecipientResolver
   → MessageHelper + ZaloApiClient
   → Zalo gateway
   → ActivityLogger
```

`StageChangeHandler` được giữ làm facade để các hook và phần tích hợp cũ không
phải thay đổi chữ ký gọi. Nghiệp vụ mới nên được đặt trong handler tương ứng,
không bổ sung trực tiếp vào facade.

Có thể chạy toàn bộ kiểm thử sau khi đóng gói hoặc nâng cấp bằng:

```text
php tests/run.php
```

Test tích hợp outbox cần database OJS hoạt động. Nếu database không kết nối được, test này báo `SKIP`; các test hành vi còn lại vẫn chạy và không gọi gateway Zalo thật.

## Ma trận sự kiện và hook

Bảng này là nguồn đối chiếu khi sửa luồng thông báo. Một sự kiện chỉ nên có một
hook gửi chính; các hook còn lại phải dùng khóa chống trùng hoặc chỉ làm nhiệm vụ
bổ trợ.

| Sự kiện | Hook OJS chính | Handler | Người nhận | Ghi chú chống trùng/quyền riêng tư |
| --- | --- | --- | --- | --- |
| Hoàn tất nộp bài | `submissionsubmitstep4form::execute` | `handleSubmissionSubmitStep4Form` | Ban biên tập, tác giả | `Submission::add`, `Submission::edit` là các nguồn dự phòng; dùng khóa `SUBMITTED_{submissionId}` |
| Phân công biên tập | `addparticipantform::execute` | `handleEditorAssigned` | Biên tập viên được phân công | Bỏ qua thao tác chỉ sửa quyền của phân công hiện có |
| Mời phản biện | `EditorAction::setDueDates` | `handleReviewDueDatesSet` | Ban biên tập, phản biện viên | `EditorAction::addReviewer` chỉ ghi nhận hook rồi chờ ngày hạn; khóa chứa submission, reviewer và review ID |
| Phản hồi lời mời | `ReviewerAction::confirmReview` | `handleReviewerResponseFromHook` | Ban biên tập | Phân biệt chấp nhận/từ chối theo review assignment |
| Hoàn tất phản biện | `reviewerreviewstep3form::execute` | `handleReviewerReviewCompleted` | Ban biên tập | Có thêm alias `pkpreviewerreviewstep3form::execute`; dùng chung khóa theo review ID |
| Nhắc hạn tự động | `Mail::send` với email nhắc | `handleMailEvent` | Nhóm Ban biên tập | Khóa theo email, submission và ngày |
| Quét phản biện quá hạn | Scheduled task mỗi giờ | `ZaloOverdueReviewTask` → `checkOverdueReviewDeadlines` | Ban biên tập, phản biện viên | Tách theo context, có khóa chạy đồng thời và khóa theo review ID/ngày |
| Ghi nhận quyết định | `EditorAction::recordDecision` | `handleDecision33` | Nhóm Ban biên tập, phản biện viên | Không gửi tác giả tại hook này |
| Chuyển bài sang phản biện | Quyết định đã lưu và stage đã đổi | `handleAuthorEnteredReview` | Tác giả | Không phụ thuộc email; khóa theo submission và stage đích |
| Email kết quả phản biện | `Mail::send` với `EDITOR_DECISION_*` | `handleMailEvent` | Tác giả | Chỉ gửi khi quyết định bắt nguồn từ giai đoạn phản biện; không lấy nội dung email, nhận xét hoặc danh tính phản biện |
| Xuất bản | `Publication::publish` | `handlePublish` | Ban biên tập, tác giả | Khóa theo submission và publication ID |
| Hủy xuất bản | `Publication::unpublish` | `handleUnpublish` | Ban biên tập, tác giả | Ghi log riêng cho mỗi lần hủy |

Lưu ý: hook `Mail::send` của OJS 3.3 chạy khi OJS bắt đầu gửi email, trước kết
quả cuối cùng của PHPMailer. Vì vậy thông báo Zalo xác nhận thao tác gửi email,
không khẳng định email đã được máy chủ thư tiếp nhận thành công.

## Kiểm tra sau cài đặt

1. Bật plugin và lưu Bot ID cùng API Key.
2. Tạo một nhóm thử nghiệm với một số điện thoại quản trị.
3. Chỉ chọn sự kiện `SUBMISSION` cho nhóm thử nghiệm.
4. Hoàn tất một bài nộp thử.
5. Xác nhận Activity Log hiển thị kết quả gửi trực tiếp, rồi kiểm tra Zalo và `debug.txt`.
6. Lặp lại với lời mời phản biện bằng tài khoản có số điện thoại trong hồ sơ.
7. Sau khi xác nhận hoạt động, cấu hình các nhóm và mẫu tin chính thức.

## Gỡ cài đặt

1. Tắt plugin trong danh sách Generic Plugins.
2. Sao lưu log nếu cần đối soát.
3. Xóa thư mục `plugins/generic/zaloNotification`.
4. Nếu muốn xóa hoàn toàn dữ liệu vận hành, xóa thư mục `<files_dir>/zalo_notification` hoặc thư mục tạm tương ứng và xóa bảng `zalo_notification_outbox` sau khi đã sao lưu.

Việc xóa thư mục plugin không tự động xóa các setting hoặc bảng outbox đã lưu trong cơ sở dữ liệu OJS.
