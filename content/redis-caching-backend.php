<?php

return [
    'redis-caching-backend.html' => [
        'vi' => [
            'title' => 'Thiết kế cache Redis an toàn cho backend: TTL, invalidation và chống cache stampede',
            'slug' => 'thiet-ke-cache-redis-backend-an-toan',
            'image' => 'redis-caching-backend.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Thiết kế cache Redis an toàn cho backend',
            'meta_keywords' => 'Redis cache, cache-aside, cache invalidation, cache stampede, TTL Redis, backend performance, negative caching',
            'meta_description' => 'Hướng dẫn thiết kế cache Redis cho backend với cache-aside, TTL, invalidation, chống stampede, giới hạn bộ nhớ và giám sát production.',
            'tags' => ['Redis', 'Caching', 'Backend', 'Performance', 'Cache Aside', 'Distributed Systems', 'Database', 'Scalability'],
            'body' => <<<'HTML'
<p><strong>Cache có thể giảm latency và tải database rất mạnh, nhưng cũng có thể trả dữ liệu cũ, che lỗi và tạo một đợt truy vấn đồng thời khi key hết hạn.</strong> Thiết kế cache production cần xác định nguồn dữ liệu chuẩn, mức độ stale chấp nhận được, cách invalidation và hành vi của ứng dụng khi Redis không khả dụng.</p>

<h2>Khi nào nên thêm cache?</h2>
<p>Chỉ thêm cache sau khi đã đo bottleneck. Cache phù hợp với dữ liệu được đọc lặp lại, chi phí tính toán cao, ít thay đổi hơn tần suất đọc và có thể chấp nhận stale trong một khoảng xác định. Ví dụ: danh mục sản phẩm, cấu hình công khai, profile, kết quả truy vấn tổng hợp hoặc response từ dịch vụ chậm.</p>
<p>Không nên cache để che truy vấn sai, thiếu index hoặc API thiết kế kém. Dữ liệu như số dư, trạng thái thanh toán hay quyền truy cập cần đánh giá rất thận trọng; một cache hit nhanh nhưng sai vẫn là lỗi.</p>

<h2>Cache-aside: mô hình dễ kiểm soát</h2>
<p>Trong cache-aside, ứng dụng tự quản lý cache:</p>
<ol><li>Đọc Redis theo cache key.</li><li>Nếu hit, deserialize và trả dữ liệu.</li><li>Nếu miss, đọc nguồn chuẩn như database.</li><li>Ghi kết quả vào Redis với TTL.</li><li>Khi dữ liệu thay đổi, ghi database rồi xóa cache key.</li></ol>
<pre><code>async function getProduct(id) {
  const key = `shop:v3:product:${id}`;
  const cached = await redis.get(key);

  if (cached !== null) {
    return JSON.parse(cached);
  }

  const product = await database.products.findById(id);
  await redis.set(key, JSON.stringify(product), { EX: 300 });
  return product;
}</code></pre>
<p>Ưu điểm là cache chỉ chứa dữ liệu thực sự được truy cập và database luôn là nguồn chuẩn. Nhược điểm là request miss chậm hơn, logic cache nằm trong ứng dụng và cần xử lý đồng thời.</p>

<h2>Thiết kế key có namespace và version</h2>
<p>Cache key nên mô tả ứng dụng, phiên bản schema, loại dữ liệu và định danh:</p>
<pre><code>shop:v3:product:8421
shop:v3:category:18:page:2:sort:newest
billing:v1:customer-summary:991</code></pre>
<p>Version trong key giúp triển khai schema cache mới mà không phải quét và sửa mọi entry cũ. Tránh nhúng dữ liệu nhạy cảm hoặc chuỗi người dùng chưa chuẩn hóa vào key. Với truy vấn có nhiều tham số, canonicalize và hash phần tham số để tránh hai key cho cùng một truy vấn.</p>

<h2>TTL là giới hạn stale, không phải con số tùy ý</h2>
<p>TTL nên xuất phát từ nghiệp vụ: dữ liệu được phép cũ bao lâu và database chịu được bao nhiêu miss. Giá sản phẩm có thể là vài phút; feature flag quan trọng có thể chỉ vài giây; nội dung tĩnh có thể lâu hơn.</p>
<p>Không đặt tất cả key hết hạn cùng một thời điểm. Thêm jitter ngẫu nhiên giúp phân tán expiration:</p>
<pre><code>const baseTtl = 300;
const jitter = Math.floor(Math.random() * 60);
await redis.set(key, payload, { EX: baseTtl + jitter });</code></pre>
<p>Mọi entry cache-aside nên có TTL, kể cả khi ứng dụng có invalidation. TTL là lớp an toàn khi event hoặc thao tác xóa key bị bỏ lỡ.</p>

<h2>Invalidation: ghi database trước, xóa cache sau</h2>
<pre><code>await database.transaction(async (tx) =&gt; {
  await tx.products.update(productId, changes);
});

await redis.del(`shop:v3:product:${productId}`);</code></pre>
<p>Xóa key thường an toàn hơn ghi giá trị mới vào cache vì lần đọc tiếp theo sẽ tải lại từ nguồn chuẩn. Nếu xóa cache trước rồi database update thất bại hoặc bị chậm, request khác có thể đọc dữ liệu cũ từ database và đưa nó trở lại cache.</p>
<p>Vẫn tồn tại cửa sổ lỗi giữa commit database và <code>DEL</code>. Với dữ liệu quan trọng, dùng transactional outbox hoặc change data capture để phát event invalidation có thể retry. TTL giới hạn thời gian stale nếu event cuối cùng vẫn thất bại.</p>

<h2>Tránh race condition khi đọc và ghi</h2>
<p>Một request miss đọc dữ liệu cũ, sau đó request khác update database và xóa key; request đầu tiên có thể ghi dữ liệu cũ trở lại cache. Cách giảm rủi ro:</p>
<ul><li>Dùng TTL ngắn hơn cho dữ liệu thay đổi thường xuyên.</li><li>Kèm version hoặc <code>updated_at</code> trong value và chỉ ghi nếu version còn hợp lệ.</li><li>Thực hiện delayed double delete cho một số luồng có race đã được đo.</li><li>Dùng event invalidation sau commit và thiết kế consumer idempotent.</li><li>Không cache dữ liệu yêu cầu read-after-write nghiêm ngặt trên luồng đó.</li></ul>

<h2>Chống cache stampede</h2>
<p>Khi một key nóng hết hạn, hàng trăm request có thể cùng miss và đánh vào database. Dùng single-flight hoặc mutex theo key để chỉ một request load dữ liệu:</p>
<pre><code>const lockKey = `lock:${key}`;
const token = crypto.randomUUID();
const acquired = await redis.set(lockKey, token, { NX: true, PX: 5000 });

if (acquired) {
  try {
    const value = await loadFromDatabase();
    await redis.set(key, JSON.stringify(value), { EX: 300 });
    return value;
  } finally {
    await releaseOnlyIfTokenMatches(lockKey, token);
  }
}

return await waitBrieflyAndReadCacheAgain();</code></pre>
<p>Lock phải có TTL dài hơn thời gian load xấu nhất, dùng token duy nhất và chỉ owner mới được release. Request không lấy được lock nên đợi ngắn, đọc lại cache hoặc dùng stale value; không quay vòng bận vô hạn.</p>

<h2>Stale-while-revalidate cho dữ liệu đọc nhiều</h2>
<p>Có thể lưu hai mốc: soft TTL và hard TTL. Sau soft TTL, ứng dụng vẫn trả dữ liệu cũ trong khi một worker refresh nền; sau hard TTL mới bắt buộc chờ nguồn chuẩn. Mô hình này giảm latency và stampede, nhưng chỉ phù hợp khi nghiệp vụ chấp nhận stale có giới hạn.</p>
<p>Không dùng stale-while-revalidate cho quyền, hạn mức hoặc dữ liệu mà giá trị cũ có thể gây giao dịch sai.</p>

<h2>Negative caching và cache penetration</h2>
<p>Request lặp cho ID không tồn tại vẫn có thể xuyên qua cache và làm database quá tải. Cache sentinel “not found” với TTL ngắn:</p>
<pre><code>if (cached === '__NOT_FOUND__') return null;

const value = await loadFromDatabase(id);
if (value === null) {
  await redis.set(key, '__NOT_FOUND__', { EX: 30 });
  return null;
}</code></pre>
<p>TTL negative phải ngắn hơn positive để bản ghi vừa tạo sớm xuất hiện. Luôn validate định dạng ID, rate-limit client và không để attacker tạo vô hạn key ngẫu nhiên.</p>

<h2>Giới hạn bộ nhớ và eviction policy</h2>
<p>Redis cần <code>maxmemory</code> và policy phù hợp. Với instance chỉ dành cho cache, <code>allkeys-lru</code> hoặc <code>allkeys-lfu</code> thường là điểm bắt đầu hợp lý tùy access pattern. <code>noeviction</code> sẽ trả lỗi cho write mới khi chạm giới hạn.</p>
<p>Không nên dùng cùng một Redis instance cho cache có thể bị eviction và dữ liệu cần bền như queue hoặc session quan trọng nếu có thể tách. Policy <code>volatile-*</code> chỉ loại key có TTL và có thể hành xử như noeviction nếu các key khác không có expiry.</p>

<h2>Ứng dụng phải làm gì khi Redis lỗi?</h2>
<p>Cache thường là lớp tối ưu, không phải nguồn chuẩn. Khi Redis timeout:</p>
<ul><li>Đặt connect/read timeout ngắn, không để request treo lâu hơn database.</li><li>Circuit-break cache sau chuỗi lỗi để tránh retry storm.</li><li>Fallback database có rate limit và concurrency cap để không làm sập nguồn chuẩn.</li><li>Không fail request chỉ vì thao tác ghi cache thất bại, nếu nghiệp vụ cho phép.</li><li>Không swallow lỗi hoàn toàn; ghi metrics và cảnh báo theo tỷ lệ.</li></ul>
<p>Nếu Redis lưu session, lock hoặc rate-limit state, nó không còn là “cache thuần túy”; chính sách availability và failure phải được thiết kế riêng.</p>

<h2>Serialization, nén và payload</h2>
<p>Cache object nhỏ, ổn định và chỉ chứa trường cần dùng. Payload lớn tăng network, CPU serialize và memory fragmentation. Gắn schema version, xử lý deserialize failure như cache miss và xóa entry hỏng.</p>
<p>Chỉ nén khi số liệu chứng minh tiết kiệm băng thông/bộ nhớ lớn hơn chi phí CPU. Không cache object chứa secret hoặc dữ liệu cá nhân nếu chưa đánh giá mã hóa, retention và quyền truy cập Redis.</p>

<h2>Những chỉ số cần theo dõi</h2>
<ul><li>Hit rate và miss rate theo nhóm key.</li><li>P50, p95, p99 latency của Redis và loader database.</li><li>Evicted keys, expired keys, memory used và fragmentation.</li><li>Connection count, rejected connections và timeout.</li><li>Số lock contention, thời gian chờ và loader concurrency.</li><li>Tỷ lệ fallback database khi Redis lỗi.</li><li>Độ trễ invalidation và số event retry/dead-letter.</li></ul>
<p>Hit rate cao không tự động tốt. Cache có thể hit dữ liệu sai hoặc giữ object ít giá trị. Đo thêm database load, end-to-end latency và độ mới dữ liệu.</p>

<h2>Kiểm thử production behavior</h2>
<ol><li>Cache hit, miss, expiry và invalidation sau update.</li><li>Nhiều request đồng thời vào một cold key chỉ tạo một database load.</li><li>Redis timeout, mất kết nối và đạt maxmemory.</li><li>Database chậm trong lúc lock gần hết TTL.</li><li>Event invalidation đến trùng hoặc sai thứ tự.</li><li>Negative cache không che bản ghi mới quá lâu.</li><li>Deploy schema value mới vẫn đọc an toàn hoặc miss có kiểm soát.</li></ol>

<h2>Checklist trước khi phát hành</h2>
<ol><li>Đã đo bottleneck và xác định cache thực sự cần thiết.</li><li>Database hoặc service gốc vẫn là nguồn dữ liệu chuẩn.</li><li>Key có namespace, version và không chứa secret.</li><li>Mọi entry có TTL dựa trên mức stale chấp nhận được.</li><li>Update ghi nguồn chuẩn trước rồi invalidation sau commit.</li><li>Hot key có jitter và stampede protection.</li><li>Redis có maxmemory, eviction policy và giám sát.</li><li>Fallback được giới hạn để bảo vệ database.</li><li>Cache failure không làm sai nghiệp vụ.</li></ol>

<h2>Kết luận</h2>
<p>Cache Redis an toàn không chỉ là thêm <code>GET</code> trước query. Thiết kế tốt coi cache là dữ liệu có thể mất, có thể stale và có thể không truy cập được. Cache-aside, TTL có chủ đích, invalidation sau commit, single-flight và giới hạn bộ nhớ giúp tăng tốc backend mà không biến cache thành nguồn lỗi khó kiểm soát.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://redis.io/docs/latest/develop/use-cases/cache-aside/" target="_blank" rel="noopener noreferrer">Redis Docs: Cache-aside</a></li><li><a href="https://redis.io/docs/latest/develop/reference/eviction/" target="_blank" rel="noopener noreferrer">Redis Docs: Key eviction</a></li><li><a href="https://redis.io/docs/latest/develop/use/keyspace/" target="_blank" rel="noopener noreferrer">Redis Docs: Keys and expiration</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Safe Redis Caching for Backends: TTLs, Invalidation, and Stampede Protection',
            'slug' => 'safe-redis-caching-backends-ttl-invalidation-stampede',
            'image' => 'redis-caching-backend.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Safe Redis Caching Design for Backends',
            'meta_keywords' => 'Redis cache, cache-aside, cache invalidation, cache stampede, Redis TTL, backend performance, negative caching',
            'meta_description' => 'Design safe backend caching with Redis cache-aside, TTLs, invalidation, stampede protection, memory limits, fallbacks, and production monitoring.',
            'tags' => ['Redis', 'Caching', 'Backend', 'Performance', 'Cache Aside', 'Distributed Systems', 'Database', 'Scalability'],
            'body' => <<<'HTML'
<p><strong>A cache can sharply reduce latency and database load, but it can also serve stale data, hide failures, and unleash concurrent queries when a hot key expires.</strong> Production cache design must define the authoritative source, acceptable staleness, invalidation strategy, and application behavior when Redis is unavailable.</p>

<h2>When should you add a cache?</h2>
<p>Add caching only after measuring the bottleneck. It fits repeatedly read data that is expensive to compute, changes less often than it is read, and can tolerate bounded staleness. Examples include product catalogs, public configuration, profiles, aggregate queries, and responses from slow dependencies.</p>
<p>Do not use a cache to hide poor queries, missing indexes, or bad API design. Balances, payment state, and authorization require special care; a fast but incorrect cache hit is still a defect.</p>

<h2>Cache-aside is easy to reason about</h2>
<ol><li>Read Redis using a cache key.</li><li>On a hit, deserialize and return.</li><li>On a miss, read the authoritative database.</li><li>Store the result in Redis with a TTL.</li><li>When data changes, write the database and delete the cache key.</li></ol>
<pre><code>async function getProduct(id) {
  const key = `shop:v3:product:${id}`;
  const cached = await redis.get(key);

  if (cached !== null) return JSON.parse(cached);

  const product = await database.products.findById(id);
  await redis.set(key, JSON.stringify(product), { EX: 300 });
  return product;
}</code></pre>
<p>The cache holds only requested data and the database remains authoritative. The tradeoffs are slower misses, application-managed cache logic, and concurrency concerns.</p>

<h2>Namespace and version keys</h2>
<pre><code>shop:v3:product:8421
shop:v3:category:18:page:2:sort:newest
billing:v1:customer-summary:991</code></pre>
<p>A schema version enables a new cache format without rewriting old entries. Do not embed secrets or unnormalized user input in keys. Canonicalize and hash complex query parameters to avoid multiple keys for the same query.</p>

<h2>TTL is a staleness boundary</h2>
<p>Choose TTL from business tolerance and acceptable primary-store load. Product data may tolerate minutes, a critical feature flag seconds, and static content much longer.</p>
<p>Add random jitter so keys do not expire together:</p>
<pre><code>const baseTtl = 300;
const jitter = Math.floor(Math.random() * 60);
await redis.set(key, payload, { EX: baseTtl + jitter });</code></pre>
<p>Every cache-aside entry should have a TTL even with explicit invalidation. It limits stale duration when an invalidation is missed.</p>

<h2>Write the database, then invalidate</h2>
<pre><code>await database.transaction(async (tx) =&gt; {
  await tx.products.update(productId, changes);
});

await redis.del(`shop:v3:product:${productId}`);</code></pre>
<p>Deleting is usually safer than updating the cached value because the next read reloads the authoritative source. If cache deletion happens before a slow or failed database update, another request may repopulate the old database value.</p>
<p>A failure window remains between database commit and <code>DEL</code>. For important data, use a transactional outbox or change-data-capture event that can retry invalidation. TTL bounds staleness if delivery ultimately fails.</p>

<h2>Control read-write races</h2>
<p>A miss may read an old value, another request may update and invalidate, and then the first request may write the old value back. Mitigations include:</p>
<ul><li>Shorter TTL for frequently changing data.</li><li>A version or <code>updated_at</code> value checked before cache writes.</li><li>Delayed double deletion for measured race conditions.</li><li>Post-commit invalidation events with idempotent consumers.</li><li>Bypassing cache on strict read-after-write paths.</li></ul>

<h2>Prevent cache stampedes</h2>
<p>When a hot key expires, many concurrent requests may hit the database. Use per-key single-flight or a mutex so only one caller loads:</p>
<pre><code>const lockKey = `lock:${key}`;
const token = crypto.randomUUID();
const acquired = await redis.set(lockKey, token, { NX: true, PX: 5000 });

if (acquired) {
  try {
    const value = await loadFromDatabase();
    await redis.set(key, JSON.stringify(value), { EX: 300 });
    return value;
  } finally {
    await releaseOnlyIfTokenMatches(lockKey, token);
  }
}

return await waitBrieflyAndReadCacheAgain();</code></pre>
<p>The lock TTL must exceed worst-case loading time, use a unique token, and be released only by its owner. Callers that lose the lock should wait briefly, retry the cache, or use a stale value rather than spin forever.</p>

<h2>Use stale-while-revalidate selectively</h2>
<p>Store a soft and hard expiration. After soft expiry, return stale data while one worker refreshes it; after hard expiry, require the authoritative source. This reduces latency and stampedes but only works when bounded staleness is acceptable.</p>
<p>Do not use stale-while-revalidate for permissions, limits, or transactional values where an old answer can cause an incorrect action.</p>

<h2>Negative caching</h2>
<p>Repeated requests for missing IDs can bypass the cache and overload the database. Cache a short-lived not-found sentinel:</p>
<pre><code>if (cached === '__NOT_FOUND__') return null;

const value = await loadFromDatabase(id);
if (value === null) {
  await redis.set(key, '__NOT_FOUND__', { EX: 30 });
  return null;
}</code></pre>
<p>Keep negative TTL shorter than positive TTL so newly created records appear quickly. Validate ID formats, rate-limit clients, and prevent attackers from generating unlimited random keys.</p>

<h2>Memory limits and eviction policy</h2>
<p>Configure Redis <code>maxmemory</code> and a suitable policy. For a cache-only instance, <code>allkeys-lru</code> or <code>allkeys-lfu</code> is often a reasonable starting point depending on access patterns. <code>noeviction</code> returns errors for new writes at the memory limit.</p>
<p>Avoid mixing evictable cache with durable queues or critical sessions in one instance when separation is possible. <code>volatile-*</code> policies evict only keys with TTL and can behave like noeviction when other keys consume memory.</p>

<h2>Behavior when Redis fails</h2>
<ul><li>Use short connect and read timeouts.</li><li>Circuit-break cache calls after repeated failures.</li><li>Rate-limit and cap concurrent database fallback.</li><li>Do not fail a request solely because a cache write failed when business rules allow.</li><li>Record metrics and alert on failure rate instead of silently swallowing every error.</li></ul>
<p>If Redis stores sessions, locks, or rate-limit state, it is no longer a pure optimization layer; design its availability and failure behavior separately.</p>

<h2>Serialization and payload size</h2>
<p>Cache small, stable objects containing only needed fields. Large payloads increase network, serialization CPU, and memory fragmentation. Version the value schema, treat deserialization failures as misses, and delete corrupt entries.</p>
<p>Compress only when measurements show that memory or bandwidth savings exceed CPU cost. Do not cache secrets or personal data without evaluating encryption, retention, and Redis access controls.</p>

<h2>Production metrics</h2>
<ul><li>Hit and miss rates by key family.</li><li>Redis and database-loader p50, p95, and p99 latency.</li><li>Evictions, expirations, used memory, and fragmentation.</li><li>Connections, rejected connections, and timeouts.</li><li>Lock contention, wait time, and loader concurrency.</li><li>Database fallback rate during Redis failures.</li><li>Invalidation delay and retry or dead-letter events.</li></ul>
<p>A high hit rate is not automatically good. The cache may be hitting incorrect data or retaining low-value objects. Also measure database load, end-to-end latency, and freshness.</p>

<h2>Test production behavior</h2>
<ol><li>Hits, misses, expiration, and invalidation after updates.</li><li>Concurrent requests for one cold key produce only one database load.</li><li>Redis timeout, disconnection, and maxmemory behavior.</li><li>A slow database while the lock approaches expiration.</li><li>Duplicate or out-of-order invalidation events.</li><li>Negative caching does not hide new records for too long.</li><li>A new value schema deploy fails safely or produces controlled misses.</li></ol>

<h2>Pre-release checklist</h2>
<ol><li>The bottleneck was measured and caching is justified.</li><li>The database or origin service remains authoritative.</li><li>Keys have namespaces and versions and contain no secrets.</li><li>Every entry has a TTL based on acceptable staleness.</li><li>Updates write the source first and invalidate after commit.</li><li>Hot keys use jitter and stampede protection.</li><li>Redis has maxmemory, an eviction policy, and monitoring.</li><li>Fallback is bounded to protect the database.</li><li>Cache failure cannot corrupt business behavior.</li></ol>

<h2>Conclusion</h2>
<p>Safe Redis caching is more than placing a <code>GET</code> before a query. A sound design assumes cached data can disappear, become stale, or be unavailable. Cache-aside, deliberate TTLs, post-commit invalidation, single-flight loading, and memory limits improve backend performance without turning the cache into an uncontrolled source of failures.</p>

<h2>References</h2>
<ul><li><a href="https://redis.io/docs/latest/develop/use-cases/cache-aside/" target="_blank" rel="noopener noreferrer">Redis Docs: Cache-aside</a></li><li><a href="https://redis.io/docs/latest/develop/reference/eviction/" target="_blank" rel="noopener noreferrer">Redis Docs: Key eviction</a></li><li><a href="https://redis.io/docs/latest/develop/use/keyspace/" target="_blank" rel="noopener noreferrer">Redis Docs: Keys and expiration</a></li></ul>
HTML,
        ],
    ],
];
