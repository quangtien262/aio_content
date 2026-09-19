<?php

return [
    'laravel-reverb-production.html' => [
        'vi' => [
            'title' => 'Laravel Reverb thực chiến: Xây realtime WebSocket và vận hành production',
            'slug' => 'laravel-reverb-websocket-production',
            'image' => 'laravel-reverb-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Laravel Reverb WebSocket trong production',
            'meta_keywords' => 'Laravel Reverb, WebSocket Laravel, Laravel Echo, broadcasting, private channel, realtime Laravel, Reverb production',
            'meta_description' => 'Cài Laravel Reverb, phát sự kiện realtime, bảo vệ private channel và triển khai production với queue, Nginx, Supervisor, TLS, Pulse và Redis.',
            'tags' => ['Laravel', 'Reverb', 'WebSocket', 'Laravel Echo', 'Broadcasting', 'Redis', 'Realtime', 'PHP'],
            'body' => <<<'HTML'
<p><strong>Laravel Reverb đưa WebSocket server vào hệ sinh thái Laravel, giúp xây thông báo, trạng thái đơn hàng, dashboard và cộng tác realtime mà không phải polling liên tục.</strong> Demo local khá nhanh, nhưng production cần thêm channel authorization, queue worker, TLS, reverse proxy, process manager, giới hạn kết nối và giám sát.</p>

<h2>Luồng dữ liệu cần hiểu</h2>
<ol><li>Trình duyệt dùng Laravel Echo mở WebSocket tới Reverb.</li><li>Với private/presence channel, ứng dụng Laravel xác thực yêu cầu subscription.</li><li>Backend dispatch event triển khai <code>ShouldBroadcast</code>.</li><li>Queue worker xử lý broadcast job và gửi message tới Reverb.</li><li>Reverb chuyển message tới các connection đã subscribe đúng channel.</li></ol>
<blockquote>Reverb giữ kết nối và phân phối message; quyền được nghe dữ liệu vẫn phải do ứng dụng quyết định.</blockquote>

<h2>1. Cài broadcasting với Reverb</h2>
<pre><code>php artisan install:broadcasting --reverb
npm install
npm run build</code></pre>
<p>Lệnh cài tạo cấu hình broadcasting, Reverb, <code>routes/channels.php</code>, thông tin môi trường và scaffolding Echo. Không commit <code>REVERB_APP_SECRET</code>. Phân biệt hai nhóm biến: <code>REVERB_SERVER_HOST/PORT</code> là nơi process lắng nghe; <code>REVERB_HOST/PORT/SCHEME</code> là địa chỉ ứng dụng và browser sử dụng.</p>
<pre><code>BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=ws.example.com
REVERB_PORT=443
REVERB_SCHEME=https</code></pre>

<h2>2. Tạo một broadcast event</h2>
<pre><code>&lt;?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders.'.$this-&gt;order-&gt;id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' =&gt; $this-&gt;order-&gt;id,
            'status' =&gt; $this-&gt;order-&gt;status,
            'updated_at' =&gt; $this-&gt;order-&gt;updated_at?-&gt;toISOString(),
        ];
    }
}</code></pre>
<p>Chỉ gửi field frontend thực sự cần. Không serialize cả model có quan hệ, email nội bộ hoặc dữ liệu nhạy cảm. Broadcast mặc định được đưa vào queue; hãy chạy worker để event không nằm chờ vô thời hạn.</p>

<h2>3. Authorization cho private channel</h2>
<p>Trong <code>routes/channels.php</code>:</p>
<pre><code>use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return Order::query()
        -&gt;whereKey($orderId)
        -&gt;where('user_id', $user-&gt;id)
        -&gt;exists();
});</code></pre>
<p>Public channel cho phép bất kỳ client nào subscribe nên chỉ dùng cho dữ liệu thực sự công khai. Private channel yêu cầu xác thực và callback phải kiểm tra quyền trên đúng resource. Presence channel còn trả thông tin thành viên; chỉ trả dữ liệu tối thiểu như ID và tên hiển thị.</p>
<pre><code>php artisan channel:list</code></pre>

<h2>4. Lắng nghe bằng Laravel Echo</h2>
<pre><code>window.Echo.private(`orders.${orderId}`)
    .listen('OrderStatusUpdated', (event) =&gt; {
        updateOrderStatus(event.status);
    });</code></pre>
