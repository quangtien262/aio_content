<?php

return [
    'laravel-sanctum-production.html' => [
        'vi' => [
            'title' => 'Laravel Sanctum thực chiến: Xác thực SPA, mobile và API token an toàn',
            'slug' => 'laravel-sanctum-xac-thuc-api-an-toan',
            'image' => 'laravel-sanctum-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Laravel Sanctum: Xác thực SPA và API an toàn',
            'meta_keywords' => 'Laravel Sanctum, xác thực Laravel, SPA authentication, API token, CSRF, Bearer token, token abilities',
            'meta_description' => 'Hướng dẫn Laravel Sanctum cho SPA, mobile và API: cookie session, CSRF, CORS, token abilities, thu hồi token và bảo mật production.',
            'tags' => ['Laravel', 'Sanctum', 'PHP', 'API Security', 'Authentication', 'SPA', 'Mobile API', 'Backend'],
            'body' => <<<'HTML'
<p><strong>Laravel Sanctum giải quyết hai bài toán khác nhau: xác thực SPA chính chủ bằng session cookie và cấp personal access token cho mobile hoặc API client.</strong> Phần lớn lỗi triển khai đến từ việc trộn hai mô hình này, chẳng hạn lưu Bearer token của SPA trong localStorage hoặc cấu hình CORS đúng nhưng quên gửi cookie.</p>

<h2>Chọn đúng mô hình trước khi viết code</h2>
<table><thead><tr><th>Client</th><th>Cơ chế nên dùng</th><th>Lý do</th></tr></thead><tbody><tr><td>SPA chính chủ cùng top-level domain</td><td>Session cookie + CSRF</td><td>Thông tin xác thực nằm trong cookie HttpOnly và hưởng cơ chế session của Laravel</td></tr><tr><td>Ứng dụng mobile</td><td>Personal access token</td><td>Client gửi Bearer token trên mỗi request</td></tr><tr><td>CLI hoặc tích hợp đơn giản</td><td>Personal access token có abilities</td><td>Dễ cấp, giới hạn và thu hồi theo thiết bị hoặc mục đích</td></tr><tr><td>Ủy quyền bên thứ ba chuẩn OAuth</td><td>Laravel Passport hoặc OAuth provider</td><td>Sanctum không thay thế đầy đủ authorization code flow, client credentials và consent</td></tr></tbody></table>
<blockquote>Với SPA chính chủ, tài liệu Laravel khuyến nghị dùng xác thực SPA dựa trên cookie của Sanctum, không dùng API token.</blockquote>

<h2>Cài Sanctum và bảo vệ route</h2>
<p>Trong Laravel 13, lệnh cài API sẽ cài và cấu hình Sanctum:</p>
<pre><code>php artisan install:api
php artisan migrate</code></pre>
<p>Model người dùng cần trait <code>HasApiTokens</code> nếu ứng dụng phát personal access token:</p>
<pre><code>use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}</code></pre>
<p>Dùng cùng middleware <code>auth:sanctum</code> cho route nhận cookie từ SPA hoặc Bearer token từ client bên ngoài:</p>
<pre><code>Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) =&gt; $request-&gt;user());
    Route::post('/projects', [ProjectController::class, 'store']);
});</code></pre>

<h2>Luồng đăng nhập đúng cho SPA</h2>
<p>SPA và API phải dùng chung top-level domain, dù có thể nằm ở các subdomain khác nhau, ví dụ <code>app.example.com</code> và <code>api.example.com</code>. Bật stateful API middleware trong <code>bootstrap/app.php</code>:</p>
<pre><code>use Illuminate\Foundation\Configuration\Middleware;

-&gt;withMiddleware(function (Middleware $middleware): void {
    $middleware-&gt;statefulApi();
})</code></pre>
<p>Trước khi đăng nhập, client gọi endpoint CSRF, sau đó mới gửi thông tin đăng nhập:</p>
<pre><code>axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

await axios.get('/sanctum/csrf-cookie');
await axios.post('/login', { email, password });
const { data } = await axios.get('/api/user');</code></pre>
<p>Request đầu tiên đặt cookie <code>XSRF-TOKEN</code>. Axios đọc giá trị đó và gửi header <code>X-XSRF-TOKEN</code> cho request thay đổi dữ liệu. Cookie session xác định người dùng; CSRF token chứng minh request đến từ giao diện hợp lệ. Hai thành phần có vai trò khác nhau.</p>

