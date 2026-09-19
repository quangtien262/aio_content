<?php

return [
    'transactional-outbox-pattern.html' => [
        'vi' => [
            'title' => 'Transactional Outbox thực chiến: Phát sự kiện đáng tin cậy sau transaction',
            'slug' => 'transactional-outbox-phat-su-kien-dang-tin-cay',
            'image' => 'transactional-outbox-pattern.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Transactional Outbox thực chiến cho hệ thống event-driven',
            'meta_keywords' => 'transactional outbox, event driven, message broker, idempotency, PostgreSQL, Kafka, RabbitMQ, microservices',
            'meta_description' => 'Hiểu và triển khai Transactional Outbox: thiết kế bảng event, commit nguyên tử, relay bằng polling hoặc CDC, retry, idempotency, ordering và vận hành production.',
            'tags' => ['Transactional Outbox', 'Event-Driven', 'PostgreSQL', 'Kafka', 'RabbitMQ', 'Microservices', 'Backend'],
            'body' => <<<'HTML'
<p><strong>Một request tạo đơn hàng thường phải làm hai việc: lưu đơn vào database và phát sự kiện <code>OrderCreated</code> sang message broker.</strong> Nếu commit database trước rồi publish thất bại, hệ thống có đơn hàng nhưng kho hoặc email không biết. Nếu publish trước rồi transaction rollback, consumer lại xử lý một đơn không tồn tại. Đây là bài toán <em>dual write</em>.</p>
<p>Transactional Outbox giải quyết bằng cách ghi dữ liệu nghiệp vụ và event vào cùng một database transaction. Một relay độc lập chỉ đọc event đã commit rồi chuyển chúng sang Kafka, RabbitMQ, SQS hoặc broker khác.</p>

<h2>Vì sao “commit rồi publish” không đủ?</h2>
<pre><code>$order = $orders-&gt;create($input);
$database-&gt;commit();

$broker-&gt;publish('OrderCreated', $order);</code></pre>
<p>Process có thể crash sau commit nhưng trước publish. Retry HTTP request cũng không chắc sửa được vì client có thể đã nhận timeout, hoặc idempotency của API chưa đúng. Đảo thứ tự publish trước commit tạo lỗi ngược lại.</p>
<p>Hai hệ thống độc lập không thể được bọc bằng transaction database thông thường. Distributed transaction/2PC có chi phí và mức hỗ trợ khác nhau; với nhiều kiến trúc web, outbox là lựa chọn thực dụng hơn.</p>

<h2>Luồng hoạt động của Transactional Outbox</h2>
<ol>
<li>Ứng dụng bắt đầu database transaction.</li>
<li>Ghi thay đổi nghiệp vụ, ví dụ tạo order.</li>
<li>Insert một event vào bảng outbox trong cùng transaction.</li>
<li>Commit. Hoặc cả hai cùng tồn tại, hoặc cả hai cùng rollback.</li>
<li>Relay đọc event chưa phát và publish tới broker.</li>
<li>Relay đánh dấu event đã phát hoặc CDC ghi nhận vị trí đã đọc.</li>
<li>Consumer xử lý event theo cơ chế idempotent.</li>
</ol>
<blockquote>Outbox bảo đảm event không bị tách khỏi transaction nguồn; nó không tự biến toàn bộ pipeline thành exactly-once.</blockquote>

<h2>1. Thiết kế bảng outbox</h2>
<pre><code>CREATE TABLE outbox_events (
    id uuid PRIMARY KEY,
    aggregate_type varchar(100) NOT NULL,
    aggregate_id varchar(100) NOT NULL,
    event_type varchar(150) NOT NULL,
    event_version integer NOT NULL DEFAULT 1,
    payload jsonb NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    available_at timestamptz NOT NULL DEFAULT now(),
    published_at timestamptz,
    attempts integer NOT NULL DEFAULT 0,
    last_error text
);

CREATE INDEX idx_outbox_pending
ON outbox_events (available_at, occurred_at)
WHERE published_at IS NULL;</code></pre>
<p><code>id</code> là định danh event duy nhất dùng để deduplicate. <code>aggregate_id</code> thường làm message key để các event của cùng order/customer vào cùng partition. <code>event_version</code> là phiên bản schema payload, không phải phiên bản application.</p>
<p>Payload nên chứa dữ liệu consumer cần tại thời điểm sự kiện xảy ra, nhưng tránh copy secret hoặc toàn bộ record không cần thiết. Event là hợp đồng bất biến; đừng sửa payload cũ sau khi đã insert.</p>

<h2>2. Ghi business data và event trong cùng transaction</h2>
<pre><code>BEGIN;

INSERT INTO orders (id, customer_id, total, status)
VALUES (:order_id, :customer_id, :total, 'pending');

INSERT INTO outbox_events (
    id, aggregate_type, aggregate_id,
    event_type, event_version, payload
) VALUES (
    :event_id, 'order', :order_id,
    'OrderCreated', 1, :payload::jsonb
);

COMMIT;</code></pre>
<p>Không publish broker bên trong transaction. Network call kéo dài thời gian giữ lock và broker vẫn không tham gia atomic commit của database. Transaction chỉ làm công việc local, ngắn và có thể rollback.</p>
<p>Tạo event ID từ ứng dụng trước khi insert. Payload nên được validate bằng schema/test trước khi commit để tránh event lỗi nằm trong outbox và chặn relay.</p>

<h2>3. Relay bằng polling</h2>
<p>Polling publisher dễ triển khai khi lưu lượng vừa phải và chưa có hạ tầng CDC. Nhiều worker có thể lấy batch bằng <code>FOR UPDATE SKIP LOCKED</code>:</p>
<pre><code>BEGIN;

SELECT id, aggregate_type, aggregate_id,
       event_type, event_version, payload
FROM outbox_events
WHERE published_at IS NULL
  AND available_at &lt;= now()
ORDER BY occurred_at, id
FOR UPDATE SKIP LOCKED
LIMIT 100;</code></pre>
<p>Sau khi chọn, có hai chiến lược. Giữ transaction trong lúc publish giúp coordination đơn giản nhưng lock lâu và kết nối database bị chiếm. Claim batch bằng trạng thái/lease rồi commit trước khi publish mở rộng tốt hơn, nhưng phải phục hồi event khi worker chết giữa chừng.</p>
<p><code>SKIP LOCKED</code> phù hợp cho bảng kiểu queue; nó tạo góc nhìn không nhất quán nên không dùng cho truy vấn nghiệp vụ tổng quát.</p>

<h2>4. Cửa sổ gửi trùng là không thể tránh hoàn toàn</h2>
<p>Relay publish thành công rồi crash trước khi ghi <code>published_at</code>. Lần chạy sau nó sẽ publish lại event. Nếu đánh dấu trước khi publish, crash ở giữa lại làm mất event. Vì vậy polling outbox thường mang ngữ nghĩa <strong>at-least-once</strong>.</p>
<p>Broker có tính năng dedup hoặc producer idempotent vẫn không loại bỏ mọi duplicate xuyên suốt nhiều hệ thống. Event ID phải đi trong message header hoặc envelope, và consumer phải xử lý lặp an toàn.</p>

<h2>5. Consumer idempotent với inbox</h2>
<pre><code>CREATE TABLE consumed_events (
    consumer_name varchar(100) NOT NULL,
    event_id uuid NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (consumer_name, event_id)
);</code></pre>
<p>Consumer mở transaction, insert khóa dedup rồi cập nhật business data. Nếu unique conflict, event đã được xử lý và có thể ack mà không thực hiện side effect lần nữa:</p>
<pre><code>BEGIN;

INSERT INTO consumed_events (consumer_name, event_id)
VALUES ('inventory-service', :event_id)
ON CONFLICT DO NOTHING;

-- Chỉ tiếp tục nếu insert thực sự tạo một hàng
UPDATE inventory
SET reserved = reserved + :quantity
WHERE product_id = :product_id;

COMMIT;</code></pre>
<p>Đối với email, thanh toán hoặc API bên thứ ba, database transaction không bao phủ side effect. Dùng idempotency key phía nhà cung cấp, một outbox tiếp theo, hoặc lưu trạng thái state machine để retry không gửi/charge hai lần.</p>

<h2>6. Retry, backoff và dead-letter</h2>
<p>Lỗi mạng tạm thời cần retry với exponential backoff và jitter. Lỗi payload cố định không nên retry vô hạn với tốc độ cao. Sau số lần nhất định, chuyển event sang trạng thái failed/dead-letter nhưng giữ dữ liệu để điều tra.</p>
<pre><code>UPDATE outbox_events
SET attempts = attempts + 1,
    available_at = now() + (:delay_seconds * interval '1 second'),
    last_error = :safe_error
WHERE id = :event_id;</code></pre>
<p>Không lưu credential hoặc toàn bộ response nhạy cảm vào <code>last_error</code>. Dashboard cần hiển thị tuổi event cũ nhất, pending count, publish latency, retry rate và dead-letter count.</p>

<h2>7. Thứ tự event và aggregate version</h2>
<p>Thứ tự toàn cục rất đắt và hiếm khi cần. Thông thường chỉ cần giữ thứ tự theo aggregate, chẳng hạn các event của cùng <code>order_id</code>. Dùng <code>aggregate_id</code> làm partition key và thêm sequence/version tăng dần trên aggregate.</p>
<p>Consumer không nên phụ thuộc tuyệt đối vào thời gian tạo từ nhiều máy. Nó có thể lưu version cuối đã áp dụng, bỏ event cũ và tạm giữ event đến sớm hơn version còn thiếu. Cần định nghĩa rõ hành vi khi event đến sai thứ tự hoặc mất quá lâu.</p>

<h2>8. Schema event phải tiến hóa được</h2>
<p>Consumer có thể triển khai chậm hơn producer nhiều ngày. Không đổi nghĩa field hiện có hoặc xóa field đột ngột. Ưu tiên thay đổi additive: thêm field optional, giữ default hợp lý và version hóa event khi có breaking change.</p>
<pre><code>{
  "event_id": "...",
  "event_type": "OrderCreated",
  "event_version": 1,
  "occurred_at": "2026-09-19T14:00:00+07:00",
  "aggregate_id": "order-123",
  "data": {
    "customer_id": "customer-9",
    "total": 1250000,
    "currency": "VND"
  }
}</code></pre>
<p>Dùng contract test hoặc schema registry khi số producer/consumer tăng. Event name nên mô tả sự kiện đã xảy ra, không phải mệnh lệnh mơ hồ.</p>

<h2>9. Polling hay CDC?</h2>
<p><strong>Polling</strong> đơn giản, dễ debug và phù hợp hệ thống nhỏ/vừa. Đổi lại, bạn phải quản lý worker, lease, retry, cleanup và polling interval.</p>
<p><strong>CDC</strong> dùng log thay đổi database, ví dụ Debezium đọc outbox rồi Outbox Event Router chuyển event tới Kafka. Ứng dụng chỉ insert event; connector phụ trách phát. Cách này có throughput và latency tốt nhưng thêm Kafka Connect, offset, replication slot và yêu cầu vận hành phức tạp.</p>
<p>CDC không loại bỏ idempotency ở consumer. Connector hoặc broker vẫn có thể redeliver trong một số failure mode. Chọn theo quy mô và năng lực vận hành, không chỉ theo mức độ “hiện đại”.</p>

<h2>10. Dọn bảng outbox</h2>
<p>Bảng tăng liên tục sẽ làm index và backup phình to. Không xóa event ngay sau publish nếu còn cần audit hoặc replay, nhưng phải có retention rõ ràng. Với PostgreSQL, xóa theo batch nhỏ và theo dõi vacuum; quy mô lớn có thể partition theo thời gian để drop partition cũ.</p>
<pre><code>DELETE FROM outbox_events
WHERE id IN (
    SELECT id
    FROM outbox_events
    WHERE published_at &lt; now() - interval '14 days'
    ORDER BY published_at
    LIMIT 5000
);</code></pre>
<p>Replay không nên là cập nhật <code>published_at = NULL</code> hàng loạt mà không kiểm soát. Tạo công cụ replay có filter, audit, rate limit và xác nhận tác động duplicate ở consumer.</p>

<h2>11. Observability và cảnh báo</h2>
<ul>
<li>Tuổi của event pending cũ nhất.</li>
<li>Số event pending, retry và dead-letter theo loại.</li>
<li>Độ trễ từ <code>occurred_at</code> tới broker acknowledgement.</li>
<li>Publish throughput và error rate.</li>
<li>Consumer lag, duplicate count và processing failures.</li>
<li>Dung lượng bảng/index, vacuum và replication lag.</li>
</ul>
<p>Cảnh báo theo độ trễ kinh doanh, không chỉ theo việc worker còn chạy. Một worker “healthy” nhưng event cũ nhất đã chờ 30 phút vẫn là sự cố.</p>

<h2>12. Những lỗi thiết kế thường gặp</h2>
<ul>
<li>Insert outbox sau khi transaction nghiệp vụ đã commit.</li>
<li>Gọi broker trong database transaction rồi tưởng đã atomic.</li>
<li>Không có event ID duy nhất và consumer dedup.</li>
<li>Cho rằng broker bảo đảm exactly-once cho cả side effect bên ngoài.</li>
<li>Dùng timestamp làm thứ tự duy nhất giữa nhiều aggregate.</li>
<li>Payload chứa model nội bộ khổng lồ, secret hoặc dữ liệu dễ thay đổi.</li>
<li>Không có retention, dead-letter, replay tool và metric tuổi event.</li>
</ul>

<h2>Checklist production</h2>
<ol>
<li>Business row và outbox row được ghi trong đúng một local transaction.</li>
<li>Mỗi event có ID, type, version, aggregate ID và timestamp.</li>
<li>Relay retry có backoff, lease/lock an toàn và dead-letter.</li>
<li>Consumer deduplicate trong cùng transaction với business update.</li>
<li>Side effect ngoài database dùng idempotency key hoặc state machine.</li>
<li>Ordering được định nghĩa theo aggregate/partition, không hứa quá mức.</li>
<li>Schema event có chiến lược tương thích ngược.</li>
<li>Có dashboard, retention, replay có kiểm soát và runbook sự cố.</li>
</ol>

<h2>Kết luận</h2>
<p>Transactional Outbox biến dual write không an toàn thành hai bước có thể phục hồi: commit dữ liệu cùng ý định phát event, rồi relay event đã commit đến broker. Pattern này chấp nhận thực tế distributed system có retry và duplicate; độ tin cậy đến từ event ID, consumer idempotent, ordering có phạm vi, schema ổn định và khả năng quan sát đầy đủ.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html" target="_blank" rel="noopener noreferrer">AWS: Transactional outbox pattern</a></li><li><a href="https://debezium.io/documentation/reference/stable/transformations/outbox-event-router.html" target="_blank" rel="noopener noreferrer">Debezium: Outbox Event Router</a></li><li><a href="https://www.postgresql.org/docs/current/sql-select.html" target="_blank" rel="noopener noreferrer">PostgreSQL: SELECT and SKIP LOCKED</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Transactional Outbox in Practice: Reliable Events After a Transaction',
            'slug' => 'transactional-outbox-reliable-events-after-transaction',
            'image' => 'transactional-outbox-pattern.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Transactional Outbox for Reliable Event-Driven Systems',
            'meta_keywords' => 'transactional outbox, event driven, message broker, idempotency, PostgreSQL, Kafka, RabbitMQ, microservices',
            'meta_description' => 'Implement the Transactional Outbox pattern with atomic event writes, polling or CDC relays, retries, idempotent consumers, ordering, schema evolution, cleanup, and production monitoring.',
            'tags' => ['Transactional Outbox', 'Event-Driven', 'PostgreSQL', 'Kafka', 'RabbitMQ', 'Microservices', 'Backend'],
            'body' => <<<'HTML'
<p><strong>Creating an order commonly requires two writes: persist the order in a database and publish an <code>OrderCreated</code> event to a broker.</strong> If the database commits and publishing fails, inventory and email services never learn about the order. If publishing succeeds before the transaction rolls back, consumers process an order that does not exist. This is the <em>dual-write problem</em>.</p>
<p>Transactional Outbox solves it by storing business data and an event in the same database transaction. An independent relay reads committed events and sends them to Kafka, RabbitMQ, SQS, or another broker.</p>

<h2>Why is “commit, then publish” unsafe?</h2>
<pre><code>$order = $orders-&gt;create($input);
$database-&gt;commit();

$broker-&gt;publish('OrderCreated', $order);</code></pre>
<p>The process can crash after commit but before publish. Retrying the HTTP request is not a reliable repair when the client received a timeout or request idempotency is incomplete. Publishing before commit creates the opposite inconsistency.</p>
<p>Two independent systems cannot participate in a normal local database transaction. Distributed transactions have different costs and support constraints; an outbox is often a more practical choice for web architectures.</p>

<h2>Transactional Outbox flow</h2>
<ol>
<li>The application starts a database transaction.</li>
<li>It writes the business change, such as a new order.</li>
<li>It inserts an event into the outbox table in that same transaction.</li>
<li>Commit makes both visible, or rollback removes both.</li>
<li>A relay reads unpublished events and sends them to the broker.</li>
<li>The relay marks them published, or CDC records its read position.</li>
<li>Consumers process each event idempotently.</li>
</ol>
<blockquote>An outbox keeps the event atomic with the source transaction; it does not automatically make the entire pipeline exactly-once.</blockquote>

<h2>1. Design the outbox table</h2>
<pre><code>CREATE TABLE outbox_events (
    id uuid PRIMARY KEY,
    aggregate_type varchar(100) NOT NULL,
    aggregate_id varchar(100) NOT NULL,
    event_type varchar(150) NOT NULL,
    event_version integer NOT NULL DEFAULT 1,
    payload jsonb NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    available_at timestamptz NOT NULL DEFAULT now(),
    published_at timestamptz,
    attempts integer NOT NULL DEFAULT 0,
    last_error text
);

CREATE INDEX idx_outbox_pending
ON outbox_events (available_at, occurred_at)
WHERE published_at IS NULL;</code></pre>
<p>The unique <code>id</code> supports deduplication. <code>aggregate_id</code> usually becomes the message key so events for one order or customer enter the same partition. <code>event_version</code> versions the payload schema, not the application release.</p>
<p>Include the data consumers need at event time, while avoiding secrets and unnecessary record snapshots. An event is an immutable contract; do not modify historical payloads after insertion.</p>

<h2>2. Write business data and the event atomically</h2>
<pre><code>BEGIN;

INSERT INTO orders (id, customer_id, total, status)
VALUES (:order_id, :customer_id, :total, 'pending');

INSERT INTO outbox_events (
    id, aggregate_type, aggregate_id,
    event_type, event_version, payload
) VALUES (
    :event_id, 'order', :order_id,
    'OrderCreated', 1, :payload::jsonb
);

COMMIT;</code></pre>
<p>Do not call the broker inside this transaction. A network call increases lock duration, and the broker still does not join the database's atomic commit. Keep the transaction local, short, and reversible.</p>
<p>Generate the event ID before insertion. Validate payloads through schemas or tests before commit so an invalid event does not enter the outbox and block the relay.</p>

<h2>3. Relay events through polling</h2>
<p>A polling publisher is approachable for moderate volume without CDC infrastructure. Multiple workers can claim batches using <code>FOR UPDATE SKIP LOCKED</code>:</p>
<pre><code>BEGIN;

SELECT id, aggregate_type, aggregate_id,
       event_type, event_version, payload
FROM outbox_events
WHERE published_at IS NULL
  AND available_at &lt;= now()
ORDER BY occurred_at, id
FOR UPDATE SKIP LOCKED
LIMIT 100;</code></pre>
<p>Publishing while holding the transaction simplifies coordination but holds locks and connections longer. Claiming a batch with a lease and committing before publish scales better, but expired claims must recover events when a worker dies.</p>
<p><code>SKIP LOCKED</code> fits queue-like access. Its inconsistent view makes it unsuitable for general business queries.</p>

<h2>4. The duplicate-delivery window</h2>
<p>A relay can publish successfully and crash before setting <code>published_at</code>. The next run sends the event again. Marking it first creates a loss window if publishing then fails. Polling outboxes therefore usually provide <strong>at-least-once</strong> delivery.</p>
<p>Broker deduplication or idempotent producers do not remove every duplicate across an end-to-end distributed workflow. Carry the event ID in a header or envelope and make consumers safe to repeat.</p>

<h2>5. Idempotent consumers with an inbox</h2>
<pre><code>CREATE TABLE consumed_events (
    consumer_name varchar(100) NOT NULL,
    event_id uuid NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (consumer_name, event_id)
);</code></pre>
<p>A consumer starts a transaction, inserts the deduplication key, and updates business data. A uniqueness conflict means that event was already handled and can be acknowledged without repeating the side effect:</p>
<pre><code>BEGIN;

INSERT INTO consumed_events (consumer_name, event_id)
VALUES ('inventory-service', :event_id)
ON CONFLICT DO NOTHING;

-- Continue only when the insert created a row
UPDATE inventory
SET reserved = reserved + :quantity
WHERE product_id = :product_id;

COMMIT;</code></pre>
<p>Database transactions cannot cover email, payment, or third-party APIs. Use a provider idempotency key, another local outbox, or a persistent state machine so retries do not send or charge twice.</p>

<h2>6. Retries, backoff, and dead letters</h2>
<p>Retry temporary network failures with exponential backoff and jitter. Permanent payload failures should not spin forever. After a threshold, place the event in a failed/dead-letter state while preserving it for investigation.</p>
<pre><code>UPDATE outbox_events
SET attempts = attempts + 1,
    available_at = now() + (:delay_seconds * interval '1 second'),
    last_error = :safe_error
WHERE id = :event_id;</code></pre>
<p>Do not store credentials or sensitive responses in <code>last_error</code>. Monitor oldest-event age, pending count, publish latency, retry rate, and dead-letter count.</p>

<h2>7. Event ordering and aggregate versions</h2>
<p>Global ordering is expensive and rarely required. Ordering by aggregate, such as one <code>order_id</code>, is usually enough. Use <code>aggregate_id</code> as a partition key and maintain a monotonically increasing aggregate sequence/version.</p>
<p>Consumers should not rely solely on timestamps from different machines. They can store the last applied version, ignore stale events, and temporarily defer an event that arrives before a missing version. Define what happens when a gap persists.</p>

<h2>8. Evolve event schemas safely</h2>
<p>A consumer may lag behind the producer by days. Do not suddenly change field meanings or remove fields. Prefer additive evolution: optional fields, sensible defaults, and a new event version for breaking changes.</p>
<pre><code>{
  "event_id": "...",
  "event_type": "OrderCreated",
  "event_version": 1,
  "occurred_at": "2026-09-19T14:00:00+07:00",
  "aggregate_id": "order-123",
  "data": {
    "customer_id": "customer-9",
    "total": 1250000,
    "currency": "VND"
  }
}</code></pre>
<p>Use contract tests or a schema registry as the number of producers and consumers grows. Event names should describe facts that happened, not ambiguous commands.</p>

<h2>9. Polling or CDC?</h2>
<p><strong>Polling</strong> is simple and easy to debug for small and medium systems. You own workers, leases, retries, cleanup, and polling intervals.</p>
<p><strong>CDC</strong> reads the database change log. For example, Debezium can capture the outbox and route records to Kafka through its Outbox Event Router. The application only inserts events, while the connector publishes them. Throughput and latency can improve, but Kafka Connect, offsets, replication slots, and operations add complexity.</p>
<p>CDC does not eliminate consumer idempotency. Connectors or brokers can still redeliver under some failures. Choose according to scale and operating capability, not novelty.</p>

<h2>10. Clean up outbox data</h2>
<p>An ever-growing table inflates indexes and backups. Do not delete events immediately when audit or replay is required, but define retention. In PostgreSQL, delete in small batches and monitor vacuum; high-volume systems can partition by time and drop old partitions.</p>
<pre><code>DELETE FROM outbox_events
WHERE id IN (
    SELECT id
    FROM outbox_events
    WHERE published_at &lt; now() - interval '14 days'
    ORDER BY published_at
    LIMIT 5000
);</code></pre>
<p>Do not replay by mass-resetting <code>published_at</code> without safeguards. Build an audited, filtered, rate-limited replay tool and confirm duplicate impact downstream.</p>

<h2>11. Observability and alerts</h2>
<ul>
<li>Age of the oldest pending event.</li>
<li>Pending, retrying, and dead-letter counts by event type.</li>
<li>Latency from <code>occurred_at</code> to broker acknowledgement.</li>
<li>Publish throughput and error rate.</li>
<li>Consumer lag, duplicate count, and processing failures.</li>
<li>Table/index size, vacuum health, and replication lag.</li>
</ul>
<p>Alert on business delay, not merely process liveness. A healthy worker with an event waiting for 30 minutes is still an incident.</p>

<h2>12. Common design mistakes</h2>
<ul>
<li>Inserting the outbox row after the business transaction commits.</li>
<li>Calling the broker inside the transaction and assuming atomicity.</li>
<li>Omitting unique event IDs and consumer deduplication.</li>
<li>Assuming broker exactly-once covers external side effects.</li>
<li>Using timestamps as the only ordering mechanism.</li>
<li>Publishing oversized internal models, secrets, or unstable payloads.</li>
<li>Having no retention, dead-letter flow, replay tooling, or event-age metric.</li>
</ul>

<h2>Production checklist</h2>
<ol>
<li>Write the business row and outbox row in one local transaction.</li>
<li>Give each event an ID, type, version, aggregate ID, and timestamp.</li>
<li>Implement relay backoff, safe leases/locks, and dead letters.</li>
<li>Deduplicate in the same transaction as the consumer's business update.</li>
<li>Protect external side effects with idempotency keys or state machines.</li>
<li>Define ordering by aggregate or partition without overpromising.</li>
<li>Maintain a backward-compatible event-schema strategy.</li>
<li>Provide dashboards, retention, controlled replay, and an incident runbook.</li>
</ol>

<h2>Conclusion</h2>
<p>Transactional Outbox converts an unsafe dual write into two recoverable steps: commit data with the intent to publish, then relay the committed event to the broker. It embraces the reality of retries and duplicates in distributed systems. Reliability comes from event IDs, idempotent consumers, scoped ordering, stable schemas, and complete observability.</p>

<h2>References</h2>
<ul><li><a href="https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html" target="_blank" rel="noopener noreferrer">AWS: Transactional outbox pattern</a></li><li><a href="https://debezium.io/documentation/reference/stable/transformations/outbox-event-router.html" target="_blank" rel="noopener noreferrer">Debezium: Outbox Event Router</a></li><li><a href="https://www.postgresql.org/docs/current/sql-select.html" target="_blank" rel="noopener noreferrer">PostgreSQL: SELECT and SKIP LOCKED</a></li></ul>
HTML,
        ],
    ],
];
