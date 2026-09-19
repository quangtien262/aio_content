<?php

return [
    'api-rate-limiting-redis.html' => [
        'vi' => [
            'title' => 'API Rate Limiting thực chiến với Redis: Thuật toán, thiết kế và vận hành',
            'slug' => 'api-rate-limiting-redis-thuc-chien',
            'image' => 'api-rate-limiting-redis.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'API Rate Limiting thực chiến với Redis',
            'meta_keywords' => 'API rate limiting, Redis, token bucket, sliding window, fixed window, HTTP 429, distributed rate limiter',
            'meta_description' => 'Thiết kế API rate limiting production với Redis: chọn thuật toán, key và quota, Lua atomic, phản hồi HTTP 429, chống burst, fallback, quan sát và kiểm thử.',
            'tags' => ['API', 'Rate Limiting', 'Redis', 'Backend', 'HTTP', 'Distributed Systems', 'Security'],
            'body' => <<<'HTML'
<p><strong>Rate limiting không chỉ để chặn bot.</strong> Nó bảo vệ database và dịch vụ phụ thuộc khỏi traffic burst, phân chia tài nguyên công bằng giữa tenant, kiểm soát chi phí API và giúp hệ thống suy giảm có kiểm soát khi quá tải. Một limiter thiết kế sai lại có thể khóa nhầm hàng nghìn người dùng sau NAT hoặc trở thành điểm lỗi mới.</p>
<p>Bài viết này đi từ lựa chọn thuật toán đến triển khai Redis phân tán, với mục tiêu tạo một chính sách dễ giải thích, có khả năng quan sát và hoạt động nhất quán trên nhiều application instance.</p>

<h2>Rate limit khác quota và concurrency limit</h2>
<ul>
<li><strong>Rate limit:</strong> số request trong một khoảng thời gian, ví dụ 100 request/phút.</li>
<li><strong>Quota:</strong> tổng mức sử dụng trong chu kỳ dài, ví dụ 1 triệu request/tháng.</li>
<li><strong>Concurrency limit:</strong> số tác vụ đang chạy đồng thời.</li>
</ul>
<p>Một API upload file có thể cần cả ba: rate limit chống spam, quota kiểm soát chi phí và concurrency limit bảo vệ CPU/I/O. Đừng dùng một counter phút để thay thế mọi cơ chế.</p>

<h2>1. Xác định mục tiêu trước khi chọn thuật toán</h2>
<p>Trả lời các câu hỏi:</p>
<ul>
<li>Tài nguyên nào cần bảo vệ: login, search, checkout hay API toàn cục?</li>
<li>Ai là chủ thể: user, API key, tenant, IP hay device?</li>
<li>Có chấp nhận burst ngắn không?</li>
<li>Limiter cần chính xác toàn cluster hay mỗi region có ngân sách riêng?</li>
<li>Khi Redis lỗi, request nên được phép hay bị từ chối?</li>
</ul>
<p>Chính sách nên gắn với chi phí. Một request đọc cache không nên tiêu cùng số token với export báo cáo hoặc chạy AI inference.</p>

<h2>2. Chọn identity và key an toàn</h2>
<p>Ưu tiên identity đã xác thực: tenant ID, user ID hoặc API key ID. IP chỉ là tín hiệu phụ vì NAT có thể gom cả văn phòng vào một địa chỉ, trong khi attacker có thể xoay proxy.</p>
<pre><code>rl:v1:{tenant_id}:{route_group}:{window}</code></pre>
<p>Không đưa raw API key, email hoặc dữ liệu cá nhân vào Redis key/log. Hash hoặc dùng internal ID. Chỉ tin <code>X-Forwarded-For</code> từ reverse proxy do bạn kiểm soát; client có thể giả header này nếu ứng dụng tiếp xúc trực tiếp Internet.</p>
<p>Thường cần nhiều lớp:</p>
<ul>
<li>Giới hạn IP thô ở CDN/WAF để hấp thụ abuse.</li>
<li>Giới hạn user/API key tại gateway hoặc application.</li>
<li>Giới hạn tenant để một khách hàng không chiếm toàn bộ capacity.</li>
<li>Giới hạn global để bảo vệ dependency khi gần quá tải.</li>
</ul>

<h2>3. Fixed window: đơn giản nhưng có burst ở biên</h2>
<p>Fixed window đếm request trong từng phút/giây:</p>
<pre><code>bucket = floor(current_time / 60)
key = "rl:user:42:" + bucket
count = INCR(key)
EXPIRE(key, 120)
allow = count &lt;= 100</code></pre>
<p>Ưu điểm là O(1), ít bộ nhớ và dễ vận hành. Nhược điểm: client có thể gửi 100 request cuối phút rồi 100 request đầu phút kế tiếp, tạo 200 request trong vài giây.</p>
<p><code>INCR</code> và <code>EXPIRE</code> phải được thực hiện nguyên tử bằng transaction/script để tránh key không hết hạn khi process chết giữa hai lệnh.</p>

<h2>4. Sliding window log: chính xác nhưng tốn bộ nhớ</h2>
<p>Mỗi request được lưu vào sorted set với timestamp, sau đó xóa phần tử ngoài cửa sổ và đếm phần còn lại:</p>
<pre><code>ZREMRANGEBYSCORE key 0 (now - window)
ZADD key now unique_request_id
ZCARD key
EXPIRE key window</code></pre>
<p>Thuật toán cho giới hạn trượt chính xác nhưng chi phí tăng theo số request và cần ID duy nhất khi nhiều request cùng millisecond. Phù hợp endpoint nhạy cảm với lưu lượng vừa phải, không lý tưởng cho hàng triệu key hoạt động liên tục.</p>

<h2>5. Sliding window counter: cân bằng chi phí và độ mượt</h2>
<p>Giữ counter của cửa sổ hiện tại và trước đó, rồi nội suy phần cửa sổ trước còn ảnh hưởng:</p>
<pre><code>estimated = current_count
          + previous_count * remaining_fraction</code></pre>
<p>Nó dùng ít bộ nhớ hơn log, giảm burst ở biên tốt hơn fixed window nhưng chỉ là xấp xỉ. Đây là lựa chọn tốt cho quota request phổ thông khi sai số nhỏ được chấp nhận.</p>

<h2>6. Token bucket: phù hợp API có burst hợp lệ</h2>
<p>Bucket có sức chứa <code>capacity</code>, token được nạp theo tốc độ <code>refill_rate</code>. Mỗi request lấy một hoặc nhiều token. Khi hết token, request bị từ chối; sau thời gian rảnh, bucket tích lũy đủ token để cho phép burst có giới hạn.</p>
<p>Ví dụ capacity 60, refill 1 token/giây: client có thể burst tối đa 60 request sau thời gian rảnh, nhưng trung bình dài hạn là 1 request/giây. Request đắt có thể tốn 5–20 token.</p>

<h2>7. Token bucket atomic bằng Redis Lua</h2>
<p>Read–calculate–write phải nguyên tử để hai request đồng thời không cùng tiêu một token. Lua script chạy atomic trong Redis:</p>
<pre><code>local key = KEYS[1]
local capacity = tonumber(ARGV[1])
local refill_rate = tonumber(ARGV[2])
local now_ms = tonumber(ARGV[3])
local cost = tonumber(ARGV[4])

local values = redis.call('HMGET', key, 'tokens', 'updated_at')
local tokens = tonumber(values[1]) or capacity
local updated_at = tonumber(values[2]) or now_ms

local elapsed = math.max(0, now_ms - updated_at) / 1000
tokens = math.min(capacity, tokens + elapsed * refill_rate)

local allowed = 0
if tokens &gt;= cost then
    tokens = tokens - cost
    allowed = 1
end

redis.call('HSET', key, 'tokens', tokens, 'updated_at', now_ms)
redis.call('PEXPIRE', key, math.ceil((capacity / refill_rate) * 2000))

return { allowed, math.floor(tokens) }</code></pre>
<p>Ứng dụng phải truyền thời gian nhất quán hoặc dùng thời gian từ Redis để giảm clock skew. Script phải rất ngắn vì Redis chặn hoạt động khác trong lúc thực thi. Với Redis Cluster, mọi key của một lần gọi phải cùng hash slot; thiết kế limiter một key cho mỗi subject giúp đơn giản hóa.</p>

<h2>8. Đặt limiter ở đâu?</h2>
<ul>
<li><strong>CDN/WAF:</strong> chặn bot và volumetric abuse sớm, nhưng ít hiểu identity nghiệp vụ.</li>
<li><strong>API gateway:</strong> chính sách tập trung, phù hợp API key/tenant và giảm traffic vào app.</li>
<li><strong>Application:</strong> hiểu user, route và chi phí nghiệp vụ chính xác nhất.</li>
<li><strong>Downstream service:</strong> tự bảo vệ capacity riêng, ngay cả khi caller nội bộ lỗi.</li>
</ul>
<p>Production thường dùng nhiều lớp. Không dựa vào limiter trong application để chống DDoS vì request đã đi qua network và web server. Ngược lại, limiter ở CDN không thay được quota tenant trong nghiệp vụ.</p>

<h2>9. Phản hồi HTTP 429 hữu ích</h2>
<pre><code>HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json
Retry-After: 12
RateLimit-Limit: 100
RateLimit-Remaining: 0
RateLimit-Reset: 12

{
  "type": "https://api.example.com/problems/rate-limit",
  "title": "Rate limit exceeded",
  "status": 429,
  "retry_after": 12
}</code></pre>
<p><code>Retry-After</code> cho client biết khi nào thử lại. RFC 9333 định nghĩa các field RateLimit để mô tả limit, remaining và reset; khi hỗ trợ, hãy giữ semantics nhất quán và không coi header là cơ chế bảo mật. Không tiết lộ chi tiết có thể giúp bypass policy.</p>
<p>Client nên dùng exponential backoff với jitter, tôn trọng <code>Retry-After</code>, giới hạn số lần retry và không retry đồng loạt đúng một thời điểm.</p>

<h2>10. Đừng tính request thất bại giống nhau một cách máy móc</h2>
<p>Thường limiter quyết định trước khi handler chạy, nên request được phép sẽ tiêu token dù trả 4xx/5xx. Hoàn token sau lỗi tạo thêm race condition và có thể bị abuse. Thay vào đó, thiết kế budget riêng:</p>
<ul>
<li>Login thất bại giới hạn chặt theo account + IP.</li>
<li>Request validate sai vẫn tiêu ngân sách để chống spam.</li>
<li>Health check nội bộ có policy riêng.</li>
<li>Job đã nhận vào queue dùng concurrency/backpressure thay vì chỉ request rate.</li>
</ul>

<h2>11. Redis lỗi: fail-open hay fail-closed?</h2>
<p><strong>Fail-open</strong> cho request đi qua khi limiter unavailable, phù hợp endpoint đọc ít rủi ro nhưng có thể làm dependency quá tải. <strong>Fail-closed</strong> từ chối, phù hợp login, OTP, thanh toán hoặc giới hạn chi phí nghiêm ngặt nhưng biến Redis thành dependency availability.</p>
<p>Có thể dùng chiến lược lai: timeout Redis rất ngắn, local emergency limiter cho từng instance, circuit breaker và policy theo route. Không silently bypass; phải có metric/cảnh báo khi fallback được kích hoạt.</p>

<h2>12. Hot key, memory và multi-region</h2>
<p>Global key cho toàn hệ thống dễ trở thành hot key. Phân bổ limit theo tenant/route hoặc chia ngân sách theo region khi tính nhất quán tuyệt đối không cần thiết. Multi-region limiter đồng bộ mạnh làm tăng latency; quota region cộng lại có thể vượt global limit trong thời gian ngắn, nên để safety margin.</p>
<p>Đặt TTL cho mọi key tạm. Theo dõi memory, eviction, command latency và hit rate. Không dùng Redis cache với eviction tùy ý mà không hiểu hậu quả: key limiter bị evict có thể reset ngân sách sớm.</p>

<h2>13. Observability</h2>
<ul>
<li>Allowed và rejected theo route, tenant, plan và region.</li>
<li>Tỷ lệ 429 và số subject chạm ngưỡng.</li>
<li>Redis latency, timeout, error, memory và eviction.</li>
<li>Thời gian chạy Lua/function và hot key.</li>
<li>Số lần fail-open/fail-closed/fallback local.</li>
<li>Tương quan giữa rejection, downstream latency và saturation.</li>
</ul>
<p>Không gắn raw user ID vào metric cardinality cao. Log có sampling và hash identity; dashboard dùng dimension giới hạn như route/plan/region.</p>

<h2>14. Kiểm thử trước production</h2>
<ol>
<li>Unit test thời điểm biên cửa sổ, refill và request cost.</li>
<li>Concurrency test nhiều request cùng một key.</li>
<li>Load test với nhiều key và một hot key.</li>
<li>Chaos test Redis timeout, failover và connection pool cạn.</li>
<li>Xác minh TTL, memory growth và cleanup.</li>
<li>Test client xử lý 429, Retry-After và jitter.</li>
<li>Canary policy với ngưỡng cao, chỉ quan sát trước khi enforcement.</li>
</ol>

<h2>Checklist thiết kế</h2>
<ul>
<li>Policy gắn với tài nguyên và chi phí thực tế.</li>
<li>Identity ưu tiên user/API key/tenant, không chỉ IP.</li>
<li>Thuật toán phù hợp burst và độ chính xác cần thiết.</li>
<li>Quyết định atomic trong Redis và key có TTL.</li>
<li>429 có hướng dẫn retry nhất quán.</li>
<li>Fail-open/closed được chọn theo route và có cảnh báo.</li>
<li>Giới hạn có version, feature flag và khả năng rollback.</li>
<li>Dashboard đo rejection cùng sức khỏe dependency.</li>
</ul>

<h2>Kết luận</h2>
<p>Rate limiting tốt là cơ chế điều phối capacity, không phải một con số tùy ý đặt trước API. Hãy bắt đầu từ identity và chi phí, chọn fixed/sliding/token bucket theo hành vi burst, thực thi quyết định atomic trên Redis, trả 429 hữu ích và chuẩn bị cho chính lúc Redis gặp sự cố. Policy rõ ràng, quan sát được và rollout dần sẽ bảo vệ hệ thống mà không gây khó chịu không cần thiết cho người dùng hợp lệ.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://www.rfc-editor.org/rfc/rfc9333.html" target="_blank" rel="noopener noreferrer">RFC 9333: RateLimit Fields for HTTP</a></li><li><a href="https://redis.io/docs/latest/develop/use-cases/rate-limiter/" target="_blank" rel="noopener noreferrer">Redis: Rate limiter use case</a></li><li><a href="https://redis.io/docs/latest/develop/programmability/" target="_blank" rel="noopener noreferrer">Redis programmability and atomic execution</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'API Rate Limiting with Redis: Algorithms, Design, and Operations',
            'slug' => 'api-rate-limiting-redis-algorithms-design-operations',
            'image' => 'api-rate-limiting-redis.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Production API Rate Limiting with Redis',
            'meta_keywords' => 'API rate limiting, Redis, token bucket, sliding window, fixed window, HTTP 429, distributed rate limiter',
            'meta_description' => 'Design production API rate limiting with Redis: algorithms, identity and quotas, atomic Lua, HTTP 429 responses, burst control, failure policies, observability, and testing.',
            'tags' => ['API', 'Rate Limiting', 'Redis', 'Backend', 'HTTP', 'Distributed Systems', 'Security'],
            'body' => <<<'HTML'
<p><strong>Rate limiting is not only for blocking bots.</strong> It protects databases and dependencies from bursts, distributes resources fairly between tenants, controls API costs, and helps a system degrade predictably under load. A poorly designed limiter can instead block thousands of legitimate users behind NAT or become a new single point of failure.</p>
<p>This guide moves from algorithm selection to distributed Redis enforcement, with the goal of a policy that is explainable, observable, and consistent across application instances.</p>

<h2>Rate limits, quotas, and concurrency limits</h2>
<ul>
<li><strong>Rate limit:</strong> requests within an interval, such as 100 per minute.</li>
<li><strong>Quota:</strong> total usage over a longer billing cycle.</li>
<li><strong>Concurrency limit:</strong> operations currently in progress.</li>
</ul>
<p>A file-upload API may need all three: rate limiting for spam, quota for cost, and concurrency control for CPU and I/O. A minute counter should not replace every mechanism.</p>

<h2>1. Define the goal before choosing an algorithm</h2>
<p>Identify the protected resource, the subject (user, API key, tenant, IP, or device), acceptable burst size, regional consistency requirement, and failure behavior when Redis is unavailable. Tie policy to cost: a cached read should not consume the same budget as report export or AI inference.</p>

<h2>2. Choose safe identities and keys</h2>
<p>Prefer authenticated tenant, user, or API-key IDs. IP is secondary because NAT may group an entire office while attackers can rotate proxies.</p>
<pre><code>rl:v1:{tenant_id}:{route_group}:{window}</code></pre>
<p>Do not place raw API keys, emails, or personal data in Redis keys or logs. Trust <code>X-Forwarded-For</code> only from controlled proxies. Production commonly layers coarse IP limits at CDN/WAF, user or API-key limits at the gateway, tenant budgets, and global dependency protection.</p>

<h2>3. Fixed window: simple with boundary bursts</h2>
<pre><code>bucket = floor(current_time / 60)
key = "rl:user:42:" + bucket
count = INCR(key)
EXPIRE(key, 120)
allow = count &lt;= 100</code></pre>
<p>This is O(1), memory-efficient, and operationally simple. A client can still send 100 requests at the end of one minute and another 100 at the beginning of the next. Execute <code>INCR</code> and <code>EXPIRE</code> atomically through a transaction or script so a crash cannot leak a permanent key.</p>

<h2>4. Sliding window log: accurate but memory-intensive</h2>
<pre><code>ZREMRANGEBYSCORE key 0 (now - window)
ZADD key now unique_request_id
ZCARD key
EXPIRE key window</code></pre>
<p>A sorted set stores each request timestamp. It provides an accurate rolling window but costs memory proportional to traffic and needs unique members for same-millisecond requests. Use it for sensitive moderate-volume endpoints, not millions of continuously active keys.</p>

<h2>5. Sliding window counter: a practical compromise</h2>
<pre><code>estimated = current_count
          + previous_count * remaining_fraction</code></pre>
<p>Interpolating current and previous counters uses less memory than a log and smooths boundaries better than a fixed window. It is approximate, which is often acceptable for general request quotas.</p>

<h2>6. Token bucket: useful when legitimate bursts exist</h2>
<p>A bucket has a <code>capacity</code> and refills at <code>refill_rate</code>. Requests consume one or more tokens. A capacity of 60 with one token per second allows a 60-request burst after idle time while sustaining one request per second over the long term. Expensive operations can cost more tokens.</p>

<h2>7. Atomic token bucket with Redis Lua</h2>
<pre><code>local key = KEYS[1]
local capacity = tonumber(ARGV[1])
local refill_rate = tonumber(ARGV[2])
local now_ms = tonumber(ARGV[3])
local cost = tonumber(ARGV[4])

local values = redis.call('HMGET', key, 'tokens', 'updated_at')
local tokens = tonumber(values[1]) or capacity
local updated_at = tonumber(values[2]) or now_ms

local elapsed = math.max(0, now_ms - updated_at) / 1000
tokens = math.min(capacity, tokens + elapsed * refill_rate)

local allowed = 0
if tokens &gt;= cost then
    tokens = tokens - cost
    allowed = 1
end

redis.call('HSET', key, 'tokens', tokens, 'updated_at', now_ms)
redis.call('PEXPIRE', key, math.ceil((capacity / refill_rate) * 2000))

return { allowed, math.floor(tokens) }</code></pre>
<p>The read–decide–write cycle must be atomic so concurrent requests cannot spend the same token. Use a consistent clock or Redis time to reduce skew. Keep scripts short because Redis blocks other activity while they execute. In Redis Cluster, all keys in one invocation must share a hash slot; one key per subject keeps this manageable.</p>

<h2>8. Where should enforcement live?</h2>
<ul>
<li><strong>CDN/WAF:</strong> blocks bots and volumetric abuse early but knows little business identity.</li>
<li><strong>API gateway:</strong> centralizes API-key and tenant policies.</li>
<li><strong>Application:</strong> understands users, routes, and business cost.</li>
<li><strong>Downstream service:</strong> protects its own capacity from faulty internal callers.</li>
</ul>
<p>Use layers. An application limiter is not DDoS protection because traffic already reached the stack; a CDN limiter cannot replace a business tenant quota.</p>

<h2>9. Return a useful HTTP 429 response</h2>
<pre><code>HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json
Retry-After: 12
RateLimit-Limit: 100
RateLimit-Remaining: 0
RateLimit-Reset: 12

{
  "type": "https://api.example.com/problems/rate-limit",
  "title": "Rate limit exceeded",
  "status": 429,
  "retry_after": 12
}</code></pre>
<p><code>Retry-After</code> tells a client when to retry. RFC 9333 defines RateLimit fields for limit, remaining quota, and reset; keep their semantics consistent when supported and never treat headers as a security control.</p>
<p>Clients should honor <code>Retry-After</code>, use exponential backoff with jitter, cap retries, and avoid synchronizing every retry at the same instant.</p>

<h2>10. Treat failure classes deliberately</h2>
<p>The limiter usually runs before the handler, so an allowed request consumes budget even if the response is 4xx or 5xx. Refunding tokens adds races and can be abused. Instead, design separate budgets: strict account-plus-IP limits for failed logins, charge validation failures to deter spam, give health checks a distinct policy, and use concurrency/backpressure for queued work.</p>

<h2>11. Redis failure: fail open or fail closed?</h2>
<p><strong>Fail-open</strong> allows requests when the limiter is unavailable. It preserves availability for low-risk reads but may overload dependencies. <strong>Fail-closed</strong> rejects requests and fits login, OTP, payment, or hard cost controls, but makes Redis part of endpoint availability.</p>
<p>A hybrid can combine very short Redis timeouts, a local emergency limiter per instance, a circuit breaker, and route-specific policy. Never bypass silently; emit metrics and alerts whenever fallback activates.</p>

<h2>12. Hot keys, memory, and multiple regions</h2>
<p>One global key can become hot. Distribute limits by tenant and route, or allocate regional budgets when perfect global consistency is unnecessary. Strong multi-region synchronization increases latency; independent regional budgets can temporarily exceed the global target, so reserve a safety margin.</p>
<p>Every temporary key needs TTL. Monitor memory, eviction, command latency, and hit rate. If limiter keys are evicted as ordinary cache entries, clients may receive an early budget reset.</p>

<h2>13. Observability</h2>
<ul>
<li>Allowed and rejected requests by route, plan, and region.</li>
<li>429 ratio and number of subjects reaching limits.</li>
<li>Redis latency, timeout, errors, memory, and eviction.</li>
<li>Lua/function execution time and hot keys.</li>
<li>Fail-open, fail-closed, and local-fallback activations.</li>
<li>Correlation between rejection, downstream latency, and saturation.</li>
</ul>
<p>Avoid raw user IDs in high-cardinality metrics. Sample logs and hash identities; keep dashboard dimensions bounded.</p>

<h2>14. Test before production</h2>
<ol>
<li>Unit-test window boundaries, refill math, and weighted cost.</li>
<li>Concurrency-test many requests against one key.</li>
<li>Load-test many keys and a single hot key.</li>
<li>Chaos-test Redis timeout, failover, and connection-pool exhaustion.</li>
<li>Verify TTL, memory growth, and cleanup.</li>
<li>Test client behavior for 429, Retry-After, and jitter.</li>
<li>Canary a high threshold in observation-only mode before enforcement.</li>
</ol>

<h2>Design checklist</h2>
<ul>
<li>Policy reflects real resource cost.</li>
<li>Identity favors user, API key, or tenant rather than IP alone.</li>
<li>The algorithm matches burst and accuracy requirements.</li>
<li>Redis decisions are atomic and every key expires.</li>
<li>429 responses provide consistent retry guidance.</li>
<li>Failure behavior is route-specific and observable.</li>
<li>Limits are versioned, feature-flagged, and reversible.</li>
<li>Dashboards connect rejection to dependency health.</li>
</ul>

<h2>Conclusion</h2>
<p>Good rate limiting coordinates capacity rather than placing an arbitrary number in front of an API. Start with identity and cost, choose fixed window, sliding window, or token bucket according to burst behavior, enforce decisions atomically in Redis, return useful 429 responses, and plan for Redis failure itself. Observable policies and gradual rollout protect the system without unnecessarily punishing legitimate users.</p>

<h2>References</h2>
<ul><li><a href="https://www.rfc-editor.org/rfc/rfc9333.html" target="_blank" rel="noopener noreferrer">RFC 9333: RateLimit Fields for HTTP</a></li><li><a href="https://redis.io/docs/latest/develop/use-cases/rate-limiter/" target="_blank" rel="noopener noreferrer">Redis: Rate limiter use case</a></li><li><a href="https://redis.io/docs/latest/develop/programmability/" target="_blank" rel="noopener noreferrer">Redis programmability and atomic execution</a></li></ul>
HTML,
        ],
    ],
];