<h2>Cấu hình domain, cookie và CORS</h2>
<p>Các biến môi trường phụ thuộc domain thực tế, nhưng một cấu hình nhiều subdomain thường có dạng:</p>
<pre><code>APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=app.example.com</code></pre>
<p>Nếu development dùng port, phải đưa cả port vào danh sách stateful. Khi frontend gọi khác origin, cấu hình CORS phải cho phép đúng origin và bật <code>supports_credentials</code>. Không thể dùng wildcard <code>*</code> cho origin khi gửi credential.</p>
<ul><li>Chỉ dùng HTTPS ở production và bật Secure cho session cookie.</li><li>Giữ HttpOnly cho cookie session để JavaScript không đọc được.</li><li>Chọn SameSite phù hợp với kiến trúc; không hạ xuống <code>None</code> nếu không thực sự cần.</li><li>Không cho phép origin động dựa trực tiếp trên header của request.</li><li>Proxy hoặc load balancer phải chuyển tiếp scheme chính xác để Laravel nhận biết HTTPS.</li></ul>

<h2>Cấp token cho mobile và API client</h2>
<p>Token chỉ hiển thị dạng rõ một lần khi được tạo; Sanctum lưu bản băm SHA-256 trong database. Hãy đặt tên theo thiết bị hoặc mục đích và chỉ cấp abilities cần thiết:</p>
<pre><code>$token = $user-&gt;createToken(
    'iphone-15',
    ['projects:read', 'projects:update'],
    now()-&gt;addDays(30),
);

return ['token' =&gt; $token-&gt;plainTextToken];</code></pre>
<p>Client gửi token qua header, không đặt trong query string:</p>
<pre><code>Authorization: Bearer 1|plain-text-token</code></pre>
<p>Trên mobile, lưu token trong Keychain hoặc Keystore. Với CLI và server integration, dùng secret manager hoặc credential store của hệ điều hành; không commit token vào source, file cấu hình mẫu hay log.</p>

<h2>Abilities không thay thế authorization policy</h2>
<p>Abilities giới hạn token được phép làm gì, còn policy xác định người dùng có quyền trên resource cụ thể hay không. Route có thể yêu cầu một hoặc nhiều ability:</p>
<pre><code>Route::put('/projects/{project}', UpdateProjectController::class)
    -&gt;middleware(['auth:sanctum', 'abilities:projects:update']);</code></pre>
<p>Trong policy, vẫn phải kiểm tra quyền sở hữu hoặc vai trò:</p>
<pre><code>public function update(User $user, Project $project): bool
{
    return $user-&gt;id === $project-&gt;owner_id
        &amp;&amp; $user-&gt;tokenCan('projects:update');
}</code></pre>
<p>Với request từ SPA chính chủ, <code>tokenCan()</code> có thể trả về true theo thiết kế của Sanctum. Vì vậy policy hoặc gate mới là nơi bắt buộc thực thi quyền nghiệp vụ.</p>

<h2>Thu hồi, hết hạn và dọn token</h2>
<p>Mặc định token Sanctum không hết hạn nếu ứng dụng không cấu hình expiration. Production nên có thời hạn phù hợp, danh sách thiết bị đang đăng nhập và chức năng thu hồi:</p>
<pre><code>// Thu hồi token hiện tại
$request-&gt;user()-&gt;currentAccessToken()-&gt;delete();

// Thu hồi một token cụ thể
$user-&gt;tokens()-&gt;whereKey($tokenId)-&gt;delete();

// Thu hồi mọi token
$user-&gt;tokens()-&gt;delete();</code></pre>
<p>Lên lịch dọn bản ghi token đã hết hạn:</p>
<pre><code>use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')-&gt;daily();</code></pre>
<p>Đổi mật khẩu, khóa tài khoản hoặc phát hiện thiết bị mất nên kích hoạt chính sách thu hồi tương ứng. Với hệ thống nhạy cảm, ghi audit log cho việc tạo, sử dụng gần nhất và thu hồi token, nhưng không ghi giá trị token dạng rõ.</p>

<h2>Rate limit endpoint đăng nhập và API</h2>
<p>Rate limiting giảm brute force và lạm dụng API. Phân đoạn giới hạn theo user khi đã đăng nhập, theo IP và định danh đã chuẩn hóa khi chưa đăng nhập:</p>
<pre><code>RateLimiter::for('login', function (Request $request) {
    $email = Str::lower((string) $request-&gt;input('email'));

    return [
        Limit::perMinute(20)-&gt;by('ip:'.$request-&gt;ip()),
        Limit::perMinute(5)-&gt;by('login:'.$email.'|'.$request-&gt;ip()),
    ];
});</code></pre>
<p>Không chỉ dựa vào email vì kẻ tấn công có thể làm khóa tài khoản của người khác. Trong hệ thống nhiều máy chủ, dùng cache tập trung như Redis để mọi instance chia sẻ cùng bộ đếm.</p>

