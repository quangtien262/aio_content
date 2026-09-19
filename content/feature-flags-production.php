<?php

return [
    'feature-flags-production.html' => [
        'vi' => [
            'title' => 'Feature flag thực chiến: Phát hành tính năng an toàn không cần deploy lại',
            'slug' => 'feature-flag-phat-hanh-tinh-nang-an-toan',
            'image' => 'feature-flags-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Feature flag thực chiến cho production',
            'meta_keywords' => 'feature flag, feature toggle, progressive rollout, canary release, kill switch, OpenFeature, phát hành phần mềm',
            'meta_description' => 'Thiết kế feature flag production: rollout ổn định, targeting context, fallback, kill switch, quan sát theo variant, kiểm thử và dọn technical debt.',
            'tags' => ['Feature Flags', 'Progressive Delivery', 'OpenFeature', 'DevOps', 'Backend', 'Observability'],
            'body' => <<<'HTML'
<p><strong>Feature flag tách thời điểm deploy code khỏi thời điểm phát hành tính năng.</strong> Nhóm phát triển có thể đưa code lên production ở trạng thái tắt, mở cho nhân viên, tăng dần tỷ lệ người dùng và tắt nhanh khi có sự cố mà không cần build lại. Nhưng nếu thiếu vòng đời, flag sẽ trở thành cấu hình sống vĩnh viễn và làm mọi nhánh code khó hiểu hơn.</p>

<h2>Deployment không phải release</h2>
<p>Deployment đưa artifact đến môi trường. Release quyết định ai được dùng hành vi mới. Feature flag nằm tại điểm quyết định runtime:</p>
<pre><code>const enabled = flags.getBooleanValue(
  'checkout-v2',
  false,
  { targetingKey: user.id, plan: user.plan }
);

return enabled ? checkoutV2(order) : checkoutV1(order);</code></pre>
<p>Giá trị mặc định là một phần của thiết kế an toàn. Với tính năng mới chưa được kiểm chứng, fallback thường là <code>false</code>. Với kill switch bảo vệ hệ thống, tên và ngữ nghĩa nên rõ để lúc khẩn cấp không bật nhầm.</p>

<h2>Bốn loại flag phổ biến</h2>
<table><thead><tr><th>Loại</th><th>Mục đích</th><th>Tuổi thọ</th></tr></thead><tbody><tr><td>Release</td><td>Ẩn code chưa sẵn sàng, rollout dần</td><td>Ngắn</td></tr><tr><td>Experiment</td><td>So sánh variant theo giả thuyết</td><td>Đến khi đủ dữ liệu</td></tr><tr><td>Ops / kill switch</td><td>Giảm tải, vô hiệu hóa tích hợp lỗi</td><td>Có thể dài</td></tr><tr><td>Entitlement</td><td>Quyền theo gói hoặc hợp đồng</td><td>Dài, cần quản trị như policy</td></tr></tbody></table>
<p>Không dùng release flag như hệ thống phân quyền. Quyền truy cập phải được kiểm tra phía server, có audit và mặc định từ chối.</p>

<h2>Rollout theo phần trăm phải ổn định</h2>
<p>Không gọi random cho mỗi request. Cùng một người có thể lúc thấy giao diện cũ, lúc thấy giao diện mới, làm hỏng trải nghiệm và dữ liệu thử nghiệm. Hãy hash ổn định:</p>
<pre><code>bucket = hash(flagKey + ':' + targetingKey) % 10000
enabled = bucket &lt; rolloutBasisPoints</code></pre>
<p><code>targetingKey</code> có thể là user, account hoặc tenant tùy đơn vị cần nhất quán. Với B2B, rollout theo tenant thường an toàn hơn rollout từng user vì mọi thành viên dùng cùng workflow.</p>

<h2>Evaluation context: đủ dùng, không gom PII tùy tiện</h2>
<p>OpenFeature chuẩn hóa evaluation API, provider, context và hook. Context có thể chứa application, region, tenant, plan hoặc targeting key. Chỉ đưa thuộc tính thực sự cần cho rule; tránh email, tên và dữ liệu nhạy cảm khi một ID giả danh đã đủ.</p>
<p>Quy tắc cần có thứ tự dễ đọc: override khẩn cấp, allowlist nội bộ, điều kiện tương thích, rollout phần trăm, cuối cùng là default. Ghi lại reason/variant thay vì copy toàn bộ context vào log.</p>

<h2>Client-side hay server-side?</h2>
<ul><li><strong>Server-side:</strong> phù hợp logic nghiệp vụ, thuật toán, tích hợp và kiểm soát dữ liệu. Giá trị flag không bị người dùng sửa trực tiếp.</li><li><strong>Client-side:</strong> phù hợp trình bày giao diện, nhưng mọi flag tải xuống đều có thể bị quan sát. Không dùng để bảo vệ secret hoặc authorization.</li></ul>
<p>Nếu cả frontend và backend cùng phụ thuộc một rollout, backend vẫn phải là nguồn quyết định cho hành vi có ảnh hưởng dữ liệu. Tránh để hai phía evaluate theo context khác nhau.</p>

<h2>Điều gì xảy ra khi hệ thống flag lỗi?</h2>
<p>SDK nên cache cấu hình cục bộ và evaluation không nên gọi HTTP đồng bộ trên từng request. Xác định rõ:</p>
<ul><li>Giá trị mặc định theo từng flag.</li><li>Thời gian cho phép dùng cache cũ.</li><li>Hành vi khi provider chưa sẵn sàng hoặc sai kiểu dữ liệu.</li><li>Cảnh báo khi đang dùng default vì lỗi, không phải vì rule.</li></ul>
<p>Không có một quy tắc fail-open cho mọi flag. Tính năng trang trí có thể bật theo mặc định; thanh toán mới nên quay về luồng cũ; quyền truy cập phải fail closed.</p>

<h2>Quan sát rollout theo variant</h2>
<p>Dashboard tổng có thể che lỗi vì chỉ 5% traffic dùng code mới. Mọi golden signal cần tách theo variant: request count, error rate, latency, conversion và business invariant. Log evaluation nên chứa flag key, variant, reason, provider và version ruleset; hạn chế giá trị/context nhạy cảm.</p>
<p>Đặt ngưỡng dừng trước khi rollout: ví dụ error rate tăng quá mức cho phép hoặc p95 vượt SLO. Kill switch phải được thử trước sự cố và chỉ người có quyền mới thay đổi được.</p>

<h2>Quy trình rollout gợi ý</h2>
<ol><li>Deploy với flag tắt và kiểm tra đường cũ.</li><li>Bật cho developer/nhân viên nội bộ.</li><li>Mở canary 1%, quan sát đủ một chu kỳ tải.</li><li>Tăng 5%, 25%, 50%, 100% theo checkpoint.</li><li>Dừng hoặc rollback flag nếu vượt guardrail.</li><li>Sau khi ổn định, xóa đường cũ và xóa flag.</li></ol>
<p>Thời gian quan sát phải phù hợp lưu lượng và chu kỳ nghiệp vụ; năm phút không đại diện cho job cuối ngày hay hóa đơn cuối tháng.</p>

<h2>Kiểm thử mà không nhân đôi mọi tổ hợp</h2>
<ul><li>Unit test đường bật và tắt ở điểm quyết định chính.</li><li>Contract test kiểu dữ liệu, default và context bắt buộc.</li><li>Integration test provider lỗi, timeout và cache cũ.</li><li>E2E tập trung vào luồng quan trọng thay vì mọi tổ hợp flag.</li><li>Production smoke test cho cohort nội bộ trước rollout công khai.</li></ul>
<p>Nhiều flag tương tác tạo số tổ hợp tăng theo cấp số nhân. Giảm số flag sống đồng thời và tránh nhánh lồng nhau quan trọng hơn cố test mọi tổ hợp.</p>

<h2>Dọn flag là một phần của Definition of Done</h2>
<p>Mỗi flag cần owner, mục đích, ngày tạo, ngày hết hạn, default và ticket xóa. Khi rollout đạt 100% và ổn định, tạo PR xóa nhánh cũ, rule, dashboard tạm và flag trên control plane. Có thể dùng lint hoặc báo cáo để cảnh báo flag quá hạn.</p>

<h2>Checklist production</h2>
<ul><li>Key ổn định, tên phản ánh hành vi, không phản ánh implementation tạm thời.</li><li>Default an toàn và được test khi provider không hoạt động.</li><li>Rollout hash theo user/account/tenant nhất quán.</li><li>Không dùng client flag thay authorization.</li><li>Metrics và log phân tách theo variant nhưng không lộ PII.</li><li>Thay đổi rule có RBAC, audit log và quy trình khẩn cấp.</li><li>Mỗi release flag có owner và ngày xóa.</li></ul>

<h2>Kết luận</h2>
<p>Feature flag là công cụ kiểm soát rủi ro phát hành, không phải nơi cất code dở dang vô thời hạn. Khi có default an toàn, rollout ổn định, telemetry theo variant, kill switch đã thử và kỷ luật xóa flag, đội ngũ có thể phát hành nhỏ hơn, học nhanh hơn và phục hồi mà không phụ thuộc một lần deploy mới.</p>

<h2>Tài liệu tham khảo</h2><ul><li><a href="https://openfeature.dev/docs/reference/intro/" target="_blank" rel="noopener noreferrer">OpenFeature: Introduction</a></li><li><a href="https://openfeature.dev/specification/sections/evaluation-context/" target="_blank" rel="noopener noreferrer">OpenFeature: Evaluation Context</a></li><li><a href="https://opentelemetry.io/docs/specs/semconv/feature-flags/" target="_blank" rel="noopener noreferrer">OpenTelemetry: Feature flag semantic conventions</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Feature Flags in Practice: Release Safely Without Redeploying',
            'slug' => 'feature-flags-safe-progressive-release',
            'image' => 'feature-flags-production.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Production Feature Flags in Practice',
            'meta_keywords' => 'feature flags, feature toggles, progressive rollout, canary release, kill switch, OpenFeature, software delivery',
            'meta_description' => 'Design production feature flags with stable rollout, targeting context, safe fallbacks, kill switches, variant telemetry, testing, and lifecycle cleanup.',
            'tags' => ['Feature Flags', 'Progressive Delivery', 'OpenFeature', 'DevOps', 'Backend', 'Observability'],
            'body' => <<<'HTML'
<p><strong>Feature flags separate deploying code from releasing behavior.</strong> A team can ship disabled code, expose it internally, increase the audience gradually, and turn it off during an incident without another build. Without lifecycle discipline, however, flags become permanent configuration and make every code path harder to understand.</p>
<h2>Deployment is not release</h2><p>Deployment moves an artifact into an environment. Release decides who receives new behavior:</p><pre><code>const enabled = flags.getBooleanValue(
  'checkout-v2', false,
  { targetingKey: user.id, plan: user.plan }
);
return enabled ? checkoutV2(order) : checkoutV1(order);</code></pre><p>The default is a safety decision. An unproven feature normally falls back to <code>false</code>. A protective kill switch needs unambiguous naming and semantics so operators cannot invert it under pressure.</p>
<h2>Four common flag types</h2><table><thead><tr><th>Type</th><th>Purpose</th><th>Lifetime</th></tr></thead><tbody><tr><td>Release</td><td>Hide unfinished code and roll out gradually</td><td>Short</td></tr><tr><td>Experiment</td><td>Compare variants against a hypothesis</td><td>Until enough evidence</td></tr><tr><td>Ops / kill switch</td><td>Shed load or disable a failing integration</td><td>Potentially long</td></tr><tr><td>Entitlement</td><td>Grant behavior by plan or contract</td><td>Long; manage as policy</td></tr></tbody></table><p>Do not treat a release flag as authorization. Access control belongs on the server with auditability and deny-by-default behavior.</p>
<h2>Percentage rollout must be stable</h2><p>Do not choose randomly on every request. Hash a stable subject:</p><pre><code>bucket = hash(flagKey + ':' + targetingKey) % 10000
enabled = bucket &lt; rolloutBasisPoints</code></pre><p>Choose user, account, or tenant according to the required consistency boundary. B2B workflows often need tenant-level allocation so colleagues see the same behavior.</p>
<h2>Evaluation context without unnecessary PII</h2><p>OpenFeature standardizes evaluation APIs, providers, context, and hooks. Context can include application, region, tenant, plan, and targeting key. Include only attributes required by rules; prefer an opaque identifier over names or email addresses. Order rules clearly: emergency override, internal allowlist, compatibility constraints, percentage rollout, then default.</p>
<h2>Client-side or server-side?</h2><ul><li><strong>Server-side:</strong> business logic, algorithms, integrations, and data control.</li><li><strong>Client-side:</strong> presentation changes, while assuming downloaded flags are observable and modifiable.</li></ul><p>Never use a client flag to protect secrets or authorization. When both frontend and backend participate, the backend remains authoritative for data-changing behavior.</p>
<h2>When the flag system fails</h2><p>SDKs should cache configuration locally; evaluation should not make a synchronous network call per request. Define each default, acceptable stale-cache duration, provider-not-ready behavior, type mismatch handling, and alerts that distinguish an error fallback from a rule result.</p><p>There is no universal fail-open choice. Cosmetic behavior may default on, a new payment path should return to the old flow, and authorization must fail closed.</p>
<h2>Observe by variant</h2><p>Aggregate dashboards can hide a regression affecting a 5% cohort. Split request count, errors, latency, conversion, and business invariants by variant. Record key, variant, reason, provider, and ruleset version while minimizing sensitive context. Set rollback guardrails before rollout and test the kill switch before an incident.</p>
<h2>A rollout sequence</h2><ol><li>Deploy disabled and verify the old path.</li><li>Enable for developers or employees.</li><li>Canary at 1% for a representative load cycle.</li><li>Advance through 5%, 25%, 50%, and 100% checkpoints.</li><li>Stop or disable when a guardrail fails.</li><li>After stabilization, delete the old path and flag.</li></ol><p>Observation time must match the business cycle; five minutes cannot validate an end-of-day job or monthly billing.</p>
<h2>Testing without combinatorial explosion</h2><ul><li>Unit-test enabled and disabled paths at key decisions.</li><li>Contract-test types, defaults, and required context.</li><li>Integration-test provider failures, timeout, and stale cache.</li><li>Use E2E tests for critical journeys, not every flag combination.</li><li>Run production smoke tests on an internal cohort.</li></ul><p>Reducing simultaneous flags and nested branches is more effective than attempting every possible combination.</p>
<h2>Cleanup belongs in the Definition of Done</h2><p>Every flag needs an owner, purpose, creation date, expiry date, default, and removal ticket. At stable 100% rollout, remove the old branch, temporary rules and dashboards, and the control-plane flag. Linting and expiry reports can highlight stale flags.</p>
<h2>Production checklist</h2><ul><li>Stable key and behavior-oriented name.</li><li>Safe default tested without a provider.</li><li>Consistent hashing by user, account, or tenant.</li><li>No client flag used as authorization.</li><li>Variant telemetry without leaking PII.</li><li>RBAC, audit logs, and an emergency process for rule changes.</li><li>An owner and removal date for every release flag.</li></ul>
<h2>Conclusion</h2><p>Feature flags control release risk; they are not permanent storage for unfinished branches. Safe defaults, deterministic rollout, variant telemetry, a tested kill switch, and disciplined cleanup let teams release in smaller steps, learn sooner, and recover without waiting for another deployment.</p>
<h2>References</h2><ul><li><a href="https://openfeature.dev/docs/reference/intro/" target="_blank" rel="noopener noreferrer">OpenFeature: Introduction</a></li><li><a href="https://openfeature.dev/specification/sections/evaluation-context/" target="_blank" rel="noopener noreferrer">OpenFeature: Evaluation Context</a></li><li><a href="https://opentelemetry.io/docs/specs/semconv/feature-flags/" target="_blank" rel="noopener noreferrer">OpenTelemetry: Feature flag semantic conventions</a></li></ul>
HTML,
        ],
    ],
];