<p>Nếu định nghĩa <code>broadcastAs()</code>, listener phải dùng tên tùy chỉnh với dấu chấm đầu, ví dụ <code>.listen('.order.updated', ...)</code>. Khi component bị hủy, rời channel để tránh listener trùng và rò bộ nhớ.</p>

<h2>5. Dispatch đúng thời điểm giao dịch</h2>
<pre><code>$order-&gt;update(['status' =&gt; 'shipped']);

OrderStatusUpdated::dispatch($order-&gt;fresh());</code></pre>
<p>Nếu event được dispatch trong database transaction, worker có thể chạy trước khi commit và đọc trạng thái cũ. Dùng after-commit của queue hoặc triển khai <code>ShouldDispatchAfterCommit</code> cho event khi tính nhất quán quan trọng. Thiết kế payload có ID, version hoặc timestamp để client bỏ qua event đến trễ.</p>

<h2>6. Chạy local và kiểm tra</h2>
<pre><code>php artisan reverb:start --debug
php artisan queue:work
npm run dev</code></pre>
<p><code>--debug</code> hữu ích ở local nhưng tạo log lớn, không nên bật thường xuyên trong production. Kiểm tra tab Network/WebSocket của trình duyệt, response của <code>/broadcasting/auth</code>, log queue và <code>failed_jobs</code>. Nếu WebSocket kết nối nhưng không có event, queue worker là điểm cần kiểm tra đầu tiên.</p>

<h2>7. Đặt Reverb sau Nginx và TLS</h2>
<p>Trong production, để Reverb nghe ở localhost và Nginx kết thúc TLS trên cổng 443:</p>
<pre><code>server {
    listen 443 ssl http2;
    server_name ws.example.com;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_read_timeout 60s;
        proxy_pass http://127.0.0.1:8080;
    }
}</code></pre>
<p>Reverb dùng URI <code>/app</code> cho WebSocket và <code>/apps</code> cho API, nên proxy phải phục vụ cả hai. Chỉ mở 80/443 ra Internet; cổng 8080 nên bị giới hạn nội bộ. Cài chứng chỉ hợp lệ cho hostname WebSocket và dùng <code>wss://</code> khi website chạy HTTPS.</p>

<h2>8. Giới hạn origin và bảo vệ channel</h2>
<p>Trong <code>config/reverb.php</code>, đặt <code>allowed_origins</code> thành các domain frontend cụ thể, không dùng <code>*</code> trên production nếu không thực sự cần. Origin check không thay thế authentication; attacker vẫn có thể gọi backend bằng công cụ riêng nếu authorization sai.</p>
<ul><li>Dùng private channel cho dữ liệu người dùng hoặc tenant.</li><li>Kiểm tra tenant/resource trong callback, không chỉ kiểm tra đã đăng nhập.</li><li>Rate-limit endpoint tạo event và các client event.</li><li>Không đưa secret, token hoặc model đầy đủ vào payload.</li><li>Giới hạn kích thước message và số subscription hợp lý.</li></ul>

<h2>9. Chạy Reverb và queue bằng process manager</h2>
<p>Reverb là process sống lâu. Dùng Supervisor hoặc systemd để tự khởi động lại. Ví dụ Supervisor:</p>
<pre><code>[program:app-reverb]
command=php /var/www/app/artisan reverb:start
directory=/var/www/app
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/app-reverb.log
stopasgroup=true
killasgroup=true</code></pre>
<p>Queue worker là process riêng và cũng phải được giám sát. Sau deploy, chạy <code>php artisan reverb:restart</code> để kết thúc connection nhẹ nhàng rồi process manager khởi động phiên bản mới. Đồng thời restart queue worker để nạp code mới.</p>

<h2>10. Capacity, monitoring và scale ngang</h2>
<p>Mỗi WebSocket giữ memory và một file descriptor. Kiểm tra <code>ulimit -n</code>, giới hạn Nginx và Supervisor. Event loop mặc định dựa trên <code>stream_select</code> thường bị giới hạn khoảng 1.024 file; tài liệu Laravel khuyến nghị event loop <code>ext-uv</code> khi vượt khoảng 1.000 connection đồng thời.</p>
<p>Tích hợp Laravel Pulse để theo dõi connection và message; chỉ chạy daemon <code>pulse:check</code> trên một node khi scale ngang. Cảnh báo thêm memory, CPU, reconnect rate, queue lag, failed jobs và lỗi 4xx/5xx của endpoint authorization.</p>
<p>Khi một node không đủ, bật <code>REVERB_SCALING_ENABLED=true</code>, dùng Redis tập trung cho pub/sub và đặt nhiều Reverb node sau load balancer. Kiểm thử tải bằng mô hình connection thực tế, không chỉ số message/giây.</p>