<h2>Các lỗi production thường gặp</h2>
<ul><li><strong>401:</strong> request không gửi cookie hoặc Bearer token, route dùng sai guard.</li><li><strong>419:</strong> chưa gọi <code>/sanctum/csrf-cookie</code>, thiếu header XSRF hoặc session đã hết hạn.</li><li><strong>CORS error:</strong> origin không khớp, thiếu credentials hoặc preflight bị proxy chặn.</li><li><strong>Hoạt động local nhưng lỗi production:</strong> sai session domain, Secure cookie hoặc trusted proxy.</li><li><strong>Token có ability nhưng vẫn 403:</strong> policy từ chối quyền trên resource cụ thể.</li><li><strong>Đăng xuất nhưng token còn dùng được:</strong> chỉ xóa session mà chưa thu hồi personal access token.</li></ul>

<h2>Kiểm thử bắt buộc</h2>
<ul><li>SPA lấy CSRF cookie, đăng nhập, gọi route bảo vệ và đăng xuất.</li><li>Request thay đổi dữ liệu thiếu CSRF token phải bị từ chối.</li><li>Token đúng ability được phép; token thiếu ability nhận 403.</li><li>User không sở hữu resource vẫn bị policy từ chối dù token có ability.</li><li>Token hết hạn hoặc đã thu hồi nhận 401.</li><li>Rate limiter trả 429 và header retry phù hợp.</li><li>CORS chỉ chấp nhận origin đã định trước.</li></ul>

<h2>Checklist trước khi phát hành</h2>
<ol><li>SPA chính chủ dùng cookie session, không lưu personal token trong localStorage.</li><li>Stateful domains, session domain, CORS và HTTPS đã khớp môi trường thật.</li><li>Mobile/API token có abilities tối thiểu và thời hạn hữu hạn.</li><li>Mọi route nhạy cảm có cả authentication và policy/gate.</li><li>Token có giao diện quản lý thiết bị và quy trình thu hồi.</li><li>Endpoint login, reset password và API quan trọng có rate limit.</li><li>Log che token, cookie, mật khẩu và dữ liệu bí mật.</li><li>Kiểm thử 401, 403, 419, 429 và luồng thu hồi token.</li></ol>

<h2>Kết luận</h2>
<p>Sanctum đơn giản khi mỗi loại client đi đúng luồng: SPA dùng session cookie và CSRF; mobile hoặc API client dùng Bearer token có abilities. Lớp xác thực này chỉ là điểm bắt đầu. Một hệ thống production còn cần policy, thời hạn và thu hồi token, rate limit, HTTPS, cấu hình cookie chặt chẽ và kiểm thử các tình huống thất bại.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://laravel.com/framework/docs/13.x/sanctum" target="_blank" rel="noopener noreferrer">Laravel 13 Documentation: Sanctum</a></li><li><a href="https://laravel.com/framework/docs/13.x/authorization" target="_blank" rel="noopener noreferrer">Laravel 13 Documentation: Authorization</a></li><li><a href="https://laravel.com/framework/docs/13.x/routing#rate-limiting" target="_blank" rel="noopener noreferrer">Laravel 13 Documentation: Rate Limiting</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Laravel Sanctum in Practice: Secure SPA, Mobile, and API Authentication',
            'slug' => 'laravel-sanctum-secure-spa-api-authentication',
            'image' => 'laravel-sanctum-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Laravel Sanctum: Secure SPA and API Authentication',
            'meta_keywords' => 'Laravel Sanctum, Laravel authentication, SPA authentication, API tokens, CSRF, Bearer token, token abilities',
            'meta_description' => 'Use Laravel Sanctum safely for SPAs, mobile apps, and APIs with session cookies, CSRF, CORS, token abilities, revocation, and rate limits.',
            'tags' => ['Laravel', 'Sanctum', 'PHP', 'API Security', 'Authentication', 'SPA', 'Mobile API', 'Backend'],
            'body' => <<<'HTML'
<p><strong>Laravel Sanctum solves two distinct problems: session-cookie authentication for a first-party SPA and personal access tokens for mobile or API clients.</strong> Most implementation failures come from mixing these models, such as storing an SPA Bearer token in localStorage or configuring CORS correctly while forgetting to send cookies.</p>

