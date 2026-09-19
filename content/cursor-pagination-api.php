<?php

return [
    'cursor-pagination-api.html' => [
        'vi' => [
            'title' => 'Cursor Pagination cho API: Phân trang ổn định khi dữ liệu liên tục thay đổi',
            'slug' => 'cursor-pagination-api-on-dinh-hieu-nang',
            'image' => 'cursor-pagination-api.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Thiết kế Cursor Pagination ổn định cho API',
            'meta_keywords' => 'cursor pagination, keyset pagination, API pagination, PostgreSQL, REST API, database index, backend performance',
            'meta_description' => 'Thiết kế cursor pagination production cho REST API: thứ tự toàn phần, keyset query, index, opaque cursor, next/previous, filter, dữ liệu thay đổi và kiểm thử.',
            'tags' => ['API', 'Pagination', 'PostgreSQL', 'Backend', 'REST API', 'Database', 'Performance'],
            'body' => <<<'HTML'
<p><strong><code>OFFSET 100000</code> trông vô hại, nhưng database vẫn phải tìm và bỏ qua một lượng lớn record trước khi trả vài chục dòng.</strong> Trong lúc người dùng chuyển trang, dữ liệu mới còn có thể được chèn vào đầu danh sách, khiến một item xuất hiện hai lần hoặc biến mất khỏi hành trình đọc.</p>
<p>Cursor pagination, còn gọi là keyset pagination, không hỏi “bỏ qua bao nhiêu dòng”. Nó hỏi “tiếp tục sau vị trí ổn định nào trong thứ tự đã chọn”. Cách tiếp cận này phù hợp với feed, lịch sử giao dịch, audit log, danh sách đơn hàng và các API có dữ liệu lớn hoặc cập nhật thường xuyên.</p>

<h2>Offset và cursor khác nhau ở đâu?</h2>
<table><thead><tr><th>Tiêu chí</th><th>Offset pagination</th><th>Cursor pagination</th></tr></thead><tbody>
<tr><td>Request</td><td><code>?page=200&amp;limit=50</code></td><td><code>?after=opaque-token&amp;limit=50</code></td></tr>
<tr><td>Truy vấn</td><td>Bỏ qua N dòng</td><td>Đi tiếp từ khóa sắp xếp</td></tr>
<tr><td>Trang sâu</td><td>Có thể chậm dần</td><td>Thường giữ chi phí ổn định với index phù hợp</td></tr>
<tr><td>Dữ liệu thay đổi</td><td>Dễ trùng hoặc bỏ sót</td><td>Ổn định hơn theo thứ tự khóa</td></tr>
<tr><td>Nhảy đến trang 73</td><td>Dễ</td><td>Không phải mục tiêu chính</td></tr>
</tbody></table>
<p>Offset vẫn hợp lý cho tập dữ liệu nhỏ, trang quản trị cần nhảy theo số trang hoặc báo cáo tĩnh. Cursor không phải lựa chọn mặc định cho mọi màn hình; nó đổi khả năng nhảy ngẫu nhiên lấy hiệu năng và tính liên tục.</p>

<h2>1. Bắt đầu từ một thứ tự toàn phần</h2>
<p>Cursor chỉ đúng khi mọi record có vị trí xác định duy nhất. Sắp xếp theo <code>created_at DESC</code> chưa đủ vì nhiều row có thể cùng timestamp. Cần thêm tie-breaker duy nhất, thường là primary key:</p>
<pre><code>ORDER BY created_at DESC, id DESC</code></pre>
<p>Cặp <code>(created_at, id)</code> tạo thứ tự toàn phần nếu <code>id</code> duy nhất và cả hai giá trị không đổi trong vòng đời record. Không dùng một field có thể bị sửa như <code>updated_at</code> nếu việc record đổi vị trí giữa lúc phân trang là không mong muốn.</p>
<p>SQL không bảo đảm thứ tự khi thiếu <code>ORDER BY</code>. Ngay cả khi kết quả “có vẻ” đi theo primary key trong môi trường phát triển, query plan hoặc dữ liệu production có thể tạo thứ tự khác.</p>

<h2>2. Viết keyset query đúng với chiều sắp xếp</h2>
<p>Trang đầu lấy các order mới nhất:</p>
<pre><code>SELECT id, created_at, status, total
FROM orders
WHERE tenant_id = :tenant_id
ORDER BY created_at DESC, id DESC
LIMIT :limit_plus_one;</code></pre>
<p>Nếu record cuối trang có <code>created_at = 2026-09-19T08:30:00Z</code> và <code>id = 9102</code>, trang kế tiếp dùng phép so sánh cùng bộ khóa:</p>
<pre><code>SELECT id, created_at, status, total
FROM orders
WHERE tenant_id = :tenant_id
  AND (created_at, id) &lt; (:cursor_created_at, :cursor_id)
ORDER BY created_at DESC, id DESC
LIMIT :limit_plus_one;</code></pre>
<p>Trong PostgreSQL, row constructor được so sánh từ trái sang phải. Dạng tương đương là:</p>
<pre><code>created_at &lt; :cursor_created_at
OR (created_at = :cursor_created_at AND id &lt; :cursor_id)</code></pre>
<p>Toán tử phải đổi theo chiều sort. Trộn <code>ASC</code>/<code>DESC</code>, xử lý <code>NULL</code> hoặc sort theo biểu thức cần được thiết kế và test riêng; không áp dụng máy móc một dấu <code>&lt;</code>.</p>

<h2>3. Tạo index khớp filter và order</h2>
<p>Query nhanh khi database có thể đi theo B-tree index thay vì sort hoặc quét phần lớn bảng:</p>
<pre><code>CREATE INDEX orders_tenant_created_id_idx
ON orders (tenant_id, created_at DESC, id DESC);</code></pre>
<p><code>tenant_id</code> đứng trước vì mọi query đều lọc theo tenant; hai cột còn lại khớp thứ tự cursor. Nếu API luôn lọc thêm <code>status</code>, cân nhắc index khác dựa trên selectivity và workload thực tế, không ghép mọi filter vào một index khổng lồ.</p>
<p>Dùng <code>EXPLAIN (ANALYZE, BUFFERS)</code> với dữ liệu gần production. Một index đúng trên giấy vẫn có thể không được chọn nếu query trả phần lớn bảng, thống kê cũ hoặc kiểu tham số không khớp.</p>

<h2>4. Cursor phải opaque nhưng vẫn kiểm chứng được</h2>
<p>Client không cần biết cursor chứa timestamp và ID. Server có thể serialize payload versioned rồi base64url:</p>
<pre><code>{
  "v": 1,
  "created_at": "2026-09-19T08:30:00.000000Z",
  "id": 9102,
  "filter": "sha256:..."
}</code></pre>
<p>Base64 chỉ là encoding, không phải bảo mật. Nếu việc sửa cursor có thể vượt ranh giới quyền truy cập hoặc tạo query bất thường, ký payload bằng HMAC và xác minh signature bằng so sánh constant-time. Dữ liệu nhạy cảm không nên nằm trong cursor; nếu cần bí mật, dùng authenticated encryption hoặc lưu state phía server.</p>
<p>Field <code>v</code> cho phép đổi format sau này. Decoder phải giới hạn kích thước token, kiểm tra kiểu, ngày hợp lệ và từ chối version không hỗ trợ bằng lỗi 400 rõ ràng, thay vì để parser hoặc database phát sinh 500.</p>

<h2>5. Ràng buộc cursor với filter và scope</h2>
<p>Một cursor sinh cho <code>status=paid</code> không nên được dùng lại cho <code>status=pending</code>. Tương tự, cursor của tenant A tuyệt đối không được mở dữ liệu tenant B.</p>
<p>Có hai hướng:</p>
<ul>
<li>Đưa filter đã chuẩn hóa và scope vào payload có chữ ký.</li>
<li>Lưu hash của filter trong cursor rồi so với request hiện tại.</li>
</ul>
<p>Tenant/user scope vẫn phải lấy từ principal đã xác thực và luôn xuất hiện trong <code>WHERE</code>. Chữ ký cursor bảo vệ tính toàn vẹn, không thay thế authorization.</p>

<h2>6. Thiết kế response cho client dễ dùng</h2>
<pre><code>{
  "data": [ ... ],
  "page": {
    "next_cursor": "eyJ2IjoxLC4uLn0.signature",
    "previous_cursor": null,
    "has_more": true
  }
}</code></pre>
<p>Lấy <code>limit + 1</code> record để biết còn trang tiếp theo hay không, sau đó chỉ trả <code>limit</code>. Cách này tránh chạy <code>COUNT(*)</code> cho mỗi request. Total count có thể đắt và nhanh lỗi thời; chỉ cung cấp nếu trải nghiệm sản phẩm thật sự cần, có thể bằng endpoint hoặc số liệu xấp xỉ riêng.</p>
<p>Giới hạn <code>limit</code> ở server, ví dụ mặc định 25 và tối đa 100. Không tin giá trị client vì page size cực lớn có thể gây tải database, memory và serialization.</p>

<h2>7. Hỗ trợ trang trước mà không đảo lộn dữ liệu</h2>
<p>Để đi ngược, cursor thường chứa khóa của record đầu trang hiện tại. Với danh sách hiển thị giảm dần, server tìm các record lớn hơn cursor theo chiều tăng dần, giới hạn N, rồi đảo mảng trước khi trả để giao diện vẫn giữ thứ tự giảm dần:</p>
<pre><code>SELECT id, created_at, status, total
FROM orders
WHERE tenant_id = :tenant_id
  AND (created_at, id) &gt; (:cursor_created_at, :cursor_id)
ORDER BY created_at ASC, id ASC
LIMIT :limit_plus_one;</code></pre>
<p>Không dùng chung một token mơ hồ cho cả hai chiều. Có thể phát hành <code>next_cursor</code> và <code>previous_cursor</code> riêng, hoặc encode direction rồi validate chặt.</p>

<h2>8. Điều gì xảy ra khi dữ liệu thay đổi?</h2>
<p>Cursor pagination ổn định hơn offset nhưng không tự tạo snapshot:</p>
<ul>
<li><strong>Record mới ở đầu feed:</strong> người đang đi về phía cũ thường không bị đẩy lệch; họ chỉ chưa thấy record mới.</li>
<li><strong>Record bị xóa:</strong> hành trình tiếp tục từ khóa, không cần bù vị trí offset.</li>
<li><strong>Sort key bị sửa:</strong> record có thể xuất hiện lại hoặc bị bỏ qua vì nó chuyển vị trí.</li>
<li><strong>Filter-relevant field đổi:</strong> record có thể vào hoặc rời tập kết quả giữa các request.</li>
</ul>
<p>Với feed, semantics “dữ liệu đang sống” thường chấp nhận được. Với export hoặc quy trình phải đọc đúng một tập bất biến, thêm snapshot boundary như <code>created_at &lt;= :as_of</code>, dùng transaction snapshot phù hợp hoặc materialize một export job. Cursor không thay thế isolation.</p>

<h2>9. Timestamp, ID và NULL là các bẫy phổ biến</h2>
<ul>
<li>Giữ độ chính xác timestamp nhất quán khi encode/decode; làm tròn microsecond có thể bỏ sót record.</li>
<li>Không giả định UUID ngẫu nhiên phản ánh thời gian; dùng nó làm tie-breaker, không dùng thay sort time.</li>
<li>Nếu sort column có <code>NULL</code>, định nghĩa rõ <code>NULLS FIRST/LAST</code> và predicate tương ứng, hoặc dùng cột non-null đã chuẩn hóa.</li>
<li>Collation của text có thể thay đổi thứ tự theo locale/version; cursor sort theo text cần contract và migration thận trọng.</li>
<li>Không dùng floating-point làm khóa ổn định nếu giá trị được tính lại.</li>
</ul>

<h2>10. Error contract và khả năng quan sát</h2>
<p>Cursor hết hạn, sai signature, không đúng filter hoặc không hỗ trợ version nên trả 400 với mã lỗi máy đọc được. Không trả stack trace hay chi tiết chữ ký. Nếu cursor hợp lệ nhưng record neo đã bị xóa, keyset query vẫn có thể tiếp tục vì nó dùng giá trị khóa, không cần row còn tồn tại.</p>
<p>Theo dõi latency theo page depth ước tính, số row đọc/trả, tỷ lệ cursor invalid, page size, query plan và số request chạm giới hạn. Log payload cursor đã giải mã có thể chứa định danh; ưu tiên log version, direction và hash rút gọn thay vì toàn bộ token.</p>

<h2>11. Kịch bản kiểm thử bắt buộc</h2>
<ol>
<li>Nhiều record cùng timestamp vẫn xuất hiện đúng một lần nhờ tie-breaker.</li>
<li>Chèn record mới giữa hai request không làm lặp item ở trang kế.</li>
<li>Xóa record cuối trang trước không làm query tiếp theo lỗi.</li>
<li>Cursor bị sửa một byte bị từ chối.</li>
<li>Cursor của filter hoặc tenant khác không được chấp nhận.</li>
<li>Đi next rồi previous trả lại cùng cửa sổ và thứ tự.</li>
<li>Limit bằng 0, âm hoặc vượt max được normalize/từ chối đúng contract.</li>
<li>Timestamp giữ nguyên precision qua vòng encode/decode.</li>
<li>Query trang sâu vẫn dùng index mong đợi trên dữ liệu lớn.</li>
</ol>

<h2>Checklist production</h2>
<ul>
<li>Order có tie-breaker duy nhất và sort key ổn định.</li>
<li>Keyset predicate khớp chính xác với order và hướng.</li>
<li>Composite index bắt đầu bằng scope/filter quan trọng rồi đến sort keys.</li>
<li>Cursor opaque, versioned, giới hạn kích thước và được ký khi cần.</li>
<li>Authorization luôn áp dụng độc lập với cursor.</li>
<li>Response dùng <code>limit + 1</code>, page size có giới hạn.</li>
<li>Semantics khi insert, update, delete và snapshot được ghi rõ.</li>
<li>Next/previous, filter mismatch và cursor lỗi đều có test.</li>
</ul>

<h2>Kết luận</h2>
<p>Cursor pagination không chỉ là base64 hóa ID cuối trang. Thiết kế production bắt đầu từ thứ tự toàn phần, predicate đúng chiều và index phù hợp; sau đó mới đến token, chữ ký, filter, điều hướng hai chiều và semantics khi dữ liệu thay đổi. Làm đúng, API có thể đi qua hàng triệu record với chi phí ổn định hơn và trải nghiệm ít trùng/bỏ sót hơn so với offset.</p>

<h2>Tài liệu tham khảo</h2>
<ul>
<li><a href="https://www.postgresql.org/docs/current/queries-limit.html" target="_blank" rel="noopener noreferrer">PostgreSQL: LIMIT and OFFSET</a></li>
<li><a href="https://www.postgresql.org/docs/current/indexes-ordering.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Indexes and ORDER BY</a></li>
<li><a href="https://www.postgresql.org/docs/current/functions-comparisons.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Row Constructor Comparison</a></li>
</ul>
HTML,
        ],
        'en' => [
            'title' => 'API Cursor Pagination: Stable Pages While Data Keeps Changing',
            'slug' => 'api-cursor-pagination-stable-efficient-pages',
            'image' => 'cursor-pagination-api.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Designing Stable API Cursor Pagination',
            'meta_keywords' => 'cursor pagination, keyset pagination, API pagination, PostgreSQL, REST API, database index, backend performance',
            'meta_description' => 'Design production cursor pagination for REST APIs with total ordering, keyset queries, indexes, opaque cursors, bidirectional navigation, filters, mutations, and tests.',
            'tags' => ['API', 'Pagination', 'PostgreSQL', 'Backend', 'REST API', 'Database', 'Performance'],
            'body' => <<<'HTML'
<p><strong><code>OFFSET 100000</code> looks harmless, but the database may still have to find and discard a large number of records before returning a few dozen.</strong> While a user moves between pages, new data can also arrive at the front, causing an item to appear twice or disappear from the reading journey.</p>
<p>Cursor pagination, also called keyset pagination, does not ask how many rows to skip. It asks where to continue in a stable ordering. This model fits feeds, transaction histories, audit logs, order lists, and APIs whose data is large or changes frequently.</p>

<h2>How do offset and cursor pagination differ?</h2>
<table><thead><tr><th>Criterion</th><th>Offset pagination</th><th>Cursor pagination</th></tr></thead><tbody>
<tr><td>Request</td><td><code>?page=200&amp;limit=50</code></td><td><code>?after=opaque-token&amp;limit=50</code></td></tr>
<tr><td>Query model</td><td>Skip N rows</td><td>Continue from sort keys</td></tr>
<tr><td>Deep pages</td><td>Can get progressively slower</td><td>Usually stable with a matching index</td></tr>
<tr><td>Changing data</td><td>Prone to duplicates and gaps</td><td>More stable relative to key ordering</td></tr>
<tr><td>Jump to page 73</td><td>Easy</td><td>Not the primary goal</td></tr>
</tbody></table>
<p>Offset remains reasonable for small datasets, admin tables requiring numbered jumps, or static reports. Cursor pagination is not the default for every screen; it exchanges random page access for continuity and predictable work.</p>

<h2>1. Start with a total order</h2>
<p>A cursor is correct only when every record has one deterministic position. Sorting by <code>created_at DESC</code> is insufficient because multiple rows can share a timestamp. Add a unique tie-breaker, usually the primary key:</p>
<pre><code>ORDER BY created_at DESC, id DESC</code></pre>
<p>The pair <code>(created_at, id)</code> creates a total order when <code>id</code> is unique and both values remain stable throughout a record's lifetime. Avoid a mutable field such as <code>updated_at</code> when records moving during traversal would be undesirable.</p>
<p>SQL does not guarantee row order without <code>ORDER BY</code>. Results that appear to follow a primary key in development may change with a different query plan or production dataset.</p>

<h2>2. Match the keyset predicate to sort direction</h2>
<p>The first page fetches the newest orders:</p>
<pre><code>SELECT id, created_at, status, total
FROM orders
WHERE tenant_id = :tenant_id
ORDER BY created_at DESC, id DESC
LIMIT :limit_plus_one;</code></pre>
<p>If the last record has <code>created_at = 2026-09-19T08:30:00Z</code> and <code>id = 9102</code>, the next page compares the same key set:</p>
<pre><code>SELECT id, created_at, status, total
FROM orders
WHERE tenant_id = :tenant_id
  AND (created_at, id) &lt; (:cursor_created_at, :cursor_id)
ORDER BY created_at DESC, id DESC
LIMIT :limit_plus_one;</code></pre>
<p>PostgreSQL compares row constructors left to right. The equivalent expanded predicate is:</p>
<pre><code>created_at &lt; :cursor_created_at
OR (created_at = :cursor_created_at AND id &lt; :cursor_id)</code></pre>
<p>The operator must change with sort direction. Mixed <code>ASC</code>/<code>DESC</code> orders, <code>NULL</code> handling, and expression-based sorts need specific design and tests; do not apply one <code>&lt;</code> rule mechanically.</p>

<h2>3. Match the index to filters and ordering</h2>
<p>The query is efficient when the database can walk a B-tree index instead of sorting or scanning most of the table:</p>
<pre><code>CREATE INDEX orders_tenant_created_id_idx
ON orders (tenant_id, created_at DESC, id DESC);</code></pre>
<p><code>tenant_id</code> comes first because every query scopes by tenant; the remaining columns match the cursor order. If the API always filters by <code>status</code>, evaluate another index based on real selectivity and workload instead of adding every filter to one oversized index.</p>
<p>Use <code>EXPLAIN (ANALYZE, BUFFERS)</code> with production-like data. An index that looks correct may still be skipped when a query returns much of the table, statistics are stale, or parameter types do not match.</p>

<h2>4. Keep cursors opaque and verifiable</h2>
<p>Clients do not need to know that a cursor contains a timestamp and ID. The server can serialize a versioned payload and base64url-encode it:</p>
<pre><code>{
  "v": 1,
  "created_at": "2026-09-19T08:30:00.000000Z",
  "id": 9102,
  "filter": "sha256:..."
}</code></pre>
<p>Base64 is encoding, not security. If cursor manipulation could cross an access boundary or create abnormal queries, sign the payload with HMAC and verify it using constant-time comparison. Do not place sensitive data in a cursor; use authenticated encryption or server-side state when secrecy is required.</p>
<p>The <code>v</code> field allows future format changes. Decoders should cap token size, validate types and dates, and reject unsupported versions with a clear 400 error instead of allowing parser or database failures to become 500 responses.</p>

<h2>5. Bind cursors to filters and scope</h2>
<p>A cursor generated for <code>status=paid</code> should not be reused with <code>status=pending</code>. Likewise, a cursor from tenant A must never open tenant B's data.</p>
<p>Either include normalized filters and scope in the signed payload or store a filter hash and compare it with the current request. Tenant or user scope must still come from the authenticated principal and always appear in the query's <code>WHERE</code>. Cursor integrity does not replace authorization.</p>

<h2>6. Design a client-friendly response</h2>
<pre><code>{
  "data": [ ... ],
  "page": {
    "next_cursor": "eyJ2IjoxLC4uLn0.signature",
    "previous_cursor": null,
    "has_more": true
  }
}</code></pre>
<p>Fetch <code>limit + 1</code> records to determine whether another page exists, then return only <code>limit</code>. This avoids a <code>COUNT(*)</code> on every request. Total counts may be expensive and quickly stale; provide them only when the product truly needs them, perhaps through a separate endpoint or approximation.</p>
<p>Enforce page-size limits on the server, for example 25 by default and 100 maximum. A huge client-provided limit can overload database reads, memory, and serialization.</p>

<h2>7. Support previous pages without reversing the UI</h2>
<p>To move backward, a cursor commonly carries the first record key of the current page. For a descending display, query records greater than that key in ascending order, limit the result, then reverse it before returning so the UI remains descending:</p>
<pre><code>SELECT id, created_at, status, total
FROM orders
WHERE tenant_id = :tenant_id
  AND (created_at, id) &gt; (:cursor_created_at, :cursor_id)
ORDER BY created_at ASC, id ASC
LIMIT :limit_plus_one;</code></pre>
<p>Do not use one ambiguous token for both directions. Issue separate <code>next_cursor</code> and <code>previous_cursor</code> values or encode and strictly validate direction.</p>

<h2>8. What happens when data changes?</h2>
<p>Cursor pagination is more stable than offset pagination, but it does not automatically create a snapshot:</p>
<ul>
<li><strong>New records at the front:</strong> a reader moving toward older data usually is not displaced; they simply have not seen the new items.</li>
<li><strong>Deleted records:</strong> traversal continues from key values without repairing an offset position.</li>
<li><strong>Changed sort keys:</strong> a record may reappear or be missed because it moved.</li>
<li><strong>Changed filter fields:</strong> a record can enter or leave the result set between requests.</li>
</ul>
<p>Live semantics are often acceptable for feeds. Exports and workflows that must read one immutable set need a snapshot boundary such as <code>created_at &lt;= :as_of</code>, an appropriate transaction snapshot, or a materialized export job. A cursor does not replace isolation.</p>

<h2>9. Timestamps, IDs, and NULL values create traps</h2>
<ul>
<li>Preserve timestamp precision during encoding and decoding; rounding microseconds can skip records.</li>
<li>Do not assume random UUIDs represent time; use them as tie-breakers, not substitutes for a time key.</li>
<li>For nullable sort columns, define <code>NULLS FIRST/LAST</code> and matching predicates or use a normalized non-null column.</li>
<li>Text collation can change ordering across locales or versions; text cursors need a stable contract and careful migrations.</li>
<li>Avoid floating-point cursor keys when values may be recomputed.</li>
</ul>

<h2>10. Error contracts and observability</h2>
<p>An expired cursor, bad signature, filter mismatch, or unsupported version should return 400 with a machine-readable code. Do not expose stack traces or signature details. When an anchor row has been deleted, a valid keyset query can still continue because it uses key values rather than requiring the row to exist.</p>
<p>Monitor latency by estimated depth, rows read versus returned, invalid-cursor rates, page sizes, query plans, and requests hitting the limit. Decoded cursor payloads may contain identifiers; log version, direction, and a shortened hash rather than the entire token.</p>

<h2>11. Required test scenarios</h2>
<ol>
<li>Records sharing a timestamp appear exactly once because of the tie-breaker.</li>
<li>Inserting a new record between requests does not repeat an item on the next page.</li>
<li>Deleting the previous page's last record does not break continuation.</li>
<li>A cursor changed by one byte is rejected.</li>
<li>A cursor from another filter or tenant is not accepted.</li>
<li>Moving next and then previous returns the same window and order.</li>
<li>Zero, negative, and excessive limits follow the documented policy.</li>
<li>Timestamp precision survives an encode/decode round trip.</li>
<li>A deep traversal still uses the expected index on a large dataset.</li>
</ol>

<h2>Production checklist</h2>
<ul>
<li>The order has a unique tie-breaker and stable sort keys.</li>
<li>The keyset predicate exactly matches order and direction.</li>
<li>The composite index begins with important scope/filter columns, then sort keys.</li>
<li>The cursor is opaque, versioned, size-limited, and signed when appropriate.</li>
<li>Authorization is always enforced independently of the cursor.</li>
<li>The response uses <code>limit + 1</code> and caps page size.</li>
<li>Insert, update, delete, and snapshot semantics are documented.</li>
<li>Next/previous navigation, filter mismatch, and invalid cursors are tested.</li>
</ul>

<h2>Conclusion</h2>
<p>Cursor pagination is not merely the last ID encoded in base64. Production design starts with a total order, a directionally correct predicate, and a matching index; only then come tokens, signatures, filters, bidirectional navigation, and mutation semantics. Done well, an API can traverse millions of records with steadier cost and fewer duplicates or gaps than offset pagination.</p>

<h2>References</h2>
<ul>
<li><a href="https://www.postgresql.org/docs/current/queries-limit.html" target="_blank" rel="noopener noreferrer">PostgreSQL: LIMIT and OFFSET</a></li>
<li><a href="https://www.postgresql.org/docs/current/indexes-ordering.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Indexes and ORDER BY</a></li>
<li><a href="https://www.postgresql.org/docs/current/functions-comparisons.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Row Constructor Comparison</a></li>
</ul>
HTML,
        ],
    ],
];
