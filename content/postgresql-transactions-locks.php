<?php

return [
    'postgresql-transactions-locks.html' => [
        'vi' => [
            'title' => 'PostgreSQL transaction thực chiến: Isolation, row lock và xử lý deadlock',
            'slug' => 'postgresql-transaction-isolation-lock-deadlock',
            'image' => 'postgresql-transactions-locks.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'PostgreSQL transaction, isolation, lock và deadlock',
            'meta_keywords' => 'PostgreSQL transaction, isolation level, SELECT FOR UPDATE, row lock, deadlock, MVCC, serializable retry',
            'meta_description' => 'Hiểu transaction PostgreSQL, Read Committed, Repeatable Read, Serializable, row lock, deadlock, retry và cách quan sát session bị block.',
            'tags' => ['PostgreSQL', 'Transaction', 'MVCC', 'Database Lock', 'Deadlock', 'SQL', 'Backend', 'Concurrency'],
            'body' => <<<'HTML'
<p><strong>Transaction không chỉ là bọc nhiều câu SQL trong <code>BEGIN</code> và <code>COMMIT</code>.</strong> Khi nhiều request cùng đọc và sửa dữ liệu, ứng dụng phải chọn isolation level phù hợp, khóa đúng hàng, giữ transaction ngắn và chuẩn bị retry các lỗi concurrency có chủ đích.</p>

<h2>ACID không tự động bảo vệ mọi business rule</h2>
<p>PostgreSQL bảo đảm atomicity và consistency ở mức constraint/transaction, nhưng database không tự hiểu quy tắc “không bán vượt tồn kho” hay “tổng số ghế không vượt giới hạn” nếu ứng dụng chỉ đọc rồi ghi bằng hai câu rời rạc. Hai transaction có thể cùng đọc một trạng thái hợp lệ và cùng ra quyết định xung đột.</p>
<blockquote>Constraint bảo vệ invariant đơn giản; lock hoặc isolation cao hơn bảo vệ quyết định dựa trên dữ liệu đang thay đổi.</blockquote>

<h2>MVCC: đọc và ghi đồng thời</h2>
<p>PostgreSQL dùng Multi-Version Concurrency Control. Mỗi statement/transaction đọc một snapshot phù hợp, trong khi update tạo row version mới. Vì vậy reader thường không chặn writer và writer không chặn reader như mô hình khóa đọc đơn giản.</p>
<p>MVCC tăng concurrency nhưng không loại bỏ conflict giữa hai writer. Row version cũ cũng phải được VACUUM thu dọn; transaction mở quá lâu giữ snapshot cũ và có thể làm bloat tăng.</p>

<h2>1. Transaction đúng cấu trúc</h2>
<pre><code>BEGIN;

UPDATE accounts
SET balance = balance - 500
WHERE id = 10 AND balance &gt;= 500;

UPDATE accounts
SET balance = balance + 500
WHERE id = 20;

COMMIT;</code></pre>
<p>Ứng dụng phải kiểm tra số hàng ảnh hưởng của câu trừ tiền; nếu là 0, rollback vì không đủ số dư hoặc account không tồn tại. Dùng phép update nguyên tử tốt hơn đọc balance về ứng dụng rồi ghi giá trị mới.</p>
<p>Không gọi API, gửi email hoặc chờ input của người dùng khi đang giữ transaction. Thực hiện side effect sau commit, hoặc dùng transactional outbox để liên kết ghi database với message đáng tin cậy.</p>

<h2>2. Read Committed: mặc định và phù hợp phần lớn CRUD</h2>
<p>Ở Read Committed, mỗi statement thấy snapshot tại lúc statement bắt đầu. Hai câu <code>SELECT</code> trong cùng transaction có thể thấy kết quả khác nếu transaction khác commit ở giữa.</p>
<p>Mức này phù hợp khi mỗi thao tác ghi có thể diễn đạt nguyên tử bằng <code>UPDATE ... WHERE</code>, UPSERT, unique/check constraint hoặc row lock rõ ràng. Nó không đủ khi business rule dựa trên nhiều lần đọc mà không khóa.</p>

<h2>3. Repeatable Read: một snapshot ổn định</h2>
<p>Repeatable Read cho transaction nhìn snapshot ổn định từ truy vấn đầu tiên. Nó hữu ích cho báo cáo nhiều câu cần cùng thời điểm và logic phức tạp, nhưng update xung đột có thể phát sinh lỗi serialization. Ứng dụng phải sẵn sàng chạy lại toàn bộ transaction.</p>
<p>Không dùng isolation cao như một cách tránh thiết kế concurrency. Transaction càng dài càng giữ snapshot lâu, tăng khả năng conflict và ảnh hưởng vacuum.</p>

<h2>4. Serializable: kết quả như chạy tuần tự</h2>
<p>Serializable của PostgreSQL dùng Serializable Snapshot Isolation để chỉ cho phép commit khi kết quả tương đương một thứ tự tuần tự hợp lệ. Database có thể hủy một transaction với SQLSTATE <code>40001</code> khi phát hiện anomaly có thể xảy ra.</p>
<p>Đây là lựa chọn mạnh cho invariant liên quan nhiều hàng hoặc predicate, nhưng hợp đồng là <strong>application phải retry</strong>. Không chỉ chạy lại câu SQL thất bại; phải chạy lại toàn bộ callback transaction, gồm mọi quyết định dựa trên dữ liệu đã đọc.</p>

<h2>5. SELECT FOR UPDATE và các row lock</h2>
<pre><code>BEGIN;

SELECT id, stock
FROM products
WHERE id = 42
FOR UPDATE;

UPDATE products
SET stock = stock - 1
WHERE id = 42 AND stock &gt; 0;

COMMIT;</code></pre>
<p><code>FOR UPDATE</code> ngăn transaction khác update, delete hoặc lấy lock xung đột trên hàng tới khi transaction kết thúc. Dùng khi cần đọc trạng thái, thực hiện logic trong ứng dụng rồi ghi lại. Nếu có thể dùng một câu <code>UPDATE ... WHERE stock &gt; 0 RETURNING ...</code>, cách đó thường ngắn và ít lock hơn.</p>
<ul><li><code>FOR NO KEY UPDATE</code>: lock yếu hơn khi không thay khóa liên quan foreign key.</li><li><code>FOR SHARE</code> và <code>FOR KEY SHARE</code>: bảo vệ các trường hợp đọc/tham chiếu cụ thể.</li><li><code>NOWAIT</code>: lỗi ngay thay vì chờ.</li><li><code>SKIP LOCKED</code>: bỏ hàng đang khóa, hữu ích cho worker queue nhưng không cho truy vấn tổng quát vì tạo view không nhất quán.</li></ul>

<h2>6. Deadlock hình thành như thế nào?</h2>
<p>Transaction A khóa account 10 rồi chờ account 20; transaction B đã khóa account 20 rồi chờ account 10. PostgreSQL phát hiện vòng chờ và abort một transaction với SQLSTATE <code>40P01</code>. Không được phụ thuộc transaction nào sẽ bị chọn.</p>
<p>Biện pháp phòng ngừa tốt nhất là khóa nhiều object theo cùng thứ tự:</p>
<pre><code>SELECT id
FROM accounts
WHERE id IN (10, 20)
ORDER BY id
FOR UPDATE;</code></pre>
<p>Sau đó mới cập nhật. Transaction phải ngắn và lấy lock mạnh nhất cần dùng từ đầu. Deadlock vẫn có thể xảy ra, nên code production cần retry giới hạn.</p>

<h2>7. Retry đúng cách với backoff</h2>
<pre><code>for ($attempt = 1; $attempt &lt;= 3; $attempt++) {
    try {
        return runWholeTransaction();
    } catch (DatabaseException $e) {
        if (! in_array($e-&gt;sqlState(), ['40001', '40P01'], true)) {
            throw $e;
        }

        if ($attempt === 3) {
            throw $e;
        }

        usleep(random_int(20_000, 100_000) * $attempt);
    }
}</code></pre>
<p>Retry toàn bộ transaction với số lần hữu hạn và jitter. Callback phải an toàn để chạy lại: không gửi email, charge payment hoặc publish message không idempotent trước commit. Unique violation <code>23505</code> đôi khi do race nhưng cũng có thể là lỗi dữ liệu cố định; không retry mù quáng.</p>

<h2>8. Timeout để thất bại có kiểm soát</h2>
<pre><code>SET LOCAL lock_timeout = '2s';
SET LOCAL statement_timeout = '10s';
SET LOCAL idle_in_transaction_session_timeout = '30s';</code></pre>
<p><code>lock_timeout</code> giới hạn thời gian chờ lock; <code>statement_timeout</code> giới hạn statement; <code>idle_in_transaction_session_timeout</code> xử lý session mở transaction rồi bỏ quên. Đặt theo SLA và workload, không dùng một giá trị cho mọi truy vấn.</p>
<p><code>SET LOCAL</code> chỉ có hiệu lực trong transaction hiện tại. Khi timeout, transaction có thể ở trạng thái aborted và cần rollback trước khi connection được trả về pool.</p>

<h2>9. Tìm session đang block production</h2>
<pre><code>SELECT
    blocked.pid AS blocked_pid,
    blocked.query AS blocked_query,
    blocker.pid AS blocker_pid,
    blocker.query AS blocker_query,
    now() - blocker.xact_start AS blocker_age
FROM pg_stat_activity blocked
JOIN pg_stat_activity blocker
  ON blocker.pid = ANY(pg_blocking_pids(blocked.pid))
WHERE cardinality(pg_blocking_pids(blocked.pid)) &gt; 0;</code></pre>
<p><code>pg_locks</code> cho cái nhìn toàn cluster về lock đã cấp và đang chờ; kết hợp <code>pg_stat_activity</code> để thấy query, user, state và transaction age. Tìm session <code>idle in transaction</code> vì nó có thể giữ lock và snapshot dù không chạy câu lệnh.</p>
<p>Không vội <code>pg_terminate_backend</code> trên production. Xác định blocker, tác động rollback, owner và khả năng ứng dụng retry. Sau sự cố, sửa code hoặc timeout thay vì chỉ kill session.</p>

<h2>10. Constraint và idempotency trước lock thủ công</h2>
<p>Ưu tiên để database bảo vệ invariant bằng <code>UNIQUE</code>, <code>CHECK</code>, <code>FOREIGN KEY</code>, exclusion constraint và atomic DML. Ví dụ idempotency key có unique index giúp hai request trùng không tạo hai payment record.</p>
<p>Advisory lock phù hợp cho tài nguyên logic không ánh xạ trực tiếp thành row, nhưng key phải ổn định và lock transaction-level thường an toàn hơn session-level. Advisory lock là cooperative: mọi code path phải tuân thủ cùng quy ước.</p>

<h2>Anti-pattern thường gặp</h2>
<ul><li>Đọc số dư/tồn kho rồi update không điều kiện.</li><li>Giữ transaction trong khi gọi HTTP hoặc chờ queue.</li><li>Retry riêng statement thay vì toàn transaction.</li><li>Lock các hàng theo thứ tự khác nhau giữa code path.</li><li>Dùng <code>SKIP LOCKED</code> cho truy vấn cần kết quả đầy đủ.</li><li>Để session <code>idle in transaction</code> qua connection pool.</li><li>Nâng mọi transaction lên Serializable nhưng không xử lý <code>40001</code>.</li></ul>

<h2>Checklist production</h2>
<ol><li>Business invariant có constraint ở database khi biểu diễn được.</li><li>Transaction ngắn, không chứa network side effect.</li><li>Isolation level được chọn theo anomaly cần ngăn.</li><li>Lock nhiều row theo thứ tự nhất quán.</li><li><code>40001</code> và <code>40P01</code> được retry toàn transaction có giới hạn.</li><li>Side effect dùng idempotency/outbox.</li><li>Timeout và connection-pool behavior được cấu hình.</li><li>Dashboard theo dõi lock wait, deadlock và transaction age.</li></ol>

<h2>Kết luận</h2>
<p>Concurrency đúng trong PostgreSQL là sự kết hợp của atomic SQL, constraint, MVCC, isolation và lock có chủ đích. Read Committed đáp ứng phần lớn CRUD nếu statement được thiết kế tốt; row lock xử lý read-modify-write; Serializable bảo vệ invariant phức tạp với yêu cầu retry. Transaction ngắn, thứ tự lock nhất quán và quan sát production tốt giúp deadlock trở thành lỗi có thể kiểm soát thay vì sự cố bí ẩn.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://www.postgresql.org/docs/current/mvcc.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Concurrency Control</a></li><li><a href="https://www.postgresql.org/docs/current/transaction-iso.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Transaction Isolation</a></li><li><a href="https://www.postgresql.org/docs/current/explicit-locking.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Explicit Locking and Deadlocks</a></li><li><a href="https://www.postgresql.org/docs/current/mvcc-serialization-failure-handling.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Serialization Failure Handling</a></li><li><a href="https://www.postgresql.org/docs/current/monitoring-locks.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Viewing Locks</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'PostgreSQL Transactions in Practice: Isolation, Row Locks, and Deadlocks',
            'slug' => 'postgresql-transactions-isolation-locks-deadlocks',
            'image' => 'postgresql-transactions-locks.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'PostgreSQL Transactions, Isolation, Locks, and Deadlocks',
            'meta_keywords' => 'PostgreSQL transaction, isolation level, SELECT FOR UPDATE, row lock, deadlock, MVCC, serializable retry',
            'meta_description' => 'Learn PostgreSQL transactions, Read Committed, Repeatable Read, Serializable, row locks, deadlocks, retries, and blocked-session monitoring.',
            'tags' => ['PostgreSQL', 'Transaction', 'MVCC', 'Database Lock', 'Deadlock', 'SQL', 'Backend', 'Concurrency'],
            'body' => <<<'HTML'
<p><strong>A transaction is more than wrapping several SQL statements in <code>BEGIN</code> and <code>COMMIT</code>.</strong> When requests concurrently read and modify data, an application must choose an isolation level, lock the correct rows, keep transactions short, and deliberately retry concurrency failures.</p>

<h2>ACID does not automatically protect every business rule</h2>
<p>PostgreSQL provides atomicity and constraint-level consistency, but it cannot infer “never oversell stock” or “seat count must remain below capacity” when an application reads and writes in separate steps. Two transactions may read valid state and make conflicting decisions.</p>
<blockquote>Constraints protect simple invariants; locks or higher isolation protect decisions based on changing data.</blockquote>

<h2>MVCC enables concurrent reads and writes</h2>
<p>PostgreSQL uses Multi-Version Concurrency Control. Each statement or transaction reads an appropriate snapshot while updates create new row versions. Readers therefore normally do not block writers in the manner of simple read locking.</p>
<p>MVCC does not remove writer conflicts. VACUUM must eventually clean old versions, and long transactions retain old snapshots and can increase bloat.</p>

<h2>1. Structure transactions correctly</h2>
<pre><code>BEGIN;

UPDATE accounts
SET balance = balance - 500
WHERE id = 10 AND balance &gt;= 500;

UPDATE accounts
SET balance = balance + 500
WHERE id = 20;

COMMIT;</code></pre>
<p>The application must check the affected-row count of the debit. If it is zero, roll back because funds are insufficient or the account is missing. An atomic update is safer than reading a balance into the application and writing a new value.</p>
<p>Do not call APIs, send email, or wait for user input while holding a transaction. Perform side effects after commit or use a transactional outbox to coordinate database writes with reliable messaging.</p>

<h2>2. Read Committed is the practical default</h2>
<p>At Read Committed, each statement sees a snapshot from the beginning of that statement. Two <code>SELECT</code> statements in one transaction may see different results if another transaction commits between them.</p>
<p>It works well when a write can be expressed atomically using <code>UPDATE ... WHERE</code>, UPSERT, unique/check constraints, or explicit row locks. It does not protect multi-read business decisions without additional controls.</p>

<h2>3. Repeatable Read provides a stable snapshot</h2>
<p>Repeatable Read gives a transaction a stable snapshot from its first query. It helps multi-query reports and complex logic, but conflicting updates can raise serialization failures. Applications must rerun the complete transaction.</p>
<p>Do not use higher isolation as a substitute for concurrency design. Longer transactions retain snapshots, increase conflict probability, and can interfere with vacuum.</p>

<h2>4. Serializable produces serial-equivalent results</h2>
<p>PostgreSQL implements Serializable Snapshot Isolation and permits commits only when results are equivalent to a valid serial order. It may abort a transaction with SQLSTATE <code>40001</code> when an anomaly could occur.</p>
<p>This is powerful for invariants spanning rows or predicates, but the contract requires <strong>application retries</strong>. Retry the entire transaction callback, including every decision based on prior reads, not only the failing statement.</p>

<h2>5. SELECT FOR UPDATE and row locks</h2>
<pre><code>BEGIN;

SELECT id, stock
FROM products
WHERE id = 42
FOR UPDATE;

UPDATE products
SET stock = stock - 1
WHERE id = 42 AND stock &gt; 0;

COMMIT;</code></pre>
<p><code>FOR UPDATE</code> prevents conflicting updates, deletes, and locks on the row until transaction end. Use it for read-modify-write logic in application code. When one <code>UPDATE ... WHERE stock &gt; 0 RETURNING ...</code> can express the operation, it is generally shorter and locks less.</p>
<ul><li><code>FOR NO KEY UPDATE</code>: weaker when foreign-key-relevant keys do not change.</li><li><code>FOR SHARE</code> and <code>FOR KEY SHARE</code>: protect specific read/reference cases.</li><li><code>NOWAIT</code>: fail immediately instead of waiting.</li><li><code>SKIP LOCKED</code>: skip locked rows, useful for worker queues but not general queries because it returns an inconsistent view.</li></ul>

<h2>6. How deadlocks form</h2>
<p>Transaction A locks account 10 and waits for 20; transaction B holds 20 and waits for 10. PostgreSQL detects the cycle and aborts one transaction with SQLSTATE <code>40P01</code>. Never depend on which transaction is selected.</p>
<p>The best defense is a consistent lock order:</p>
<pre><code>SELECT id
FROM accounts
WHERE id IN (10, 20)
ORDER BY id
FOR UPDATE;</code></pre>
<p>Update after locking. Transactions should be short and acquire the strongest needed lock first. Deadlocks can still occur, so production code needs bounded retries.</p>

<h2>7. Retry correctly with backoff</h2>
<pre><code>for ($attempt = 1; $attempt &lt;= 3; $attempt++) {
    try {
        return runWholeTransaction();
    } catch (DatabaseException $e) {
        if (! in_array($e-&gt;sqlState(), ['40001', '40P01'], true)) {
            throw $e;
        }

        if ($attempt === 3) {
            throw $e;
        }

        usleep(random_int(20_000, 100_000) * $attempt);
    }
}</code></pre>
<p>Retry the complete transaction with a finite limit and jitter. The callback must be safe to repeat: do not email, charge a payment, or publish a non-idempotent message before commit. A <code>23505</code> unique violation can be a race or a permanent data error, so do not retry it blindly.</p>

<h2>8. Fail predictably with timeouts</h2>
<pre><code>SET LOCAL lock_timeout = '2s';
SET LOCAL statement_timeout = '10s';
SET LOCAL idle_in_transaction_session_timeout = '30s';</code></pre>
<p><code>lock_timeout</code> limits waiting for locks; <code>statement_timeout</code> limits statements; <code>idle_in_transaction_session_timeout</code> handles abandoned open transactions. Tune them to the SLA and workload rather than applying one value everywhere.</p>
<p><code>SET LOCAL</code> lasts only for the current transaction. After a timeout, the transaction may be aborted and must be rolled back before returning its connection to a pool.</p>

<h2>9. Find blocking sessions in production</h2>
<pre><code>SELECT
    blocked.pid AS blocked_pid,
    blocked.query AS blocked_query,
    blocker.pid AS blocker_pid,
    blocker.query AS blocker_query,
    now() - blocker.xact_start AS blocker_age
FROM pg_stat_activity blocked
JOIN pg_stat_activity blocker
  ON blocker.pid = ANY(pg_blocking_pids(blocked.pid))
WHERE cardinality(pg_blocking_pids(blocked.pid)) &gt; 0;</code></pre>
<p><code>pg_locks</code> provides a cluster-wide view of granted and waiting locks. Combine it with <code>pg_stat_activity</code> for query, user, state, and transaction age. Find <code>idle in transaction</code> sessions that may retain locks and snapshots while doing no work.</p>
<p>Do not immediately call <code>pg_terminate_backend</code>. Identify the blocker, rollback impact, owner, and retry behavior. Afterward, fix code or timeouts instead of merely killing the session.</p>

<h2>10. Prefer constraints and idempotency to manual locks</h2>
<p>Let the database enforce representable invariants using <code>UNIQUE</code>, <code>CHECK</code>, <code>FOREIGN KEY</code>, exclusion constraints, and atomic DML. A unique idempotency key, for example, prevents duplicate requests from creating two payment records.</p>
<p>Advisory locks fit logical resources that do not map to a row, but keys must be stable and transaction-level locks are often safer than session-level locks. They are cooperative: every code path must follow the same convention.</p>

<h2>Common anti-patterns</h2>
<ul><li>Reading a balance or stock count and then updating without a condition.</li><li>Holding a transaction during HTTP calls or queue waits.</li><li>Retrying one statement instead of the complete transaction.</li><li>Locking rows in different orders across code paths.</li><li>Using <code>SKIP LOCKED</code> when complete query results are required.</li><li>Returning <code>idle in transaction</code> sessions to a pool.</li><li>Making everything Serializable without handling <code>40001</code>.</li></ul>

<h2>Production checklist</h2>
<ol><li>Database constraints protect every representable business invariant.</li><li>Transactions are short and contain no network side effects.</li><li>Isolation level matches the anomaly being prevented.</li><li>Multiple rows are locked in a consistent order.</li><li><code>40001</code> and <code>40P01</code> trigger bounded whole-transaction retries.</li><li>Side effects use idempotency or an outbox.</li><li>Timeouts and connection-pool behavior are configured.</li><li>Dashboards monitor lock waits, deadlocks, and transaction age.</li></ol>

<h2>Conclusion</h2>
<p>Correct PostgreSQL concurrency combines atomic SQL, constraints, MVCC, isolation, and deliberate locking. Read Committed handles most CRUD when statements are designed well; row locks protect read-modify-write; Serializable protects complex invariants with a retry requirement. Short transactions, consistent lock order, and good production visibility turn deadlocks into controlled errors instead of mysteries.</p>

<h2>References</h2>
<ul><li><a href="https://www.postgresql.org/docs/current/mvcc.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Concurrency Control</a></li><li><a href="https://www.postgresql.org/docs/current/transaction-iso.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Transaction Isolation</a></li><li><a href="https://www.postgresql.org/docs/current/explicit-locking.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Explicit Locking and Deadlocks</a></li><li><a href="https://www.postgresql.org/docs/current/mvcc-serialization-failure-handling.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Serialization Failure Handling</a></li><li><a href="https://www.postgresql.org/docs/current/monitoring-locks.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Viewing Locks</a></li></ul>
HTML,
        ],
    ],
];
