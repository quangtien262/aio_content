<?php

return [
    'api-idempotency-key-design.html' => [
        'vi' => [
            'title' => 'Idempotency Key cho API: Chống tạo trùng đơn hàng và thanh toán',
            'slug' => 'idempotency-key-api-chong-trung-don-hang-thanh-toan',
            'image' => 'api-idempotency-key-design.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Thiết kế Idempotency Key an toàn cho API',
            'meta_keywords' => 'idempotency key, API idempotency, chống thanh toán trùng, retry API, duplicate request, PostgreSQL, distributed systems',
            'meta_description' => 'Thiết kế Idempotency Key production cho API POST/PATCH: request fingerprint, concurrency, lưu response, TTL, transaction, retry, lỗi và tích hợp payment/outbox.',
            'tags' => ['API', 'Idempotency', 'Backend', 'PostgreSQL', 'Payments', 'Distributed Systems', 'HTTP'],
            'body' => <<<'HTML'
<p><strong>Client gửi yêu cầu tạo đơn hàng, server xử lý thành công nhưng response bị mất trên đường truyền.</strong> Client chỉ thấy timeout và gửi lại. Nếu API tạo thêm một order hoặc charge thẻ lần hai, lỗi nằm ở thiết kế server chứ không phải ở hành vi retry hợp lý của client.</p>
<p>Idempotency Key cho phép nhiều lần thử của cùng một thao tác tạo ra một hiệu ứng nghiệp vụ duy nhất. Server nhận diện retry bằng khóa do client gửi, đối chiếu request và trả lại kết quả đã lưu thay vì thực hiện lại.</p>

<h2>Idempotency là gì?</h2>
<p>Một thao tác idempotent có cùng hiệu ứng sau một hay nhiều lần thực hiện giống nhau. HTTP định nghĩa GET, PUT và DELETE là idempotent về intended effect, còn POST thường không. Tuy vậy, method idempotent không có nghĩa implementation tự động an toàn: một DELETE gửi email mỗi lần gọi vẫn tạo side effect lặp.</p>
<p>Idempotency Key chủ yếu hữu ích cho các thao tác có side effect:</p>
<ul>
<li>Tạo order, booking, invoice hoặc payout.</li>
<li>Charge/refund payment.</li>
<li>Gửi lệnh provisioning tài nguyên.</li>
<li>POST/PATCH có thể bị client, proxy hoặc job queue retry.</li>
</ul>
<blockquote>Mục tiêu không phải ngăn mọi request lặp, mà bảo đảm các retry của cùng một ý định không tạo thêm hiệu ứng.</blockquote>

<h2>1. Hợp đồng giữa client và server</h2>
<p>Client tạo khóa ngẫu nhiên có entropy cao, thường là UUID, và giữ nguyên khóa cho mọi retry của cùng thao tác:</p>
<pre><code>POST /v1/orders HTTP/1.1
Authorization: Bearer ...
Idempotency-Key: 8e03978e-40d5-43e8-bc93-6894a57f9324
Content-Type: application/json

{
  "cart_id": "cart-9821",
  "shipping_address_id": "address-17"
}</code></pre>
<p>Một thao tác nghiệp vụ mới phải dùng key mới. Không dùng timestamp ngắn, số tăng dần hoặc hash payload đơn thuần làm key vì collision, khả năng đoán và hai ý định giống payload có thể là hai giao dịch hợp lệ.</p>
<p>Server phải công bố định dạng, độ dài tối đa, phạm vi áp dụng và thời gian giữ key. IETF đã thảo luận header <code>Idempotency-Key</code> trong một Internet-Draft; đây là tài liệu work in progress, không nên mô tả như tiêu chuẩn RFC đã hoàn tất.</p>

<h2>2. Scope khóa theo tenant và endpoint</h2>
<p>Không dùng idempotency key làm khóa toàn cục duy nhất. Hai khách hàng có thể vô tình tạo cùng UUID; cùng key trên hai endpoint cũng có thể là hai ý định khác nhau. Lookup key nên là composite:</p>
<pre><code>(tenant_id, operation, idempotency_key)</code></pre>
<p><code>tenant_id</code> phải lấy từ principal đã xác thực, không lấy từ body do client tùy ý gửi. <code>operation</code> là tên route ổn định như <code>orders.create</code>, không nhất thiết là URL chứa version hoặc query động.</p>

<h2>3. Schema lưu idempotency record</h2>
<pre><code>CREATE TABLE idempotency_keys (
    tenant_id uuid NOT NULL,
    operation varchar(100) NOT NULL,
    idempotency_key varchar(255) NOT NULL,
    request_hash char(64) NOT NULL,
    status varchar(20) NOT NULL,
    resource_type varchar(100),
    resource_id varchar(100),
    response_code integer,
    response_headers jsonb,
    response_body jsonb,
    locked_until timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz,
    expires_at timestamptz NOT NULL,
    PRIMARY KEY (tenant_id, operation, idempotency_key)
);

CREATE INDEX idx_idempotency_expiry
ON idempotency_keys (expires_at);</code></pre>
<p>Các trạng thái cơ bản là <code>processing</code>, <code>completed</code> và có thể <code>failed</code>. <code>request_hash</code> ngăn một key bị tái sử dụng cho payload khác. Tùy API, chỉ lưu resource ID rồi dựng lại response, hoặc lưu status/body đã trả để replay chính xác.</p>

<h2>4. Tạo request fingerprint ổn định</h2>
<p>Hash raw JSON không ổn định vì thứ tự field và whitespace có thể khác dù ý nghĩa giống nhau. Hãy canonicalize dữ liệu:</p>
<ol>
<li>Parse JSON và từ chối payload không hợp lệ.</li>
<li>Chuẩn hóa field được phép, kiểu dữ liệu và giá trị mặc định.</li>
<li>Sắp xếp key object theo quy tắc xác định.</li>
<li>Bao gồm method, operation và các tham số có ảnh hưởng.</li>
<li>Hash canonical representation bằng SHA-256.</li>
</ol>
<pre><code>fingerprint = SHA256(
    operation + "\n" + canonical_json(validated_input)
)</code></pre>
<p>Không hash authorization token hoặc header thay đổi vô nghĩa. File upload lớn có thể dùng checksum nội dung/metadata đã xác minh. Nếu cùng key nhưng fingerprint khác, trả lỗi conflict; tuyệt đối không replay response của request đầu cho payload thứ hai.</p>

<h2>5. Claim key bằng unique constraint</h2>
<p>Hai request cùng key có thể đến đúng một lúc. Check-then-insert trong application bị race. Hãy để unique constraint phân xử:</p>
<pre><code>INSERT INTO idempotency_keys (
    tenant_id, operation, idempotency_key,
    request_hash, status, locked_until, expires_at
) VALUES (
    :tenant_id, :operation, :key,
    :hash, 'processing', now() + interval '30 seconds',
    now() + interval '24 hours'
)
ON CONFLICT DO NOTHING;</code></pre>
<p>Request insert thành công là owner. Request còn lại đọc row hiện có:</p>
<ul>
<li>Hash khác: trả <code>409 Conflict</code> vì key bị dùng sai.</li>
<li><code>completed</code>: trả lại response đã lưu.</li>
<li><code>processing</code>: chờ ngắn/poll có giới hạn hoặc trả conflict/in-progress để client retry sau.</li>
<li>Lease hết hạn: chỉ takeover nếu quy trình có thể xác định an toàn thao tác trước chưa tạo side effect.</li>
</ul>

<h2>6. Transaction phải bao gồm business data</h2>
<p>Với thao tác chỉ ghi một database, tạo business record và hoàn tất idempotency row trong cùng transaction:</p>
<pre><code>BEGIN;

SELECT * FROM idempotency_keys
WHERE tenant_id = :tenant
  AND operation = 'orders.create'
  AND idempotency_key = :key
FOR UPDATE;

INSERT INTO orders (...) VALUES (...)
RETURNING id;

UPDATE idempotency_keys
SET status = 'completed',
    resource_type = 'order',
    resource_id = :order_id,
    response_code = 201,
    response_body = :body,
    completed_at = now()
WHERE tenant_id = :tenant
  AND operation = 'orders.create'
  AND idempotency_key = :key;

COMMIT;</code></pre>
<p>Nếu process crash trước commit, cả order và kết quả idempotency rollback. Nếu crash sau commit nhưng trước khi gửi response, retry đọc row completed và trả đúng order đã tạo.</p>

<h2>7. Lưu response hay tham chiếu resource?</h2>
<p><strong>Lưu full response</strong> replay chính xác, phù hợp response nhỏ và yêu cầu client nhận cùng status/body. Nhược điểm là tốn storage, giữ PII và response có thể chứa dữ liệu không nên lưu lâu.</p>
<p><strong>Lưu resource reference</strong> nhẹ hơn và dễ dọn, nhưng response dựng lại có thể khác nếu resource đã thay đổi. Một phương án lai lưu resource ID, status code và snapshot tối thiểu cần cho contract.</p>
<p>Không lưu <code>Set-Cookie</code>, token ngắn hạn hoặc header nhạy cảm để replay. Allowlist response header an toàn thay vì serialize tất cả.</p>

<h2>8. Validation error và server error có nên được cache?</h2>
<p>Policy phải rõ ràng:</p>
<ul>
<li><strong>Validation chưa bắt đầu execution:</strong> thường không claim/lưu key; client sửa payload và có thể retry.</li>
<li><strong>Business rejection đã quyết định:</strong> có thể lưu kết quả 4xx để retry nhận cùng quyết định.</li>
<li><strong>Lỗi 5xx trước side effect:</strong> thường cho retry thực thi lại.</li>
<li><strong>Lỗi sau side effect:</strong> phải lưu trạng thái đủ để reconcile, không được mù quáng chạy lại.</li>
</ul>
<p>Một số API như Stripe lưu status và body khi endpoint đã bắt đầu execution, kể cả lỗi 500. Không sao chép policy máy móc; chọn theo khả năng xác định side effect của hệ thống và mô tả cho client.</p>

<h2>9. Tích hợp payment provider</h2>
<p>Idempotency tại API của bạn và tại payment provider là hai lớp khác nhau. Tạo provider key ổn định từ payment attempt nội bộ, không tạo UUID mới mỗi lần worker retry:</p>
<pre><code>provider_key = "payment-attempt:" + payment_attempt_id</code></pre>
<p>Lưu provider request ID và trạng thái. Nếu timeout không rõ charge đã thành công, query provider bằng idempotency key/request ID trước khi gửi lệnh mới. Webhook cũng phải deduplicate bằng event ID của provider.</p>
<p>Không giữ database transaction mở trong lúc gọi payment network. Dùng state machine và transactional outbox để commit ý định, sau đó worker thực hiện side effect có idempotency key.</p>

<h2>10. Idempotency và Transactional Outbox bổ sung nhau</h2>
<p>Idempotency Key bảo vệ request vào hệ thống. Outbox bảo đảm event sau khi business transaction commit sẽ được phát đáng tin cậy. Trong transaction tạo order, có thể đồng thời:</p>
<ol>
<li>Khóa/claim idempotency key.</li>
<li>Tạo order.</li>
<li>Insert <code>OrderCreated</code> vào outbox.</li>
<li>Lưu response idempotent.</li>
<li>Commit tất cả.</li>
</ol>
<p>Consumer event vẫn cần dedup riêng vì broker có thể giao lại message. Một idempotency key không tự truyền guarantee xuyên qua mọi service.</p>

<h2>11. TTL và tái sử dụng key</h2>
<p>TTL phải dài hơn cửa sổ retry thực tế của client, queue, mobile offline và webhook. 24 giờ có thể đủ cho API tương tác, nhưng payout hoặc provisioning dài ngày có thể cần lâu hơn. Công bố TTL trong tài liệu.</p>
<p>Khi record hết hạn và bị xóa, dùng lại key có thể tạo thao tác mới. Vì vậy client không nên tái chế key. Với giao dịch giá trị cao, unique business identifier như <code>merchant_order_id</code> nên có constraint riêng và retention dài hơn idempotency cache.</p>
<pre><code>DELETE FROM idempotency_keys
WHERE expires_at &lt; now()
  AND status &lt;&gt; 'processing'
LIMIT 5000;</code></pre>
<p>Dọn theo batch/partition và không xóa row processing chưa được reconcile.</p>

<h2>12. Bảo mật và abuse</h2>
<ul>
<li>Giới hạn độ dài và format key để tránh storage abuse.</li>
<li>Scope theo authenticated principal để ngăn dò key tenant khác.</li>
<li>Không trả response cache trước khi kiểm tra authorization hiện tại.</li>
<li>Rate-limit số key mới; attacker có thể gửi UUID mới liên tục.</li>
<li>Mã hóa hoặc giảm PII trong response đã lưu.</li>
<li>Không log body/payment data chỉ để debug idempotency.</li>
</ul>
<p>Key không phải credential. Biết idempotency key không được phép cung cấp quyền truy cập resource.</p>

<h2>13. Observability</h2>
<ul>
<li>Tỷ lệ request mới, replay, conflict và in-progress.</li>
<li>Thời gian từ processing đến completed.</li>
<li>Số lease hết hạn và thao tác cần reconcile.</li>
<li>Dung lượng bảng, tốc độ tạo/xóa và tuổi record.</li>
<li>Duplicate bị chặn theo operation, không theo raw key.</li>
<li>Provider timeout và kết quả query lại.</li>
</ul>
<p>Gắn request ID và trace ID riêng với idempotency key. Request ID xác định từng attempt; idempotency key nhóm nhiều attempt của cùng logical operation.</p>

<h2>14. Kịch bản kiểm thử bắt buộc</h2>
<ol>
<li>Hai request giống nhau chạy đồng thời chỉ tạo một resource.</li>
<li>Cùng key, payload khác trả conflict.</li>
<li>Crash trước commit không để lại business record.</li>
<li>Crash sau commit trước response được replay đúng.</li>
<li>Timeout từ payment provider được reconcile không charge lại.</li>
<li>Retry sau TTL hoạt động đúng policy đã công bố.</li>
<li>Authorization thay đổi không làm lộ response cũ.</li>
<li>Cleanup không xóa request đang processing.</li>
</ol>

<h2>Checklist production</h2>
<ul>
<li>Client sinh key entropy cao và giữ nguyên qua retry.</li>
<li>Server scope theo tenant + operation + key.</li>
<li>Payload canonical hóa và fingerprint được so sánh.</li>
<li>Unique constraint xử lý race, không dùng check-then-insert.</li>
<li>Business data và kết quả idempotency commit nguyên tử khi có thể.</li>
<li>Policy cho 4xx, 5xx, in-progress và lease expiry được định nghĩa.</li>
<li>External side effect có provider key/state machine riêng.</li>
<li>TTL, cleanup, bảo mật và metric được vận hành rõ ràng.</li>
</ul>

<h2>Kết luận</h2>
<p>Idempotency Key biến retry từ rủi ro thành một phần bình thường của API fault-tolerant. Thiết kế đúng cần nhiều hơn bảng cache: khóa phải có scope, request phải có fingerprint, race phải được giải bằng unique constraint, business effect phải gắn với transaction và side effect ngoài database cần state machine riêng. Idempotency không hứa “request chỉ chạy đúng một lần”; nó bảo đảm nhiều attempt của cùng ý định chỉ tạo một kết quả nghiệp vụ.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://docs.stripe.com/api/idempotent_requests" target="_blank" rel="noopener noreferrer">Stripe: Idempotent requests</a></li><li><a href="https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/" target="_blank" rel="noopener noreferrer">IETF Internet-Draft: Idempotency-Key HTTP Header Field</a></li><li><a href="https://www.rfc-editor.org/rfc/rfc9110.html" target="_blank" rel="noopener noreferrer">RFC 9110: HTTP Semantics</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'API Idempotency Keys: Prevent Duplicate Orders and Payments',
            'slug' => 'api-idempotency-keys-prevent-duplicate-orders-payments',
            'image' => 'api-idempotency-key-design.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Designing Safe Idempotency Keys for APIs',
            'meta_keywords' => 'idempotency key, API idempotency, duplicate payment prevention, API retry, duplicate request, PostgreSQL, distributed systems',
            'meta_description' => 'Design production Idempotency Keys for POST and PATCH APIs with request fingerprints, concurrency control, response storage, TTL, transactions, retries, payments, and outbox integration.',
            'tags' => ['API', 'Idempotency', 'Backend', 'PostgreSQL', 'Payments', 'Distributed Systems', 'HTTP'],
            'body' => <<<'HTML'
<p><strong>A client sends an order request, the server completes it, but the response is lost in transit.</strong> The client sees only a timeout and retries. If the API creates another order or charges the card twice, the fault lies in server design, not in the client's reasonable retry behavior.</p>
<p>An Idempotency Key lets multiple attempts of one operation produce one business effect. The server recognizes a retry using a client-supplied key, compares the request, and returns the stored result instead of executing again.</p>

<h2>What is idempotency?</h2>
<p>An idempotent operation has the same intended effect after one or many identical executions. HTTP defines GET, PUT, and DELETE as idempotent in semantics, while POST commonly is not. An idempotent method does not make an implementation automatically safe: a DELETE endpoint that emails on every call still repeats a side effect.</p>
<p>Idempotency Keys are especially useful when creating orders, bookings, invoices, payouts, charges, refunds, infrastructure resources, and any POST/PATCH request that a client, proxy, or queue may retry.</p>
<blockquote>The goal is not to block every duplicate request; it is to ensure retries of the same intent do not create additional effects.</blockquote>

<h2>1. The client–server contract</h2>
<p>The client generates a high-entropy random key, commonly a UUID, and keeps it unchanged across retries:</p>
<pre><code>POST /v1/orders HTTP/1.1
Authorization: Bearer ...
Idempotency-Key: 8e03978e-40d5-43e8-bc93-6894a57f9324
Content-Type: application/json

{
  "cart_id": "cart-9821",
  "shipping_address_id": "address-17"
}</code></pre>
<p>A new business operation needs a new key. Avoid short timestamps, counters, or payload hashes as keys: collisions and predictability are risks, and two valid intents may have identical payloads.</p>
<p>The server must document format, maximum length, supported operations, and retention. The IETF has discussed <code>Idempotency-Key</code> in an Internet-Draft; it remains work in progress rather than a completed RFC.</p>

<h2>2. Scope keys by tenant and operation</h2>
<p>Do not use the client key as a globally unique lookup by itself. Different customers can generate the same UUID, and the same key on separate endpoints may represent separate intents. Use a composite identity:</p>
<pre><code>(tenant_id, operation, idempotency_key)</code></pre>
<p>Derive <code>tenant_id</code> from the authenticated principal, not an arbitrary request field. Use a stable operation name such as <code>orders.create</code> rather than a dynamic URL.</p>

<h2>3. Store an idempotency record</h2>
<pre><code>CREATE TABLE idempotency_keys (
    tenant_id uuid NOT NULL,
    operation varchar(100) NOT NULL,
    idempotency_key varchar(255) NOT NULL,
    request_hash char(64) NOT NULL,
    status varchar(20) NOT NULL,
    resource_type varchar(100),
    resource_id varchar(100),
    response_code integer,
    response_headers jsonb,
    response_body jsonb,
    locked_until timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz,
    expires_at timestamptz NOT NULL,
    PRIMARY KEY (tenant_id, operation, idempotency_key)
);

CREATE INDEX idx_idempotency_expiry
ON idempotency_keys (expires_at);</code></pre>
<p>Common states are <code>processing</code>, <code>completed</code>, and optionally <code>failed</code>. The request hash prevents reuse with a different payload. Store either the resource ID for reconstruction or the status and body for exact replay.</p>

<h2>4. Create a stable request fingerprint</h2>
<p>Hashing raw JSON is unstable because field order and whitespace can differ without changing meaning. Parse and validate JSON, normalize allowed fields and defaults, sort object keys deterministically, include the method/operation and meaningful parameters, then hash the canonical representation with SHA-256:</p>
<pre><code>fingerprint = SHA256(
    operation + "\n" + canonical_json(validated_input)
)</code></pre>
<p>Do not hash authorization tokens or irrelevant volatile headers. For large uploads, use a verified content checksum and metadata. If a key matches but the fingerprint differs, return a conflict; never replay the first response for the second payload.</p>

<h2>5. Claim the key with a uniqueness constraint</h2>
<p>Two identical requests may arrive concurrently. Application-level check-then-insert races; let the database constraint arbitrate:</p>
<pre><code>INSERT INTO idempotency_keys (
    tenant_id, operation, idempotency_key,
    request_hash, status, locked_until, expires_at
) VALUES (
    :tenant_id, :operation, :key,
    :hash, 'processing', now() + interval '30 seconds',
    now() + interval '24 hours'
)
ON CONFLICT DO NOTHING;</code></pre>
<p>The successful insert owns execution. A losing request reads the existing row. A different hash receives <code>409 Conflict</code>; completed returns the stored result; processing either waits briefly or returns an in-progress conflict. Take over an expired lease only when the previous effect can be determined safely.</p>

<h2>6. Keep business data in the transaction</h2>
<p>For a single-database operation, create the business record and complete the idempotency row atomically:</p>
<pre><code>BEGIN;

SELECT * FROM idempotency_keys
WHERE tenant_id = :tenant
  AND operation = 'orders.create'
  AND idempotency_key = :key
FOR UPDATE;

INSERT INTO orders (...) VALUES (...)
RETURNING id;

UPDATE idempotency_keys
SET status = 'completed',
    resource_type = 'order',
    resource_id = :order_id,
    response_code = 201,
    response_body = :body,
    completed_at = now()
WHERE tenant_id = :tenant
  AND operation = 'orders.create'
  AND idempotency_key = :key;

COMMIT;</code></pre>
<p>A crash before commit rolls back both records. A crash after commit but before response delivery leaves a completed row that can replay the created order.</p>

<h2>7. Full response or resource reference?</h2>
<p><strong>Full response storage</strong> replays the exact status and body but consumes storage and may retain personal data. <strong>Resource references</strong> are smaller, yet reconstructed responses may differ after the resource changes. A hybrid stores the ID, status, and minimum response snapshot required by the contract.</p>
<p>Never replay sensitive or short-lived headers such as <code>Set-Cookie</code>. Allowlist safe headers instead of serializing everything.</p>

<h2>8. Should errors be cached?</h2>
<ul>
<li><strong>Validation before execution:</strong> usually do not claim or persist the key.</li>
<li><strong>Final business rejection:</strong> storing a 4xx decision can produce consistent retries.</li>
<li><strong>5xx before any effect:</strong> execution can generally be retried.</li>
<li><strong>Failure after an external effect:</strong> persist enough state to reconcile rather than blindly repeat.</li>
</ul>
<p>Some APIs, including Stripe, store status and body once endpoint execution begins, including 500 responses. Do not copy a policy mechanically; base it on when your system can prove whether effects occurred and document it for clients.</p>

<h2>9. Integrate payment providers</h2>
<p>Your API's key and the provider's key protect different boundaries. Derive a stable provider key from an internal payment attempt rather than creating a new UUID on each worker retry:</p>
<pre><code>provider_key = "payment-attempt:" + payment_attempt_id</code></pre>
<p>Persist provider request IDs and state. When a timeout leaves charge status unknown, query the provider by key or request ID before issuing another command. Deduplicate provider webhooks by their event IDs.</p>
<p>Do not hold a database transaction open during a payment network call. Commit intent through a state machine and transactional outbox, then let a worker perform the idempotent external effect.</p>

<h2>10. Idempotency and Transactional Outbox complement each other</h2>
<p>Idempotency protects inbound API retries. Outbox reliably publishes events after commit. One order transaction can claim the key, create the order, insert <code>OrderCreated</code>, store the idempotent response, and commit everything together.</p>
<p>Event consumers still require their own deduplication because brokers may redeliver. One idempotency key does not automatically propagate guarantees across every service.</p>

<h2>11. TTL and key reuse</h2>
<p>Retention must exceed the actual retry window for clients, queues, offline mobile apps, and webhooks. Twenty-four hours may fit interactive APIs; payouts or long provisioning may need more. Publish the retention policy.</p>
<p>After deletion, reusing a key can create a new operation, so clients should never recycle keys. High-value transactions should also have a long-lived unique business identifier such as <code>merchant_order_id</code>.</p>
<pre><code>DELETE FROM idempotency_keys
WHERE expires_at &lt; now()
  AND status &lt;&gt; 'processing'
LIMIT 5000;</code></pre>
<p>Clean in batches or partitions and never delete unreconciled processing rows.</p>

<h2>12. Security and abuse</h2>
<ul>
<li>Limit key length and format to prevent storage abuse.</li>
<li>Scope lookups by authenticated principal.</li>
<li>Recheck current authorization before returning a cached response.</li>
<li>Rate-limit new keys because attackers can send endless UUIDs.</li>
<li>Minimize or encrypt personal data in stored responses.</li>
<li>Do not log payment bodies merely for idempotency debugging.</li>
</ul>
<p>A key is not a credential. Knowing it must never grant access to a resource.</p>

<h2>13. Observability</h2>
<ul>
<li>New, replayed, conflicting, and in-progress request ratios.</li>
<li>Processing-to-completed latency.</li>
<li>Expired leases and operations needing reconciliation.</li>
<li>Table size, insertion/deletion rate, and record age.</li>
<li>Duplicates prevented by operation, not raw key.</li>
<li>Provider timeouts and lookup outcomes.</li>
</ul>
<p>Keep request IDs and trace IDs separate. A request ID identifies one attempt; an idempotency key groups attempts of one logical operation.</p>

<h2>14. Required test scenarios</h2>
<ol>
<li>Two concurrent identical requests create one resource.</li>
<li>The same key with a different payload returns conflict.</li>
<li>A crash before commit leaves no business record.</li>
<li>A crash after commit but before response replays correctly.</li>
<li>A payment-provider timeout reconciles without a second charge.</li>
<li>A retry after TTL follows the documented policy.</li>
<li>Authorization changes do not expose an old response.</li>
<li>Cleanup never removes a processing request.</li>
</ol>

<h2>Production checklist</h2>
<ul>
<li>Clients generate high-entropy keys and preserve them across retries.</li>
<li>The server scopes by tenant, operation, and key.</li>
<li>Canonical payload fingerprints are compared.</li>
<li>A uniqueness constraint handles races.</li>
<li>Business data and idempotency results commit atomically where possible.</li>
<li>4xx, 5xx, in-progress, and lease-expiry policies are explicit.</li>
<li>External effects have separate provider keys and state machines.</li>
<li>TTL, cleanup, security, and metrics are operationalized.</li>
</ul>

<h2>Conclusion</h2>
<p>Idempotency Keys turn retries from a risk into a normal part of fault-tolerant APIs. Correct design requires more than a cache: scope the key, fingerprint the request, resolve races with uniqueness constraints, connect business effects to transactions, and model external side effects separately. Idempotency does not promise that code executes exactly once; it ensures multiple attempts of one intent produce one business outcome.</p>

<h2>References</h2>
<ul><li><a href="https://docs.stripe.com/api/idempotent_requests" target="_blank" rel="noopener noreferrer">Stripe: Idempotent requests</a></li><li><a href="https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/" target="_blank" rel="noopener noreferrer">IETF Internet-Draft: Idempotency-Key HTTP Header Field</a></li><li><a href="https://www.rfc-editor.org/rfc/rfc9110.html" target="_blank" rel="noopener noreferrer">RFC 9110: HTTP Semantics</a></li></ul>
HTML,
        ],
    ],
];