<h2>Những lỗi production thường gặp</h2>
<ul><li><strong>WebSocket 404/502:</strong> Nginx chưa proxy đúng <code>/app</code>, process Reverb dừng hoặc sai port.</li><li><strong>403 subscription:</strong> session/cookie, CSRF, guard hoặc callback channel không khớp.</li><li><strong>Event không đến:</strong> queue worker chưa chạy, broadcast job thất bại hoặc sai channel/event name.</li><li><strong>Chỉ lỗi trên HTTPS:</strong> Echo vẫn dùng <code>ws://</code>, certificate sai hoặc proxy thiếu header Upgrade.</li><li><strong>Deploy xong vẫn chạy code cũ:</strong> chưa restart Reverb và queue worker.</li><li><strong>Rớt connection khi tải tăng:</strong> chạm giới hạn file descriptor, event loop, Nginx, memory hoặc port.</li></ul>

<h2>Checklist trước khi ra mắt</h2>
<ol><li>Private/presence channel có test authorization cho phép và từ chối.</li><li>Payload tối thiểu, không chứa secret hoặc dữ liệu tenant khác.</li><li>Queue worker, Reverb và process manager đều hoạt động.</li><li>WSS qua certificate hợp lệ; port nội bộ không public.</li><li><code>allowed_origins</code> chỉ chứa frontend hợp lệ.</li><li>Deploy script restart Reverb và queue worker.</li><li>Đã đo connection đồng thời, queue lag, memory và reconnect.</li><li>Có dashboard, cảnh báo và kế hoạch scale Redis/load balancer.</li></ol>

<h2>Kết luận</h2>
<p>Laravel Reverb làm phần realtime hòa vào event và broadcasting của Laravel, nhưng độ tin cậy đến từ toàn bộ đường truyền: authorization đúng, queue không nghẽn, reverse proxy hỗ trợ upgrade, process được giám sát và hạ tầng có giới hạn phù hợp. Bắt đầu bằng một use case nhỏ, đo tải thật rồi scale theo connection thay vì dự đoán.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://laravel.com/docs/13.x/reverb" target="_blank" rel="noopener noreferrer">Laravel 13: Reverb</a></li><li><a href="https://laravel.com/docs/13.x/broadcasting" target="_blank" rel="noopener noreferrer">Laravel 13: Broadcasting</a></li><li><a href="https://laravel.com/docs/13.x/queues" target="_blank" rel="noopener noreferrer">Laravel 13: Queues</a></li><li><a href="https://laravel.com/docs/13.x/pulse" target="_blank" rel="noopener noreferrer">Laravel 13: Pulse</a></li><li><a href="https://laravel.com/docs/13.x/deployment" target="_blank" rel="noopener noreferrer">Laravel 13: Deployment</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Laravel Reverb in Practice: Building and Operating Production WebSockets',
            'slug' => 'laravel-reverb-websockets-production',
            'image' => 'laravel-reverb-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Laravel Reverb WebSockets in Production',
            'meta_keywords' => 'Laravel Reverb, Laravel WebSockets, Laravel Echo, broadcasting, private channel, realtime Laravel, Reverb production',
            'meta_description' => 'Install Laravel Reverb, broadcast realtime events, secure private channels, and operate production with queues, Nginx, Supervisor, Pulse, and Redis.',
            'tags' => ['Laravel', 'Reverb', 'WebSocket', 'Laravel Echo', 'Broadcasting', 'Redis', 'Realtime', 'PHP'],
            'body' => <<<'HTML'
<p><strong>Laravel Reverb brings a WebSocket server into the Laravel ecosystem for notifications, order status, dashboards, and collaborative interfaces without continuous polling.</strong> A local demo is quick, but production also needs channel authorization, queue workers, TLS, a reverse proxy, process management, capacity controls, and monitoring.</p>

