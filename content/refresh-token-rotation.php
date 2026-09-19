<?php

return [
    'refresh-token-rotation.html' => [
        'vi' => [
            'title' => 'Refresh token rotation thực chiến: Thiết kế đăng nhập an toàn cho API',
            'slug' => 'refresh-token-rotation-thiet-ke-dang-nhap-api-an-toan',
            'image' => 'refresh-token-rotation.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Refresh token rotation an toàn cho API',
            'meta_keywords' => 'refresh token rotation, access token, JWT, OAuth 2.0, token reuse detection, API authentication, token family',
            'meta_description' => 'Thiết kế refresh token rotation cho API: token family, hash lưu trữ, transaction chống race, reuse detection, revoke, cookie an toàn và kiểm thử.',
            'tags' => ['OAuth 2.0', 'Refresh Token', 'JWT', 'API Security', 'Authentication', 'Backend'],
            'body' => <<<'HTML'
<p><strong>Access token ngắn hạn giảm thời gian kẻ tấn công có thể sử dụng token bị lộ, nhưng refresh token lại là thông tin đăng nhập dài hạn có khả năng tạo access token mới.</strong> Nếu backend chấp nhận một refresh token nhiều lần, bản sao bị đánh cắp có thể duy trì phiên gần như âm thầm. Refresh token rotation giải quyết vấn đề bằng cách thay token sau mỗi lần sử dụng và phát hiện token cũ bị phát lại.</p>

<h2>Access token và refresh token có vai trò khác nhau</h2>
<ul><li><strong>Access token:</strong> sống ngắn, gửi tới resource server, giới hạn audience và scope.</li><li><strong>Refresh token:</strong> chỉ gửi tới authorization server để đổi token mới, sống lâu hơn và phải được bảo vệ chặt hơn.</li></ul>
<p>JWT không bắt buộc cho refresh token. Một chuỗi ngẫu nhiên opaque thường dễ thu hồi, dễ kiểm soát và không làm lộ claim. Dù dùng định dạng nào, token phải có entropy cao, truyền qua TLS và không xuất hiện trong URL hoặc log.</p>

<h2>Rotation hoạt động như thế nào?</h2>
<ol><li>Đăng nhập thành công tạo access token <code>A1</code> và refresh token <code>R1</code>.</li><li>Client gửi <code>R1</code> tới endpoint refresh.</li><li>Server xác thực, đánh dấu <code>R1</code> đã dùng và phát <code>A2 + R2</code>.</li><li><code>R1</code> không còn hợp lệ nhưng quan hệ của nó với phiên vẫn được giữ để phát hiện reuse.</li><li>Nếu <code>R1</code> xuất hiện lần nữa, server thu hồi toàn bộ token family.</li></ol>
<p>Server không biết request thứ hai đến từ client hợp lệ hay kẻ tấn công. Vì vậy phản ứng an toàn là hủy family và buộc đăng nhập lại.</p>

<h2>Mô hình dữ liệu đề xuất</h2>
<pre><code>refresh_tokens
- id (UUID)
- family_id (UUID)
- user_id, client_id
- token_hash
- parent_id
- status: active | used | revoked
- issued_at, expires_at, used_at, revoked_at
- replaced_by_id
- scope, audience
- device_label, last_ip (optional metadata)</code></pre>
<p>Chỉ lưu hash của token, tương tự password reset token. Khi nhận token, server hash bằng hàm nhất quán có khóa hoặc SHA-256 với token ngẫu nhiên đủ mạnh rồi tra cứu. Không ghi token thô vào database, APM hay audit log.</p>

<h2>Luồng refresh phải nguyên tử</h2>
<pre><code>BEGIN;
SELECT * FROM refresh_tokens
WHERE token_hash = :hash
FOR UPDATE;

-- reject expired/revoked token
-- if status = used: revoke entire family and fail
-- if active: mark used, insert replacement R2

COMMIT;</code></pre>
<p>Khóa hàng hoặc compare-and-swap ngăn hai request song song cùng đổi một token thành công. Unique constraint cho <code>token_hash</code>, transaction ngắn và index theo hash/family là các lớp bảo vệ cần thiết.</p>

<h2>Xử lý race condition hợp lệ</h2>
<p>Browser có thể gửi hai request API cùng lúc, cả hai thấy access token hết hạn và cùng refresh. Nếu request đầu đã rotate, request thứ hai trông giống replay. Cách tốt nhất là client dùng single-flight: chỉ một promise refresh chạy, các request khác chờ kết quả.</p>
<p>Một grace window phía server có thể giảm logout giả nhưng cũng mở cửa phát lại. Nếu bắt buộc dùng, cửa sổ phải rất ngắn, chỉ trả lại đúng replacement đã tạo và gắn với cùng client context; không phát thêm nhánh token mới.</p>

<h2>Lưu token trên web và mobile</h2>
<ul><li><strong>Web:</strong> ưu tiên refresh token trong cookie <code>HttpOnly; Secure; SameSite</code>, kết hợp CSRF protection phù hợp. Không lưu credential trong <code>localStorage</code>.</li><li><strong>Mobile:</strong> dùng Keychain/Keystore và tránh backup token sang thiết bị khác.</li><li><strong>BFF:</strong> browser chỉ giữ session cookie, backend-for-frontend quản lý token OAuth phía server.</li></ul>
<p>Access token nên ở memory khi có thể. Cookie không tự loại bỏ XSS hay CSRF; vẫn cần CSP, encode output, kiểm tra Origin/CSRF token và giới hạn CORS.</p>

<h2>Thời hạn và thu hồi</h2>
<p>Dùng cả idle timeout và absolute lifetime. Mỗi lần refresh có thể kéo dài idle window nhưng không vượt quá tuổi tuyệt đối của authorization. Thu hồi family khi logout thiết bị, đổi mật khẩu, khóa tài khoản, phát hiện reuse hoặc có sự kiện rủi ro.</p>
<p>“Logout tất cả thiết bị” thu hồi mọi family của user; “logout thiết bị này” chỉ thu hồi family hiện tại. Access token đã phát có thể còn hiệu lực đến khi hết hạn, trừ khi resource server có introspection/denylist. Đây là lý do access token cần sống ngắn.</p>

<h2>Response và lỗi API</h2>
<pre><code>POST /oauth/token
grant_type=refresh_token&amp;refresh_token=...

200 { access_token, expires_in, refresh_token }
400 { error: "invalid_grant" }</code></pre>
<p>Không tiết lộ token “hết hạn”, “đã dùng” hay “không tồn tại” cho client không tin cậy. Ghi lý do chi tiết vào security event nội bộ, kèm family ID, client ID và correlation ID nhưng không chứa token.</p>

<h2>Checklist kiểm thử</h2>
<ul><li>Một token chỉ đổi thành công đúng một lần.</li><li>Hai refresh song song không tạo hai nhánh hợp lệ.</li><li>Dùng lại token cũ thu hồi cả family.</li><li>Token hết hạn, sai client, scope hoặc audience bị từ chối.</li><li>Logout và đổi mật khẩu thực sự chặn refresh tiếp theo.</li><li>Database, log, trace và analytics không chứa token thô.</li><li>Cookie và CSRF/CORS được kiểm thử trên browser thật.</li><li>Cleanup xóa bản ghi hết hạn nhưng giữ đủ dấu vết phát hiện reuse trong thời gian chính sách yêu cầu.</li></ul>

<h2>Khi nào nên dùng giải pháp khác?</h2>
<p>Ứng dụng web cùng domain có thể đơn giản hơn với session cookie phía server. Hệ thống rủi ro cao có thể dùng sender-constrained token như DPoP hoặc mTLS, đôi khi kết hợp rotation. Không tự xây authorization server nếu một nhà cung cấp hoặc thư viện OAuth/OIDC trưởng thành đáp ứng yêu cầu.</p>

<h2>Kết luận</h2>
<p>Rotation không chỉ là phát chuỗi mới. Thiết kế đúng cần token family, trạng thái sử dụng, thao tác nguyên tử, phát hiện reuse, chiến lược revoke và client single-flight. Khi các phần này đi cùng access token ngắn hạn và lưu trữ phù hợp nền tảng, phiên đăng nhập vừa thuận tiện vừa có điểm chặn rõ ràng khi credential bị lộ.</p>

<h2>Tài liệu tham khảo</h2><ul><li><a href="https://www.rfc-editor.org/rfc/rfc9700.html" target="_blank" rel="noopener noreferrer">RFC 9700: OAuth 2.0 Security Best Current Practice</a></li><li><a href="https://cheatsheetseries.owasp.org/cheatsheets/OAuth2_Cheat_Sheet.html" target="_blank" rel="noopener noreferrer">OWASP OAuth2 Cheat Sheet</a></li><li><a href="https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html" target="_blank" rel="noopener noreferrer">OWASP Session Management Cheat Sheet</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Refresh Token Rotation in Practice: Secure API Authentication',
            'slug' => 'refresh-token-rotation-secure-api-authentication',
            'image' => 'refresh-token-rotation.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Secure Refresh Token Rotation for APIs',
            'meta_keywords' => 'refresh token rotation, access token, JWT, OAuth 2.0, token reuse detection, API authentication, token family',
            'meta_description' => 'Design refresh token rotation with token families, hashed storage, atomic replacement, reuse detection, revocation, secure cookies, and focused tests.',
            'tags' => ['OAuth 2.0', 'Refresh Token', 'JWT', 'API Security', 'Authentication', 'Backend'],
            'body' => <<<'HTML'
<p><strong>A short-lived access token limits the useful lifetime of a leak, but a refresh token is a long-lived credential that can mint new access tokens.</strong> If a backend accepts the same refresh token repeatedly, a stolen copy can preserve access quietly. Refresh token rotation replaces the credential after every use and detects replay of an older token.</p>
<h2>Different responsibilities</h2><ul><li><strong>Access token:</strong> short-lived, sent to a resource server, restricted by audience and scope.</li><li><strong>Refresh token:</strong> sent only to the authorization server, longer-lived, and protected more carefully.</li></ul><p>A refresh token does not need to be a JWT. An opaque random value is often easier to revoke and exposes no claims. Either format needs high entropy, TLS, and exclusion from URLs and logs.</p>
<h2>How rotation works</h2><ol><li>Login issues access token <code>A1</code> and refresh token <code>R1</code>.</li><li>The client exchanges <code>R1</code>.</li><li>The server marks <code>R1</code> used and returns <code>A2 + R2</code>.</li><li>The relationship remains stored even though <code>R1</code> is invalid.</li><li>If <code>R1</code> appears again, the server revokes the entire token family.</li></ol><p>The server cannot know whether the attacker or legitimate client made the replay, so both lose the grant and must authenticate again.</p>
<h2>Suggested data model</h2><pre><code>refresh_tokens
- id, family_id, user_id, client_id
- token_hash, parent_id, replaced_by_id
- status: active | used | revoked
- issued_at, expires_at, used_at, revoked_at
- scope, audience</code></pre><p>Store only a deterministic hash of the random token. Never place raw refresh tokens in databases, APM traces, analytics, or audit logs.</p>
<h2>Make refresh atomic</h2><pre><code>BEGIN;
SELECT * FROM refresh_tokens
WHERE token_hash = :hash FOR UPDATE;
-- reject expired/revoked
-- reused: revoke family and fail
-- active: mark used and insert R2
COMMIT;</code></pre><p>A row lock or compare-and-swap prevents concurrent requests from both succeeding. Add unique constraints and indexes for token hash and family.</p>
<h2>Handle legitimate races</h2><p>Several API calls may notice an expired access token simultaneously. The client should use single-flight refresh: one shared promise performs the exchange while other calls wait. A server grace window weakens replay protection; if unavoidable, keep it very short and return the same replacement for the same client context rather than creating another branch.</p>
<h2>Web and mobile storage</h2><ul><li><strong>Web:</strong> prefer an <code>HttpOnly; Secure; SameSite</code> cookie plus appropriate CSRF defenses. Do not keep credentials in localStorage.</li><li><strong>Mobile:</strong> use Keychain/Keystore and prevent token backup to another device.</li><li><strong>BFF:</strong> let the browser hold only a session cookie while the backend-for-frontend manages OAuth tokens.</li></ul><p>Cookies do not eliminate XSS or CSRF. Apply CSP, output encoding, Origin/CSRF checks, and restrictive CORS.</p>
<h2>Expiration and revocation</h2><p>Use both idle timeout and absolute lifetime. Revoke a family on device logout, password change, account suspension, detected reuse, or a risk event. Logout-all revokes every user family; logout-this-device revokes only the current family. Already issued access tokens may work until expiry unless resource servers use introspection or a denylist, so keep them short-lived.</p>
<h2>API errors and observability</h2><pre><code>POST /oauth/token
grant_type=refresh_token&amp;refresh_token=...

200 { access_token, expires_in, refresh_token }
400 { error: "invalid_grant" }</code></pre><p>Do not reveal whether a token was expired, reused, or unknown to an untrusted client. Record the internal reason with family ID, client ID, and correlation ID, never the token.</p>
<h2>Test checklist</h2><ul><li>A token succeeds exactly once.</li><li>Concurrent refreshes cannot create two valid branches.</li><li>Reusing an old token revokes its family.</li><li>Expired tokens and wrong clients, scopes, or audiences fail.</li><li>Logout and password changes block later refreshes.</li><li>Logs and traces contain no raw credentials.</li><li>Cookie, CSRF, and CORS behavior is tested in real browsers.</li></ul>
<h2>When to choose another approach</h2><p>A same-domain web app may be simpler with server-side session cookies. High-risk systems can use sender-constrained tokens such as DPoP or mTLS, potentially alongside rotation. Avoid building an authorization server when a mature OAuth/OIDC provider or library meets the requirements.</p>
<h2>Conclusion</h2><p>Rotation is more than returning a new string. A secure design needs token families, use state, atomic replacement, reuse detection, revocation, and client-side single-flight behavior. Combined with short-lived access tokens and platform-appropriate storage, it gives a session a clear containment point when credentials leak.</p>
<h2>References</h2><ul><li><a href="https://www.rfc-editor.org/rfc/rfc9700.html" target="_blank" rel="noopener noreferrer">RFC 9700: OAuth 2.0 Security Best Current Practice</a></li><li><a href="https://cheatsheetseries.owasp.org/cheatsheets/OAuth2_Cheat_Sheet.html" target="_blank" rel="noopener noreferrer">OWASP OAuth2 Cheat Sheet</a></li><li><a href="https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html" target="_blank" rel="noopener noreferrer">OWASP Session Management Cheat Sheet</a></li></ul>
HTML,
        ],
    ],
];
