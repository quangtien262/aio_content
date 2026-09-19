<?php

return [
    'laravel-queue-production.html' => [
        'vi' => [
            'title' => 'Laravel Queue trong production: Redis, Horizon, retry và xử lý job an toàn',
            'slug' => 'laravel-queue-production',
            'image' => 'laravel-queue-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Laravel Queue production với Redis và Horizon',
            'meta_keywords' => 'Laravel Queue, Laravel Horizon, Redis, queue production, retry job, failed jobs, idempotency',
            'meta_description' => 'Hướng dẫn vận hành Laravel Queue với Redis và Horizon: cấu hình worker, timeout, retry, idempotency, failed jobs và deploy an toàn.',
            'tags' => ['Laravel', 'PHP', 'Queue', 'Redis', 'Horizon', 'Backend', 'DevOps', 'Production'],
            'body' => <<<'HTML'
<p><strong>Queue giúp request Laravel trả về nhanh hơn, nhưng đưa công việc sang nền không tự động làm hệ thống đáng tin cậy.</strong> Trong production, worker có thể dừng giữa chừng, API bên ngoài có thể timeout và cùng một job có thể được xử lý lại. Một thiết kế tốt phải chủ động kiểm soát retry, timeout, tính idempotent, tài nguyên và khả năng quan sát.</p>

<h2>Khi nào nên đưa công việc vào queue?</h2>
<p>Queue phù hợp với tác vụ không cần hoàn tất trước khi trả response: gửi email, tạo báo cáo, xử lý ảnh, đồng bộ dữ liệu, phát webhook hoặc nhập file lớn. Những thao tác quyết định trực tiếp kết quả mà người dùng đang chờ, chẳng hạn xác thực quyền hay giữ chỗ tồn kho, thường vẫn cần xử lý đồng bộ hoặc một thiết kế nghiệp vụ riêng.</p>
<blockquote>Queue là ranh giới xử lý bất đồng bộ, không phải nơi giấu mọi đoạn mã chậm.</blockquote>

<h2>Chọn Redis và cài Laravel Horizon</h2>
<p>Horizon cung cấp dashboard và cấu hình worker cho Laravel Queue dùng Redis. Tài liệu Laravel hiện tại yêu cầu Horizon sử dụng kết nối Redis và lưu ý Horizon chưa tương thích với Redis Cluster. Cài đặt bằng các lệnh sau:</p>
<pre><code>composer require laravel/horizon
php artisan horizon:install
php artisan migrate</code></pre>
<p>Đặt queue connection trong môi trường production:</p>
<pre><code>QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379</code></pre>
<p>Redis không nên được mở trực tiếp ra Internet. Hãy giới hạn mạng, bật xác thực khi phù hợp, theo dõi bộ nhớ và thiết lập chính sách persistence dựa trên mức độ chấp nhận mất job của hệ thống.</p>

<h2>Tạo job có giới hạn rõ ràng</h2>
<p>Một job production cần giới hạn số lần thử, thời gian chạy và khoảng nghỉ giữa các lần retry. Mảng backoff giúp tăng dần thời gian chờ khi dịch vụ phụ thuộc đang lỗi tạm thời.</p>
<pre><code>&lt;?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 90;
    public bool $failOnTimeout = true;

    public function __construct(public int $invoiceId) {}

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(): void
    {
        // Gọi service với connect timeout và request timeout riêng.
    }

    public function failed(?Throwable $exception): void
    {
        // Ghi nhận ngữ cảnh và phát cảnh báo, không nuốt lỗi.
    }
}</code></pre>
<p>Chỉ retry lỗi tạm thời như timeout, 429 hoặc 5xx. Lỗi validation, thiếu dữ liệu bắt buộc hay lỗi quyền truy cập thường không tự biến mất sau lần thử tiếp theo; hãy cho job fail có chủ đích và đưa vào luồng xử lý phù hợp.</p>

<h2>Ba mốc thời gian phải khớp nhau</h2>
<p>Một cấu hình an toàn thường tuân theo thứ tự:</p>
<pre><code>job timeout &lt; Horizon supervisor timeout &lt; Redis retry_after</code></pre>
<p>Ví dụ job timeout 90 giây, Horizon timeout 100 giây và <code>retry_after</code> 120 giây. Horizon cần có cơ hội dừng worker bị treo trước khi queue coi job là mất và giao lại. Nếu timeout của worker dài hơn hoặc quá sát <code>retry_after</code>, hai worker có thể cùng xử lý một job.</p>
<p>Ở tầng trình quản lý tiến trình, <code>stopwaitsecs</code> phải lớn hơn thời gian chạy dài nhất của job để deploy hoặc restart không giết tiến trình giữa công việc hợp lệ.</p>

<h2>Dispatch sau khi transaction đã commit</h2>
<p>Nếu job phụ thuộc vào dữ liệu vừa ghi trong transaction, worker nhanh có thể chạy trước khi transaction commit và không tìm thấy bản ghi. Có thể bật <code>after_commit</code> cho queue connection hoặc chỉ định trên lần dispatch:</p>
<pre><code>DB::transaction(function () use ($order) {
    $order->markAsPaid();

    GenerateInvoice::dispatch($order->id)->afterCommit();
});</code></pre>
<p>Chỉ truyền ID và dữ liệu tối thiểu vào job. Serialize một object Eloquent cùng nhiều relationship đã load làm payload lớn, dễ giữ dữ liệu cũ và tăng áp lực lên Redis.</p>

<h2>Idempotency: phòng tuyến chống xử lý trùng</h2>
<p>Queue thường mang ngữ nghĩa giao ít nhất một lần. Job có thể hoàn tất tác động bên ngoài nhưng worker dừng trước khi xác nhận, khiến job được giao lại. Vì vậy thao tác như trừ tiền, tạo hóa đơn hoặc gửi webhook phải chịu được việc gọi lặp.</p>
<ul><li>Dùng khóa nghiệp vụ duy nhất, ví dụ <code>invoice_id + action</code>.</li><li>Tạo unique constraint trong database thay vì chỉ kiểm tra bằng mã ứng dụng.</li><li>Lưu trạng thái và cập nhật nghiệp vụ trong cùng transaction khi có thể.</li><li>Dùng transactional outbox nếu cần phối hợp database với message hoặc API bên ngoài.</li><li>Có thể dùng <code>ShouldBeUnique</code> để hạn chế enqueue trùng, nhưng không coi đó là bảo đảm “exactly once”.</li></ul>

<h2>Chia queue theo độ ưu tiên và tài nguyên</h2>
<p>Không nên để email, ảnh nặng và tác vụ thanh toán cạnh tranh trong một queue. Một cách chia dễ vận hành là <code>high</code>, <code>default</code>, <code>notifications</code> và <code>media</code>. Trong <code>config/horizon.php</code>, tạo supervisor riêng cho workload cần tài nguyên hoặc độ ưu tiên khác nhau.</p>
<pre><code>'production' =&gt; [
    'supervisor-critical' =&gt; [
        'connection' =&gt; 'redis',
        'queue' =&gt; ['high'],
        'balance' =&gt; 'simple',
        'processes' =&gt; 4,
        'tries' =&gt; 3,
        'timeout' =&gt; 100,
    ],
    'supervisor-default' =&gt; [
        'connection' =&gt; 'redis',
        'queue' =&gt; ['default', 'notifications'],
        'balance' =&gt; 'auto',
        'minProcesses' =&gt; 2,
        'maxProcesses' =&gt; 10,
        'balanceMaxShift' =&gt; 1,
        'balanceCooldown' =&gt; 3,
        'tries' =&gt; 5,
        'timeout' =&gt; 100,
        'backoff' =&gt; [10, 30, 120],
    ],
],</code></pre>
<p><code>auto</code> phân bổ worker theo tải, nhưng không bảo đảm ưu tiên tuyệt đối giữa các queue. Workload quan trọng nên có supervisor riêng và giới hạn worker dựa trên CPU, RAM, kết nối database và rate limit của dịch vụ phụ thuộc.</p>

<h2>Chạy Horizon như một dịch vụ</h2>
<p>Production cần Supervisor, systemd hoặc trình quản lý tiến trình tương đương để tự khởi động lại Horizon khi máy reboot hoặc tiến trình lỗi. Tiến trình chính là:</p>
<pre><code>php artisan horizon</code></pre>
<p>Dashboard Horizon chứa thông tin vận hành, vì vậy phải giới hạn quyền truy cập qua gate trong <code>HorizonServiceProvider</code>, đồng thời đặt sau HTTPS và cơ chế xác thực của ứng dụng.</p>

<h2>Deploy mà không để worker chạy mã cũ</h2>
<p>Worker là tiến trình sống lâu và không tự nạp lại source code sau mỗi request. Với Laravel 13, có thể dùng <code>php artisan reload</code> trong quy trình deploy để reload các dịch vụ dài hạn. Riêng Horizon, <code>php artisan horizon:terminate</code> yêu cầu tiến trình hiện tại thoát nhẹ nhàng sau khi hoàn tất job; trình quản lý tiến trình sẽ khởi động phiên bản mới.</p>
<pre><code>php artisan migrate --force
php artisan optimize
php artisan horizon:terminate</code></pre>
<p>Không deploy thay đổi không tương thích với payload job đang chờ. Khi đổi constructor hoặc định dạng dữ liệu, hãy hỗ trợ payload cũ trong giai đoạn chuyển tiếp hoặc làm cạn queue trước khi phát hành.</p>

<h2>Xử lý failed jobs và quan sát hệ thống</h2>
<p>Đội vận hành cần biết job thất bại vì đâu trước khi retry hàng loạt:</p>
<pre><code>php artisan queue:failed
php artisan queue:retry &lt;job-id&gt;
php artisan queue:retry all
php artisan horizon:forget &lt;job-id&gt;</code></pre>
<p>Dashboard và cảnh báo nên theo dõi độ trễ queue, tuổi của job lâu nhất, throughput, runtime p95/p99, số lần retry, failed jobs, worker restart, bộ nhớ Redis và lỗi từ dịch vụ phụ thuộc. Thiết lập snapshot Horizon theo scheduler và prune dữ liệu theo thời gian lưu giữ phù hợp.</p>

<h2>Kiểm thử trước khi đưa lên production</h2>
<ul><li>Dùng <code>Queue::fake()</code> để xác minh job được dispatch đúng queue và đúng dữ liệu.</li><li>Kiểm thử <code>handle()</code> riêng với lỗi tạm thời và lỗi vĩnh viễn.</li><li>Chạy integration test với Redis và worker thật cho đường đi quan trọng.</li><li>Mô phỏng worker dừng sau tác động nghiệp vụ nhưng trước lúc ACK.</li><li>Kiểm tra nhiều job cùng chạy trên một khóa nghiệp vụ.</li><li>Thử deploy khi queue đang có tải và xác minh worker mới dùng đúng phiên bản mã.</li></ul>

<h2>Checklist production</h2>
<ol><li>Redis chỉ truy cập từ mạng tin cậy và có giám sát bộ nhớ.</li><li>Mọi job có timeout, số lần thử và backoff hữu hạn.</li><li>Quan hệ timeout tuân theo <code>job &lt; Horizon &lt; retry_after</code>.</li><li>Job phụ thuộc transaction chỉ dispatch sau commit.</li><li>Tác động nghiệp vụ quan trọng có idempotency key và unique constraint.</li><li>Queue nặng hoặc quan trọng có supervisor riêng.</li><li>Horizon chạy dưới process monitor và dashboard được bảo vệ.</li><li>Deploy có bước reload hoặc terminate worker nhẹ nhàng.</li><li>Failed jobs có cảnh báo, người chịu trách nhiệm và quy trình replay.</li></ol>

<h2>Kết luận</h2>
<p>Laravel Queue đáng tin cậy không nằm ở số lượng worker, mà ở các giới hạn được thiết kế rõ: job có thể retry nhưng không tạo tác động trùng, worker có thể restart mà không mất việc, và đội vận hành nhìn thấy queue đang chậm ở đâu. Redis và Horizon cung cấp nền tảng tốt; tính an toàn đến từ timeout hợp lý, idempotency, chia tải và quy trình deploy có kiểm soát.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://laravel.com/framework/docs/queues" target="_blank" rel="noopener noreferrer">Laravel Documentation: Queues</a></li><li><a href="https://laravel.com/framework/docs/horizon" target="_blank" rel="noopener noreferrer">Laravel Documentation: Horizon</a></li><li><a href="https://laravel.com/framework/docs/deployment" target="_blank" rel="noopener noreferrer">Laravel Documentation: Deployment</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Laravel Queues in Production: Redis, Horizon, Retries, and Safe Job Processing',
            'slug' => 'laravel-queues-in-production',
            'image' => 'laravel-queue-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Laravel Queues in Production with Redis and Horizon',
            'meta_keywords' => 'Laravel Queue, Laravel Horizon, Redis, production queues, job retries, failed jobs, idempotency',
            'meta_description' => 'Run Laravel Queues safely with Redis and Horizon: workers, timeouts, retries, idempotency, failed jobs, monitoring, and deployment.',
            'tags' => ['Laravel', 'PHP', 'Queue', 'Redis', 'Horizon', 'Backend', 'DevOps', 'Production'],
            'body' => <<<'HTML'
<p><strong>Queues make Laravel requests faster, but moving work into the background does not automatically make a system reliable.</strong> In production, workers can stop mid-job, external APIs can time out, and the same job can be delivered again. A sound design explicitly controls retries, timeouts, idempotency, resources, and observability.</p>

<h2>What belongs on a queue?</h2>
<p>Queues fit work that does not need to finish before the response: sending email, generating reports, processing images, synchronizing data, delivering webhooks, or importing large files. Operations that directly determine the result a user is waiting for, such as authorization or inventory reservation, often remain synchronous or need a dedicated business workflow.</p>
<blockquote>A queue is an asynchronous processing boundary, not a place to hide every slow code path.</blockquote>

<h2>Choose Redis and install Laravel Horizon</h2>
<p>Horizon provides a dashboard and worker configuration for Redis-powered Laravel queues. Current Laravel documentation requires Horizon to use Redis and notes that Horizon is not currently compatible with Redis Cluster.</p>
<pre><code>composer require laravel/horizon
php artisan horizon:install
php artisan migrate</code></pre>
<p>Configure the production connection:</p>
<pre><code>QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379</code></pre>
<p>Do not expose Redis directly to the Internet. Restrict network access, enable authentication where appropriate, monitor memory, and choose a persistence policy that reflects how much job loss the system can tolerate.</p>

<h2>Give every job explicit limits</h2>
<p>A production job should define its attempt limit, runtime limit, and delay between retries. An array backoff increases the delay while a dependency is temporarily unhealthy.</p>
<pre><code>&lt;?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 90;
    public bool $failOnTimeout = true;

    public function __construct(public int $invoiceId) {}

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(): void
    {
        // Call the service with separate connection and request timeouts.
    }

    public function failed(?Throwable $exception): void
    {
        // Record context and alert; do not swallow the failure.
    }
}</code></pre>
<p>Retry transient faults such as timeouts, 429 responses, and 5xx errors. Validation errors, missing required data, or authorization failures usually will not heal on another attempt; fail them deliberately and route them to the appropriate workflow.</p>

<h2>Align the three timeout boundaries</h2>
<p>A safe configuration generally follows this order:</p>
<pre><code>job timeout &lt; Horizon supervisor timeout &lt; Redis retry_after</code></pre>
<p>For example, use a 90-second job timeout, a 100-second Horizon timeout, and a 120-second <code>retry_after</code>. Horizon needs time to terminate a stuck worker before the queue considers the job lost and delivers it again. If the worker timeout exceeds or sits too close to <code>retry_after</code>, two workers may process the same job.</p>
<p>At the process-manager layer, <code>stopwaitsecs</code> must exceed the longest valid job runtime so a deployment or restart does not kill useful work midway.</p>

<h2>Dispatch only after the transaction commits</h2>
<p>When a job depends on data written inside a transaction, a fast worker can run before commit and fail to find the record. Enable <code>after_commit</code> on the queue connection or specify it on dispatch:</p>
<pre><code>DB::transaction(function () use ($order) {
    $order->markAsPaid();

    GenerateInvoice::dispatch($order->id)->afterCommit();
});</code></pre>
<p>Pass IDs and minimal data to jobs. Serializing an Eloquent model with a large loaded relationship graph creates large payloads, preserves stale state, and puts more pressure on Redis.</p>

<h2>Idempotency is the duplicate-processing defense</h2>
<p>Queues commonly provide at-least-once delivery. A job can complete an external effect and then lose its acknowledgement when the worker stops, causing redelivery. Charging money, creating invoices, and sending webhooks must therefore tolerate repeated execution.</p>
<ul><li>Use a unique business key such as <code>invoice_id + action</code>.</li><li>Enforce it with a database unique constraint, not only an application check.</li><li>Commit state and business changes in one transaction when possible.</li><li>Use a transactional outbox when coordinating database state with a message or external API.</li><li>Use <code>ShouldBeUnique</code> to reduce duplicate dispatches, but do not treat it as an exactly-once guarantee.</li></ul>

<h2>Separate queues by priority and resource profile</h2>
<p>Email, heavy media work, and payment-related jobs should not all compete in one queue. A practical split is <code>high</code>, <code>default</code>, <code>notifications</code>, and <code>media</code>. Define separate supervisors in <code>config/horizon.php</code> for workloads with different resource or priority needs.</p>
<pre><code>'production' =&gt; [
    'supervisor-critical' =&gt; [
        'connection' =&gt; 'redis',
        'queue' =&gt; ['high'],
        'balance' =&gt; 'simple',
        'processes' =&gt; 4,
        'tries' =&gt; 3,
        'timeout' =&gt; 100,
    ],
    'supervisor-default' =&gt; [
        'connection' =&gt; 'redis',
        'queue' =&gt; ['default', 'notifications'],
        'balance' =&gt; 'auto',
        'minProcesses' =&gt; 2,
        'maxProcesses' =&gt; 10,
        'balanceMaxShift' =&gt; 1,
        'balanceCooldown' =&gt; 3,
        'tries' =&gt; 5,
        'timeout' =&gt; 100,
        'backoff' =&gt; [10, 30, 120],
    ],
],</code></pre>
<p><code>auto</code> allocates workers according to load, but it does not enforce strict queue priority. Give critical workloads dedicated supervisors, and set worker limits according to CPU, memory, database connections, and dependency rate limits.</p>

<h2>Run Horizon as a managed service</h2>
<p>Production needs Supervisor, systemd, or an equivalent process manager to restart Horizon after machine reboots and process failures. The managed command is:</p>
<pre><code>php artisan horizon</code></pre>
<p>The Horizon dashboard exposes operational information. Restrict it through the gate in <code>HorizonServiceProvider</code>, and place it behind HTTPS and application authentication.</p>

<h2>Deploy without leaving workers on old code</h2>
<p>Workers are long-lived processes and do not reload source code after each request. Laravel 13 can use <code>php artisan reload</code> during deployment to reload long-running services. For Horizon specifically, <code>php artisan horizon:terminate</code> asks current processes to exit gracefully after finishing their job, and the process manager starts fresh instances.</p>
<pre><code>php artisan migrate --force
php artisan optimize
php artisan horizon:terminate</code></pre>
<p>Do not deploy changes that are incompatible with queued payloads. When a constructor or payload format changes, support the old version during a transition window or drain the queue before release.</p>

<h2>Operate failed jobs and observe the system</h2>
<p>Operators should understand why a job failed before retrying it in bulk:</p>
<pre><code>php artisan queue:failed
php artisan queue:retry &lt;job-id&gt;
php artisan queue:retry all
php artisan horizon:forget &lt;job-id&gt;</code></pre>
<p>Dashboards and alerts should track queue wait time, oldest-job age, throughput, p95/p99 runtime, retries, failed jobs, worker restarts, Redis memory, and dependency failures. Schedule Horizon snapshots and prune metrics and failed-job data according to an explicit retention policy.</p>

<h2>Test before production</h2>
<ul><li>Use <code>Queue::fake()</code> to assert that a job is dispatched to the right queue with the right data.</li><li>Test <code>handle()</code> directly for transient and permanent failures.</li><li>Run integration tests with real Redis and workers for critical paths.</li><li>Simulate a worker stopping after a business effect but before acknowledgement.</li><li>Run concurrent jobs against the same business key.</li><li>Deploy while the queue is active and confirm new workers use the intended code version.</li></ul>

<h2>Production checklist</h2>
<ol><li>Redis is reachable only from trusted networks and its memory is monitored.</li><li>Every job has finite timeouts, attempts, and backoff.</li><li>Timeouts follow <code>job &lt; Horizon &lt; retry_after</code>.</li><li>Jobs that depend on transactions are dispatched after commit.</li><li>Critical business effects use idempotency keys and unique constraints.</li><li>Heavy or critical queues have dedicated supervisors.</li><li>Horizon runs under a process monitor and its dashboard is protected.</li><li>Deployment gracefully reloads or terminates workers.</li><li>Failed jobs have alerts, ownership, and a controlled replay process.</li></ol>

<h2>Conclusion</h2>
<p>A reliable Laravel queue is not defined by worker count but by explicit boundaries: retries do not duplicate business effects, workers restart without losing work, and operators can see where latency is building. Redis and Horizon provide a strong foundation; safety comes from aligned timeouts, idempotency, workload isolation, and a controlled deployment process.</p>

<h2>References</h2>
<ul><li><a href="https://laravel.com/framework/docs/queues" target="_blank" rel="noopener noreferrer">Laravel Documentation: Queues</a></li><li><a href="https://laravel.com/framework/docs/horizon" target="_blank" rel="noopener noreferrer">Laravel Documentation: Horizon</a></li><li><a href="https://laravel.com/framework/docs/deployment" target="_blank" rel="noopener noreferrer">Laravel Documentation: Deployment</a></li></ul>
HTML,
        ],
    ],
];