<h2>Understand the data path</h2>
<ol><li>Laravel Echo opens a browser WebSocket to Reverb.</li><li>For private or presence channels, Laravel authorizes the subscription.</li><li>The backend dispatches an event implementing <code>ShouldBroadcast</code>.</li><li>A queue worker processes the broadcast job and publishes to Reverb.</li><li>Reverb sends the message to connections subscribed to that channel.</li></ol>
<blockquote>Reverb maintains connections and distributes messages; the application must still decide who may receive the data.</blockquote>

<h2>1. Install broadcasting with Reverb</h2>
<pre><code>php artisan install:broadcasting --reverb
npm install
npm run build</code></pre>
<p>The command creates broadcasting and Reverb configuration, <code>routes/channels.php</code>, environment credentials, and Echo scaffolding. Never commit <code>REVERB_APP_SECRET</code>. Distinguish <code>REVERB_SERVER_HOST/PORT</code>, where the process listens, from <code>REVERB_HOST/PORT/SCHEME</code>, where the application and browser connect.</p>
<pre><code>BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=ws.example.com
REVERB_PORT=443
REVERB_SCHEME=https</code></pre>

<h2>2. Create a broadcast event</h2>
<pre><code>&lt;?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders.'.$this-&gt;order-&gt;id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' =&gt; $this-&gt;order-&gt;id,
            'status' =&gt; $this-&gt;order-&gt;status,
            'updated_at' =&gt; $this-&gt;order-&gt;updated_at?-&gt;toISOString(),
        ];
    }
}</code></pre>
<p>Send only fields the frontend needs. Do not serialize entire relationship graphs, internal email addresses, or sensitive data. Broadcast events are queued by default, so run a worker or events will wait indefinitely.</p>

<h2>3. Authorize private channels</h2>
<p>In <code>routes/channels.php</code>:</p>
<pre><code>use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return Order::query()
        -&gt;whereKey($orderId)
        -&gt;where('user_id', $user-&gt;id)
        -&gt;exists();
});</code></pre>
<p>Anyone can subscribe to a public channel, so reserve it for genuinely public data. A private-channel callback must authorize the exact resource, not merely check that a user is signed in. Presence channels expose member information; return only a minimal ID and display name.</p>
<pre><code>php artisan channel:list</code></pre>

<h2>4. Listen with Laravel Echo</h2>
<pre><code>window.Echo.private(`orders.${orderId}`)
    .listen('OrderStatusUpdated', (event) =&gt; {
        updateOrderStatus(event.status);
    });</code></pre>
<p>If the event defines <code>broadcastAs()</code>, listen to the custom name with a leading dot, such as <code>.listen('.order.updated', ...)</code>. Leave the channel when a component unmounts to prevent duplicate handlers and memory leaks.</p>

<h2>5. Dispatch after consistent database state</h2>
<pre><code>$order-&gt;update(['status' =&gt; 'shipped']);

OrderStatusUpdated::dispatch($order-&gt;fresh());</code></pre>
<p>When an event is dispatched inside a database transaction, a fast worker may run before commit and read stale state. Use queue after-commit behavior or implement <code>ShouldDispatchAfterCommit</code> when consistency matters. Include an ID, version, or timestamp so clients can ignore late events.</p>

<h2>6. Run and inspect locally</h2>
<pre><code>php artisan reverb:start --debug
php artisan queue:work
npm run dev</code></pre>
<p><code>--debug</code> is useful locally but noisy in production. Inspect the browser's Network/WebSocket panel, the <code>/broadcasting/auth</code> response, queue logs, and <code>failed_jobs</code>. When the socket connects but events do not arrive, check the queue worker first.</p>

<h2>7. Put Reverb behind Nginx and TLS</h2>
<p>In production, bind Reverb locally and terminate TLS at Nginx on port 443:</p>
<pre><code>server {
    listen 443 ssl http2;
    server_name ws.example.com;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_read_timeout 60s;
        proxy_pass http://127.0.0.1:8080;
    }
}</code></pre>
<p>Reverb serves WebSockets at <code>/app</code> and API requests at <code>/apps</code>, so proxy both. Expose only ports 80 and 443; keep 8080 private. Install a valid certificate for the WebSocket hostname and use <code>wss://</code> when the site uses HTTPS.</p>