<h2>Choose the model before writing code</h2>
<table><thead><tr><th>Client</th><th>Recommended mechanism</th><th>Why</th></tr></thead><tbody><tr><td>First-party SPA on the same top-level domain</td><td>Session cookie + CSRF</td><td>Credentials remain in an HttpOnly cookie and use Laravel sessions</td></tr><tr><td>Mobile application</td><td>Personal access token</td><td>The client sends a Bearer token with each request</td></tr><tr><td>CLI or simple integration</td><td>Personal access token with abilities</td><td>Easy to issue, restrict, and revoke per device or purpose</td></tr><tr><td>Third-party OAuth delegation</td><td>Laravel Passport or an OAuth provider</td><td>Sanctum is not a full replacement for authorization code, client credentials, and consent flows</td></tr></tbody></table>
<blockquote>For a first-party SPA, Laravel recommends Sanctum's cookie-based SPA authentication instead of API tokens.</blockquote>

<h2>Install Sanctum and protect routes</h2>
<p>In Laravel 13, the API installation command installs and configures Sanctum:</p>
<pre><code>php artisan install:api
php artisan migrate</code></pre>
<p>Add <code>HasApiTokens</code> when the application issues personal access tokens:</p>
<pre><code>use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}</code></pre>
<p>The same <code>auth:sanctum</code> middleware accepts SPA cookies and third-party Bearer tokens:</p>
<pre><code>Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) =&gt; $request-&gt;user());
    Route::post('/projects', [ProjectController::class, 'store']);
});</code></pre>

<h2>The correct SPA login flow</h2>
<p>The SPA and API must share a top-level domain, although they may use different subdomains such as <code>app.example.com</code> and <code>api.example.com</code>. Enable stateful API middleware in <code>bootstrap/app.php</code>:</p>
<pre><code>use Illuminate\Foundation\Configuration\Middleware;

-&gt;withMiddleware(function (Middleware $middleware): void {
    $middleware-&gt;statefulApi();
})</code></pre>
<p>Before login, initialize CSRF protection and then submit credentials:</p>
<pre><code>axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

await axios.get('/sanctum/csrf-cookie');
await axios.post('/login', { email, password });
const { data } = await axios.get('/api/user');</code></pre>
<p>The first request sets an <code>XSRF-TOKEN</code> cookie. Axios reads it and sends the <code>X-XSRF-TOKEN</code> header on state-changing requests. The session cookie identifies the user; the CSRF token proves the request came through an accepted browser flow. They serve different purposes.</p>

<h2>Configure domains, cookies, and CORS</h2>
<p>Exact values depend on the deployment, but a multi-subdomain setup commonly resembles:</p>
<pre><code>APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=app.example.com</code></pre>
<p>Development origins must include their port in the stateful list. For cross-origin frontend requests, CORS must allow the exact origin and enable <code>supports_credentials</code>. Credentialed requests cannot combine an origin wildcard with credentials.</p>
<ul><li>Use HTTPS in production and mark session cookies Secure.</li><li>Keep the session cookie HttpOnly so JavaScript cannot read it.</li><li>Choose SameSite deliberately; do not relax it to <code>None</code> without a real need.</li><li>Never reflect an arbitrary request Origin into an allowlist response.</li><li>Ensure proxies forward the original scheme so Laravel recognizes HTTPS.</li></ul>

<h2>Issue tokens to mobile and API clients</h2>
<p>A plain-text token is shown only once; Sanctum stores its SHA-256 hash. Name tokens by device or purpose and grant only required abilities:</p>
<pre><code>$token = $user-&gt;createToken(
    'iphone-15',
    ['projects:read', 'projects:update'],
    now()-&gt;addDays(30),
);

return ['token' =&gt; $token-&gt;plainTextToken];</code></pre>
<p>Send the token in a header, never in the query string:</p>
<pre><code>Authorization: Bearer 1|plain-text-token</code></pre>
<p>Store mobile tokens in Keychain or Keystore. For CLIs and server integrations, use a secret manager or operating-system credential store. Never commit tokens to source, sample configuration, or logs.</p>

<h2>Abilities do not replace authorization policies</h2>
<p>Abilities constrain what a token may do; policies decide whether the user may act on a specific resource. A route can require abilities:</p>
<pre><code>Route::put('/projects/{project}', UpdateProjectController::class)
    -&gt;middleware(['auth:sanctum', 'abilities:projects:update']);</code></pre>
