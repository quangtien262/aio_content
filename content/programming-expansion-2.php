<?php

return [
    'toi-uu-truy-van-sqlite.html' => [
        'vi' => [
            'title' => 'Tối ưu truy vấn SQLite: Index, EXPLAIN QUERY PLAN và những lỗi thường gặp',
            'slug' => 'toi-uu-truy-van-sqlite',
            'image' => 'toi-uu-truy-van-sqlite.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Tối ưu truy vấn SQLite bằng index và query plan',
            'meta_keywords' => 'SQLite, tối ưu SQLite, EXPLAIN QUERY PLAN, SQLite index, PRAGMA optimize, composite index',
            'meta_description' => 'Hướng dẫn đo và tối ưu truy vấn SQLite bằng EXPLAIN QUERY PLAN, index ghép, covering index và PRAGMA optimize.',
            'tags' => ['SQLite', 'SQL', 'Database', 'Query Optimization', 'Index', 'Backend', 'Performance'],
            'body' => <<<'HTML'
<p><strong>Khi ứng dụng SQLite chậm, thêm index theo cảm tính thường tạo thêm dung lượng và chi phí ghi mà chưa chắc sửa đúng truy vấn.</strong> Quy trình hiệu quả là đo truy vấn thật, đọc kế hoạch thực thi, thiết kế index theo điều kiện lọc và sắp xếp, rồi đo lại trên dữ liệu có kích thước gần production.</p>

<h2>Bắt đầu bằng truy vấn chậm, không bắt đầu bằng index</h2>
<p>Ghi nhận câu SQL, tham số, thời gian chạy, số hàng và tần suất gọi. Một truy vấn 80 ms chạy mỗi giờ ít quan trọng hơn truy vấn 8 ms xuất hiện hàng nghìn lần trong một thao tác. Hãy kiểm tra cả thời gian khóa và số lần truy vấn do ORM tạo ra.</p>
<pre><code>EXPLAIN QUERY PLAN
SELECT id, total
FROM orders
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 20;</code></pre>
<p><code>EXPLAIN QUERY PLAN</code> cho biết SQLite đang quét toàn bảng (<code>SCAN</code>), tìm qua index (<code>SEARCH ... USING INDEX</code>) hay tạo B-tree tạm để sắp xếp. Đầu ra dành cho gỡ lỗi tương tác và có thể thay đổi giữa các phiên bản; không nên viết logic ứng dụng phụ thuộc chuỗi mô tả này.</p>

<h2>Thiết kế index ghép theo truy vấn</h2>
<p>Với truy vấn trên, một index hợp lý là:</p>
<pre><code>CREATE INDEX idx_orders_customer_status_created
ON orders(customer_id, status, created_at DESC);</code></pre>
<p>Các cột so sánh bằng thường đứng trước, sau đó đến cột phạm vi hoặc sắp xếp. Thứ tự cột rất quan trọng vì index nhiều cột hoạt động theo tiền tố bên trái. Một index bắt đầu bằng <code>customer_id</code> có thể hỗ trợ truy vấn theo khách hàng, nhưng thường không giúp nhiều cho truy vấn chỉ lọc <code>status</code>.</p>

<h2>Covering index: nhanh hơn nhưng không miễn phí</h2>
<p>Nếu index chứa cả các cột cần trả về, SQLite có thể đọc kết quả mà không quay lại bảng. Thêm <code>total</code> vào cuối index có thể tạo covering index cho ví dụ trên. Đổi lại, file cơ sở dữ liệu lớn hơn, mỗi lần ghi phải cập nhật nhiều dữ liệu hơn và cache chứa ít trang hữu ích hơn.</p>
<blockquote>Không tạo một index riêng cho mọi truy vấn. Hãy tìm index có thể phục vụ nhiều truy vấn quan trọng và loại bỏ index trùng tiền tố khi đã xác minh không còn cần thiết.</blockquote>

<h2>Khi index không được sử dụng</h2>
<ul><li>Bảng nhỏ khiến quét toàn bảng rẻ hơn.</li><li>Cột có độ chọn lọc thấp, chẳng hạn phần lớn hàng có cùng trạng thái.</li><li>Điều kiện dùng hàm hoặc phép biến đổi không khớp expression index.</li><li>Kiểu dữ liệu tham số và cột không phù hợp.</li><li>Index không khớp tiền tố trái hoặc truy vấn trả về phần lớn bảng.</li><li>Thống kê chưa phản ánh dữ liệu hiện tại.</li></ul>
<p>Đừng ép index bằng <code>INDEXED BY</code> như một mẹo tối ưu thông thường. Tài liệu SQLite mô tả nó chủ yếu như cách phát hiện thay đổi kế hoạch ngoài ý muốn, không phải lời gợi ý mềm cho planner.</p>

<h2>Cập nhật thống kê bằng PRAGMA optimize</h2>
<p>SQLite khuyến nghị chạy <code>PRAGMA optimize;</code> định kỳ và sau thay đổi schema, đặc biệt sau khi tạo index. Với kết nối tồn tại lâu, có thể chạy <code>PRAGMA optimize=0x10002;</code> khi mở rồi gọi <code>PRAGMA optimize;</code> theo chu kỳ hoặc trước khi đóng. Lệnh thường không làm gì và chỉ chạy ANALYZE khi planner có thể hưởng lợi.</p>

<h2>Đừng quên mẫu N+1 và phân trang</h2>
<p>Một truy vấn nhanh vẫn tạo hệ thống chậm nếu bị gọi một lần cho mỗi hàng. Dùng eager loading, join hoặc truy vấn theo lô để loại N+1. Với bảng lớn, phân trang bằng con trỏ như <code>WHERE id &lt; ? ORDER BY id DESC LIMIT ?</code> thường ổn định hơn OFFSET sâu.</p>

<h2>Checklist tối ưu an toàn</h2>
<ol><li>Tái hiện trên dữ liệu gần kích thước thật.</li><li>Đo trước và sau bằng cùng truy vấn, tham số và cache state.</li><li>Đọc query plan, không suy đoán.</li><li>Thêm một thay đổi tại một thời điểm.</li><li>Đo tác động lên INSERT, UPDATE, dung lượng và migration.</li><li>Chạy test đúng kết quả, không chỉ benchmark tốc độ.</li><li>Theo dõi truy vấn chậm sau khi phát hành.</li></ol>

<h2>Kết luận</h2>
<p>Tối ưu SQLite là bài toán cân bằng giữa tốc độ đọc, chi phí ghi và độ phức tạp vận hành. Query plan cho biết cơ sở dữ liệu đang làm gì; index ghép và covering index chỉ nên được thêm khi số liệu chứng minh chúng giải quyết một đường truy cập quan trọng.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://www.sqlite.org/eqp.html" target="_blank" rel="noopener noreferrer">SQLite: EXPLAIN QUERY PLAN</a></li><li><a href="https://www.sqlite.org/queryplanner.html" target="_blank" rel="noopener noreferrer">SQLite: Query Planning</a></li><li><a href="https://www.sqlite.org/lang_analyze.html" target="_blank" rel="noopener noreferrer">SQLite: ANALYZE and PRAGMA optimize</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Optimizing SQLite Queries: Indexes, EXPLAIN QUERY PLAN, and Common Mistakes',
            'slug' => 'optimizing-sqlite-queries-indexes-query-plan',
            'image' => 'toi-uu-truy-van-sqlite.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Optimize SQLite with Indexes and Query Plans',
            'meta_keywords' => 'SQLite, SQLite optimization, EXPLAIN QUERY PLAN, SQLite index, PRAGMA optimize, composite index',
            'meta_description' => 'Measure and optimize SQLite queries with EXPLAIN QUERY PLAN, composite and covering indexes, and PRAGMA optimize.',
            'tags' => ['SQLite', 'SQL', 'Database', 'Query Optimization', 'Index', 'Backend', 'Performance'],
            'body' => <<<'HTML'
<p><strong>When a SQLite application becomes slow, adding indexes by instinct often increases storage and write cost without fixing the actual query.</strong> A better process measures a real workload, inspects its execution plan, designs an index around filtering and ordering, and measures again with production-like data.</p>

<h2>Start with a slow query, not an index</h2>
<p>Capture the SQL, parameters, duration, row count, and call frequency. A query taking 80 ms once an hour matters less than an 8 ms query executed thousands of times per action. Include lock time and ORM-generated query counts.</p>
<pre><code>EXPLAIN QUERY PLAN
SELECT id, total
FROM orders
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 20;</code></pre>
<p><code>EXPLAIN QUERY PLAN</code> shows whether SQLite performs a table <code>SCAN</code>, an indexed <code>SEARCH</code>, or creates a temporary B-tree for sorting. Its output is intended for interactive debugging and can change between SQLite versions, so application logic should not parse its description text.</p>

<h2>Design composite indexes around access patterns</h2>
<pre><code>CREATE INDEX idx_orders_customer_status_created
ON orders(customer_id, status, created_at DESC);</code></pre>
<p>Equality columns commonly come first, followed by range or ordering columns. Column order matters because a multi-column index follows a left-most-prefix rule. An index beginning with <code>customer_id</code> can help customer queries but usually offers little to a query filtering only by <code>status</code>.</p>

<h2>Covering indexes have a cost</h2>
<p>If an index includes every column needed by the query, SQLite may return results without looking up the table. Adding <code>total</code> to the example index could make it covering. The tradeoff is a larger database, more work on every write, and fewer useful pages in cache.</p>
<blockquote>Do not create one index per query. Look for indexes that support several important access patterns and remove redundant prefixes only after verifying they are unused.</blockquote>

<h2>Why an index might not be used</h2>
<ul><li>The table is small enough that a scan is cheaper.</li><li>The indexed value has low selectivity.</li><li>A function or transformation does not match an expression index.</li><li>Parameter and column data types do not align.</li><li>The query does not match the left-most prefix or returns most rows.</li><li>Planner statistics no longer represent current data.</li></ul>
<p>Avoid using <code>INDEXED BY</code> as a routine tuning hint. SQLite documents it primarily as a way to detect unwanted plan changes, not as a soft instruction to the planner.</p>

<h2>Refresh statistics with PRAGMA optimize</h2>
<p>SQLite recommends running <code>PRAGMA optimize;</code> periodically and after schema changes, especially after creating indexes. Long-lived connections can run <code>PRAGMA optimize=0x10002;</code> when opened and invoke the regular command periodically or before closing. It is usually a no-op and performs ANALYZE work only when useful.</p>

<h2>Watch for N+1 and deep pagination</h2>
<p>A fast query still creates a slow system when called once per result row. Use eager loading, joins, or batches to remove N+1 patterns. For large tables, cursor pagination such as <code>WHERE id &lt; ? ORDER BY id DESC LIMIT ?</code> is often more stable than a deep OFFSET.</p>

<h2>Safe optimization checklist</h2>
<ol><li>Reproduce with production-like data volume.</li><li>Benchmark before and after under equivalent cache conditions.</li><li>Read the plan instead of guessing.</li><li>Change one thing at a time.</li><li>Measure INSERT, UPDATE, storage, and migration impact.</li><li>Test correctness as well as speed.</li><li>Monitor slow queries after release.</li></ol>

<h2>Conclusion</h2>
<p>SQLite optimization balances read speed, write overhead, and operational complexity. The query plan reveals what the database is doing; composite and covering indexes should be added only when measurements show that they improve an important access path.</p>

<h2>References</h2>
<ul><li><a href="https://www.sqlite.org/eqp.html" target="_blank" rel="noopener noreferrer">SQLite: EXPLAIN QUERY PLAN</a></li><li><a href="https://www.sqlite.org/queryplanner.html" target="_blank" rel="noopener noreferrer">SQLite: Query Planning</a></li><li><a href="https://www.sqlite.org/lang_analyze.html" target="_blank" rel="noopener noreferrer">SQLite: ANALYZE and PRAGMA optimize</a></li></ul>
HTML,
        ],
    ],
    'thiet-ke-webhook-dang-tin-cay.html' => [
        'vi' => [
            'title' => 'Thiết kế webhook đáng tin cậy: Chữ ký, idempotency, retry và hàng đợi',
            'slug' => 'thiet-ke-webhook-dang-tin-cay',
            'image' => 'thiet-ke-webhook-dang-tin-cay.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Thiết kế webhook đáng tin cậy và an toàn',
            'meta_keywords' => 'webhook, idempotency, webhook signature, retry, queue, event processing',
            'meta_description' => 'Thiết kế webhook an toàn với xác minh chữ ký, chống xử lý trùng, phản hồi nhanh, queue, retry và observability.',
            'tags' => ['Webhook', 'Idempotency', 'API', 'Queue', 'Security', 'Backend', 'Distributed Systems'],
            'body' => <<<'HTML'
<p><strong>Webhook là một kênh giao sự kiện qua mạng, vì vậy ứng dụng phải giả định sự kiện có thể đến trễ, đến nhiều lần, sai thứ tự hoặc không đến trong lần thử đầu tiên.</strong> Một endpoint trả về 200 không đồng nghĩa nghiệp vụ đã hoàn tất; kiến trúc đáng tin cậy cần tách tiếp nhận khỏi xử lý.</p>

<h2>Luồng tiếp nhận tối thiểu</h2>
<ol><li>Đọc raw request body và các header cần thiết.</li><li>Xác minh chữ ký cùng giới hạn thời gian để chống replay.</li><li>Kiểm tra event ID trong kho idempotency.</li><li>Lưu event và trạng thái tiếp nhận trong một transaction.</li><li>Đưa công việc vào queue.</li><li>Trả phản hồi 2xx nhanh.</li></ol>
<p>Stripe lưu ý rằng thay đổi raw body trước khi xác minh sẽ làm kiểm tra chữ ký thất bại. Cách ký, header và tolerance khác nhau theo nhà cung cấp, nên luôn dùng SDK hoặc tài liệu chính thức của nguồn phát.</p>

<h2>Idempotency chống tác động lặp</h2>
<p>Nhà cung cấp có thể gửi lại cùng event khi timeout hoặc nhận lỗi. Tạo unique constraint theo <code>provider + event_id</code> và coi vi phạm unique là sự kiện đã nhận. Chỉ kiểm tra rồi chèn bằng hai thao tác rời rạc có thể tạo race condition.</p>
<pre><code>BEGIN;
INSERT INTO webhook_events(provider, event_id, payload, status)
VALUES (?, ?, ?, 'received')
ON CONFLICT(provider, event_id) DO NOTHING;
COMMIT;</code></pre>
<p>Idempotency cần kéo dài đến tác động nghiệp vụ. Ví dụ, cập nhật đơn hàng và ghi dấu event nên nằm trong cùng transaction; gửi email hoặc gọi dịch vụ khác cần khóa nghiệp vụ hoặc outbox để tránh chạy hai lần.</p>

<h2>Phản hồi nhanh, xử lý ở nền</h2>
<p>Endpoint không nên tạo hóa đơn, gửi email và đồng bộ CRM trước khi trả lời. Ghi bền event, enqueue rồi trả 2xx giúp tránh timeout và retry không cần thiết. Worker có thể retry độc lập với backoff và đưa lỗi lâu dài vào dead-letter queue.</p>
<blockquote>Chỉ trả 2xx sau khi event đã được lưu bền hoặc được giao chắc chắn cho queue. Trả thành công trước bước này có thể làm mất sự kiện nếu tiến trình dừng.</blockquote>

<h2>Không giả định thứ tự sự kiện</h2>
<p>Các event có thể được xử lý song song hoặc đến sai thứ tự. Dùng version, timestamp, state machine và điều kiện cập nhật để từ chối trạng thái cũ. Khi nhà cung cấp hỗ trợ, có thể đọc lại resource hiện tại từ API thay vì tin hoàn toàn vào snapshot cũ.</p>

<h2>Retry có giới hạn</h2>
<p>Chỉ retry lỗi tạm thời như timeout, 429 hoặc 5xx. Lỗi dữ liệu không hợp lệ cần được ghi nhận và chuyển sang hàng chờ xử lý thủ công. Dùng exponential backoff có jitter, giới hạn số lần thử và đặt timeout cho mọi request đi ra.</p>

<h2>Bảo mật endpoint</h2>
<ul><li>Chỉ nhận HTTPS và xác minh chữ ký trước khi parse nghiệp vụ.</li><li>Lưu secret trong secret manager và hỗ trợ xoay khóa có giai đoạn chồng lấn.</li><li>Giới hạn kích thước body và loại event đăng ký.</li><li>Không ghi secret hoặc dữ liệu nhạy cảm đầy đủ vào log.</li><li>IP allowlist chỉ là lớp bổ sung vì dải IP có thể thay đổi.</li></ul>

<h2>Observability và vận hành</h2>
<p>Mỗi event cần correlation ID, thời điểm nhận, số lần thử, trạng thái, lỗi cuối và thời gian xử lý. Dashboard nên hiển thị tỷ lệ xác minh thất bại, độ trễ queue, retry, dead-letter và tuổi của event chưa hoàn tất. Xây công cụ replay có phân quyền, audit log và vẫn đi qua idempotency.</p>

<h2>Kịch bản kiểm thử bắt buộc</h2>
<ul><li>Chữ ký sai, timestamp quá cũ và body bị thay đổi.</li><li>Cùng event được gửi đồng thời nhiều lần.</li><li>Worker dừng sau khi cập nhật database nhưng trước khi ack.</li><li>Event đến sai thứ tự.</li><li>Dịch vụ phụ thuộc trả 429, 500 hoặc timeout.</li><li>Replay từ dead-letter queue.</li></ul>

<h2>Kết luận</h2>
<p>Webhook đáng tin cậy được xây trên giả định giao ít nhất một lần: xác minh nguồn gửi, lưu bền, chống trùng, xử lý bất đồng bộ và quan sát được. Khi các lớp này rõ ràng, retry trở thành cơ chế phục hồi thay vì nguyên nhân tạo dữ liệu trùng.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://docs.stripe.com/webhooks" target="_blank" rel="noopener noreferrer">Stripe Docs: Receive events in a webhook endpoint</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Designing Reliable Webhooks: Signatures, Idempotency, Retries, and Queues',
            'slug' => 'designing-reliable-webhooks',
            'image' => 'thiet-ke-webhook-dang-tin-cay.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Design Reliable and Secure Webhooks',
            'meta_keywords' => 'webhook, idempotency, webhook signature, retry, queue, event processing',
            'meta_description' => 'Build secure webhooks with signature verification, deduplication, fast responses, queues, retries, and observability.',
            'tags' => ['Webhook', 'Idempotency', 'API', 'Queue', 'Security', 'Backend', 'Distributed Systems'],
            'body' => <<<'HTML'
<p><strong>A webhook delivers events over a network, so applications must assume an event can be delayed, duplicated, reordered, or missed on the first attempt.</strong> A 200 response does not mean the business operation is complete; a reliable architecture separates ingestion from processing.</p>

<h2>A minimal ingestion flow</h2>
<ol><li>Read the raw request body and required headers.</li><li>Verify the signature and timestamp tolerance.</li><li>Check the event ID in an idempotency store.</li><li>Persist the event and receipt state in a transaction.</li><li>Enqueue processing.</li><li>Return a 2xx response quickly.</li></ol>
<p>Stripe notes that modifying the raw body before verification breaks signature checking. Signature algorithms, headers, and tolerances vary by provider, so use the sender's official SDK or documentation.</p>

<h2>Idempotency prevents repeated effects</h2>
<p>A provider may redeliver an event after a timeout or error. Create a unique constraint on <code>provider + event_id</code> and treat a uniqueness conflict as an already-received event. A separate check followed by insert is vulnerable to races.</p>
<pre><code>BEGIN;
INSERT INTO webhook_events(provider, event_id, payload, status)
VALUES (?, ?, ?, 'received')
ON CONFLICT(provider, event_id) DO NOTHING;
COMMIT;</code></pre>
<p>Idempotency must extend to business effects. Updating an order and marking an event should share a transaction. Email or external calls need a business key or transactional outbox so they are not executed twice.</p>

<h2>Respond quickly and process in the background</h2>
<p>The endpoint should not create invoices, send email, and synchronize a CRM before responding. Durably persist, enqueue, and return 2xx to avoid timeouts and unnecessary redelivery. A worker can retry with backoff and move permanent failures to a dead-letter queue.</p>
<blockquote>Return 2xx only after the event is durably stored or reliably handed to a queue. A success response before that point can lose the event if the process stops.</blockquote>

<h2>Do not assume event order</h2>
<p>Events may be processed concurrently or arrive out of sequence. Use versions, timestamps, state machines, and conditional updates to reject stale transitions. When supported, retrieve the resource's current state from the provider API rather than trusting an old snapshot.</p>

<h2>Use bounded retries</h2>
<p>Retry transient failures such as timeouts, 429, and 5xx responses. Invalid data should be recorded and routed for manual review. Use exponential backoff with jitter, cap the attempt count, and set timeouts on every outbound request.</p>

<h2>Secure the endpoint</h2>
<ul><li>Require HTTPS and verify signatures before business parsing.</li><li>Keep secrets in a secret manager and support overlapping rotation.</li><li>Limit body size and subscribed event types.</li><li>Do not log secrets or complete sensitive payloads.</li><li>Treat IP allowlists as an additional layer, not primary authentication.</li></ul>

<h2>Observability and operations</h2>
<p>Track a correlation ID, received time, attempt count, state, last error, and processing duration. Dashboards should expose verification failures, queue lag, retries, dead letters, and the age of unfinished events. Replay tools need authorization and audit logs and must still pass through idempotency controls.</p>

<h2>Required test scenarios</h2>
<ul><li>Bad signatures, expired timestamps, and modified bodies.</li><li>The same event delivered concurrently.</li><li>A worker stopping after a database update but before acknowledgement.</li><li>Out-of-order events.</li><li>Dependencies returning 429, 500, or timeout.</li><li>Replay from the dead-letter queue.</li></ul>

<h2>Conclusion</h2>
<p>Reliable webhook handling assumes at-least-once delivery: authenticate the sender, persist durably, deduplicate, process asynchronously, and make operations observable. With these layers in place, retries become a recovery mechanism instead of a source of duplicate data.</p>

<h2>Reference</h2>
<ul><li><a href="https://docs.stripe.com/webhooks" target="_blank" rel="noopener noreferrer">Stripe Docs: Receive events in a webhook endpoint</a></li></ul>
HTML,
        ],
    ],
];