<h2>8. Restrict origins and channels</h2>
<p>Set <code>allowed_origins</code> in <code>config/reverb.php</code> to explicit frontend domains. Avoid <code>*</code> in production unless required. Origin checks do not replace authentication; authorization defects remain exploitable by non-browser clients.</p>
<ul><li>Use private channels for user or tenant data.</li><li>Verify tenant and resource membership, not only authentication.</li><li>Rate-limit endpoints that create events and client events.</li><li>Never include secrets, tokens, or complete models in payloads.</li><li>Set reasonable message-size and subscription limits.</li></ul>

<h2>9. Manage Reverb and queues as processes</h2>
<p>Reverb is long-running. Use Supervisor or systemd for automatic recovery. Example Supervisor program:</p>
<pre><code>[program:app-reverb]
command=php /var/www/app/artisan reverb:start
directory=/var/www/app
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/app-reverb.log
stopasgroup=true
killasgroup=true</code></pre>
<p>The queue worker is a separate supervised process. After deployment, run <code>php artisan reverb:restart</code> for graceful connection termination and automatic restart by the process manager. Restart queue workers so they load new code too.</p>

<h2>10. Capacity, monitoring, and horizontal scaling</h2>
<p>Every WebSocket consumes memory and a file descriptor. Inspect <code>ulimit -n</code> plus Nginx and Supervisor limits. The default <code>stream_select</code> event loop is typically constrained to about 1,024 open files; Laravel recommends an <code>ext-uv</code> loop beyond roughly 1,000 concurrent connections.</p>
<p>Laravel Pulse can track Reverb connections and messages. Run <code>pulse:check</code> on only one node in a scaled deployment. Also alert on memory, CPU, reconnect rate, queue lag, failed jobs, and authorization endpoint errors.</p>
<p>When one node is insufficient, set <code>REVERB_SCALING_ENABLED=true</code>, use central Redis pub/sub, and place several Reverb nodes behind a load balancer. Load-test realistic connection behavior, not only messages per second.</p>

<h2>Common production failures</h2>
<ul><li><strong>WebSocket 404 or 502:</strong> Nginx does not proxy <code>/app</code>, Reverb stopped, or ports differ.</li><li><strong>Subscription 403:</strong> session, cookie, CSRF, guard, or channel callback mismatch.</li><li><strong>No event:</strong> queue worker is absent, broadcast job failed, or channel/event names differ.</li><li><strong>HTTPS-only failure:</strong> Echo still uses <code>ws://</code>, the certificate is invalid, or Upgrade headers are missing.</li><li><strong>Old behavior after deploy:</strong> Reverb and queue workers were not restarted.</li><li><strong>Drops under load:</strong> file descriptor, event loop, Nginx, memory, or port limits were reached.</li></ul>

<h2>Launch checklist</h2>
<ol><li>Private and presence channels have allow and deny authorization tests.</li><li>Payloads are minimal and contain no secret or cross-tenant data.</li><li>Queue workers, Reverb, and process management are healthy.</li><li>WSS uses a valid certificate and the internal port is private.</li><li><code>allowed_origins</code> contains only valid frontends.</li><li>The deployment restarts Reverb and queue workers.</li><li>Concurrent connections, queue lag, memory, and reconnects were measured.</li><li>Dashboards, alerts, and a Redis/load-balancer scaling plan exist.</li></ol>

<h2>Conclusion</h2>
<p>Laravel Reverb makes realtime behavior fit Laravel's event and broadcasting model, but reliability depends on the entire path: correct authorization, healthy queues, WebSocket-aware proxying, supervised processes, and suitable capacity limits. Start with one focused use case, measure real connections, and scale from evidence.</p>

<h2>References</h2>
<ul><li><a href="https://laravel.com/docs/13.x/reverb" target="_blank" rel="noopener noreferrer">Laravel 13: Reverb</a></li><li><a href="https://laravel.com/docs/13.x/broadcasting" target="_blank" rel="noopener noreferrer">Laravel 13: Broadcasting</a></li><li><a href="https://laravel.com/docs/13.x/queues" target="_blank" rel="noopener noreferrer">Laravel 13: Queues</a></li><li><a href="https://laravel.com/docs/13.x/pulse" target="_blank" rel="noopener noreferrer">Laravel 13: Pulse</a></li><li><a href="https://laravel.com/docs/13.x/deployment" target="_blank" rel="noopener noreferrer">Laravel 13: Deployment</a></li></ul>
HTML,
        ],
    ],
];