<p>The policy still checks ownership or role:</p>
<pre><code>public function update(User $user, Project $project): bool
{
    return $user-&gt;id === $project-&gt;owner_id
        &amp;&amp; $user-&gt;tokenCan('projects:update');
}</code></pre>
<p>For first-party SPA requests, <code>tokenCan()</code> may return true by Sanctum's design. Policies and gates must therefore remain the authority for business permissions.</p>

<h2>Revocation, expiration, and pruning</h2>
<p>Sanctum tokens do not expire by default unless the application configures expiration. Production systems should define suitable lifetimes, show active devices, and support revocation:</p>
<pre><code>// Revoke the current token
$request-&gt;user()-&gt;currentAccessToken()-&gt;delete();

// Revoke one token
$user-&gt;tokens()-&gt;whereKey($tokenId)-&gt;delete();

// Revoke every token
$user-&gt;tokens()-&gt;delete();</code></pre>
<p>Schedule cleanup of expired records:</p>
<pre><code>use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')-&gt;daily();</code></pre>
<p>Password changes, account suspension, and lost-device reports should trigger the appropriate revocation policy. Sensitive systems should audit token creation, last use, and revocation without logging the plain-text secret.</p>

<h2>Rate-limit login and API endpoints</h2>
<p>Rate limiting reduces brute-force attempts and API abuse. Segment authenticated traffic by user and login traffic by both IP and normalized identifier:</p>
<pre><code>RateLimiter::for('login', function (Request $request) {
    $email = Str::lower((string) $request-&gt;input('email'));

    return [
        Limit::perMinute(20)-&gt;by('ip:'.$request-&gt;ip()),
        Limit::perMinute(5)-&gt;by('login:'.$email.'|'.$request-&gt;ip()),
    ];
});</code></pre>
<p>Do not key the limit only by email because an attacker could lock out another person's account. In a multi-server deployment, use a shared cache such as Redis so all instances share counters.</p>

<h2>Common production failures</h2>
<ul><li><strong>401:</strong> the request omitted its cookie or Bearer token, or the route uses the wrong guard.</li><li><strong>419:</strong> the client skipped <code>/sanctum/csrf-cookie</code>, omitted XSRF, or the session expired.</li><li><strong>CORS error:</strong> the origin does not match, credentials are disabled, or a proxy blocks preflight.</li><li><strong>Works locally but fails in production:</strong> session domain, Secure cookie, or trusted proxy configuration is wrong.</li><li><strong>Token has an ability but receives 403:</strong> the resource policy denied the action.</li><li><strong>Token still works after logout:</strong> only the session was cleared; the personal access token was not revoked.</li></ul>

<h2>Required tests</h2>
<ul><li>The SPA initializes CSRF, logs in, accesses a protected route, and logs out.</li><li>A state-changing request without CSRF is rejected.</li><li>A token with the ability succeeds; a token without it receives 403.</li><li>A non-owner is denied by policy even when the token has the ability.</li><li>Expired and revoked tokens receive 401.</li><li>The rate limiter returns 429 with appropriate retry information.</li><li>CORS accepts only configured origins.</li></ul>

<h2>Pre-release checklist</h2>
<ol><li>The first-party SPA uses session cookies and does not keep personal tokens in localStorage.</li><li>Stateful domains, session domain, CORS, and HTTPS match the real environment.</li><li>Mobile and API tokens have minimal abilities and finite lifetimes.</li><li>Every sensitive route uses authentication plus a policy or gate.</li><li>Users can inspect devices and revoke tokens.</li><li>Login, password reset, and important API endpoints are rate-limited.</li><li>Logs redact tokens, cookies, passwords, and secrets.</li><li>Tests cover 401, 403, 419, 429, and token revocation.</li></ol>

<h2>Conclusion</h2>
<p>Sanctum stays simple when each client follows the correct path: SPAs use session cookies and CSRF, while mobile and API clients use ability-scoped Bearer tokens. Authentication is only the starting point. Production safety also requires policies, expiration and revocation, rate limits, HTTPS, strict cookie settings, and failure-path tests.</p>

<h2>References</h2>
<ul><li><a href="https://laravel.com/framework/docs/13.x/sanctum" target="_blank" rel="noopener noreferrer">Laravel 13 Documentation: Sanctum</a></li><li><a href="https://laravel.com/framework/docs/13.x/authorization" target="_blank" rel="noopener noreferrer">Laravel 13 Documentation: Authorization</a></li><li><a href="https://laravel.com/framework/docs/13.x/routing#rate-limiting" target="_blank" rel="noopener noreferrer">Laravel 13 Documentation: Rate Limiting</a></li></ul>
HTML,
        ],
    ],
];
