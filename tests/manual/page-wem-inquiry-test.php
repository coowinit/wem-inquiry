<?php
/**
 * Template Name: WEM Inquiry Privacy Test
 * Description: Standalone test for the private admin-post submission endpoint.
 * Install in the ACTIVE Classic Theme root. Do not use as production contact page.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Diagnostic only: remote HTTP request with no WordPress authentication cookies.
 * This is a server-side loopback check, not an external third-party penetration test.
 */
function wem_test_anon_fetch( $url ) {
    $response = wp_remote_get( $url, array(
        'timeout' => 8,
        'redirection' => 2,
        'headers' => array( 'Accept' => 'application/json, application/rss+xml, */*' ),
        'cookies' => array(),
        'user-agent' => 'WEM-Inquiry-Diagnostic/0.1.5',
    ) );
    if ( is_wp_error( $response ) ) {
        return array( 'code' => 0, 'error' => $response->get_error_message(), 'body' => '' );
    }
    return array(
        'code' => (int) wp_remote_retrieve_response_code( $response ),
        'error' => '',
        'body' => (string) wp_remote_retrieve_body( $response ),
    );
}

/**
 * Evaluate whether a given inquiry was visible in one specific anonymous response.
 * We avoid echoing personal data into the HTML diagnostics.
 */
function wem_test_response_verdict( $response, $needle, $is_direct = false ) {
    if ( $response['error'] || ! $response['code'] ) {
        return array( 'unknown', '无法完成站点自身 HTTP 请求：' . $response['error'] );
    }
    if ( $needle !== '' && strpos( $response['body'], $needle ) !== false ) {
        return array( 'fail', '响应正文包含测试询盘的邮箱，存在公开暴露风险。' );
    }
    if ( $is_direct && in_array( $response['code'], array( 401, 403, 404 ), true ) ) {
        return array( 'pass', '返回 HTTP ' . $response['code'] . '，未返回该条询盘。' );
    }
    if ( $response['code'] >= 400 ) {
        return array( 'unknown', 'HTTP ' . $response['code'] . '；无法确认是权限阻止还是其他错误。' );
    }
    if ( $response['code'] >= 200 && $response['code'] < 300 ) {
        return array( 'sample', 'HTTP ' . $response['code'] . '，本次响应未发现测试邮箱；仅代表本次请求，非全面安全证明。' );
    }
    return array( 'unknown', '收到 HTTP ' . $response['code'] . '，需人工检查。' );
}

function wem_test_admin_report( $post_id ) {
    if ( ! current_user_can( 'moderate_comments' ) ) { return; }
    $selected = isset( $_GET['wem_test_id'] ) ? absint( $_GET['wem_test_id'] ) : 0;
    $comment = $selected ? get_comment( $selected ) : null;
    if ( ! $comment || (int) $comment->comment_post_ID !== (int) $post_id || 'wem_inquiry' !== $comment->comment_type ) {
        $results = get_comments( array(
            'post_id' => $post_id,
            'type' => 'wem_inquiry',
            'status' => 'all',
            'number' => 1,
            'orderby' => 'comment_ID',
            'order' => 'DESC',
        ) );
        $comment = $results ? $results[0] : null;
    }
    echo '<section class="wem-test-report" aria-labelledby="wem-test-report-heading"><h2 id="wem-test-report-heading">隐私诊断（仅管理员可见）</h2>';
    echo '<p>诊断通过服务器无 Cookie 请求访问本站公开 REST / Feed，并检查是否包含测试记录的邮箱。不显示客户姓名、邮箱、电话或正文。若服务器回环不可用，会标记为无法判断。</p>';
    if ( ! $comment ) {
        echo '<p>当前页面没有找到 <code>wem_inquiry</code> 测试记录。请先使用上面的表单提交，再登录管理员账号刷新此页。</p></section>';
        return;
    }
    echo '<p>检查记录 ID：<strong>' . esc_html( (string) $comment->comment_ID ) . '</strong>；所属页面：' . esc_html( (string) $post_id ) . '；当前审核状态：<strong>' . esc_html( (string) $comment->comment_approved ) . '</strong>。</p>';
    $nonce_url = wp_nonce_url( add_query_arg( 'wem_diag_run', '1', get_permalink( $post_id ) ), 'wem_diag_run', 'wem_diag_nonce' );
    echo '<p><a class="wem-test-run" href="' . esc_url( $nonce_url ) . '">运行匿名 REST / Feed 检查</a> <small>点击后可能等待约 30 秒；仅管理员可运行</small></p>';
    if ( ! isset( $_GET['wem_diag_run'] ) || '1' !== $_GET['wem_diag_run'] ) {
        echo '<p>尚未运行 HTTP 检查。下面显示数据库身份检查。</p>';
    }
    $checks = array();
    $checks[] = array( '记录类型', 'pass', '数据库 comment_type = wem_inquiry。' );
    $checks[] = array( '附加字段', 'pass', '已找到该记录；Phone / Country ' . ( metadata_exists( 'comment', $comment->comment_ID, 'phone' ) && metadata_exists( 'comment', $comment->comment_ID, 'country' ) ? '均已写入。' : '至少一项没有写入（若留空属正常）。' ) );
    $run = isset( $_GET['wem_diag_run'] ) && '1' === $_GET['wem_diag_run'] && isset( $_GET['wem_diag_nonce'] ) && is_string( $_GET['wem_diag_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wem_diag_nonce'] ) ), 'wem_diag_run' );
    if ( $run ) {
        $id = absint( $comment->comment_ID );
        $email = (string) $comment->comment_author_email;
        $urls = array(
            array( 'REST 评论列表（关联页面）', add_query_arg( array( 'post' => $post_id, 'per_page' => 100 ), rest_url( 'wp/v2/comments' ) ), false ),
            array( 'REST 评论列表（全站第一页）', add_query_arg( array( 'per_page' => 100 ), rest_url( 'wp/v2/comments' ) ), false ),
            array( 'REST 单条评论', rest_url( 'wp/v2/comments/' . $id ), true ),
            array( '评论 Feed（全站）', get_feed_link( 'comments_rss2' ), false ),
            array( '评论 Feed（关联页面）', add_query_arg( array( 'feed' => 'comments-rss2', 'p' => $post_id ), home_url( '/' ) ), false ),
        );
        foreach ( $urls as $i => $item ) {
            $resp = wem_test_anon_fetch( $item[1] );
            $verdict = wem_test_response_verdict( $resp, $email, $item[2] );
            $checks[] = array( $item[0], $verdict[0], $verdict[1] );
        }
    }
    foreach ( $checks as $item ) {
        $labels = array( 'pass'=>'通过', 'fail'=>'风险', 'sample'=>'仅本次未发现', 'unknown'=>'无法判断' );
        echo '<div class="wem-test-result wem-test-' . esc_attr( $item[1] ) . '"><strong>' . esc_html( $item[0] ) . '</strong><span>' . esc_html( $labels[ $item[1] ] ) . '</span><p>' . esc_html( $item[2] ) . '</p></div>';
    }
    echo '<div class="test-note"><strong>误批准测试：</strong>当前状态为 ' . esc_html( (string) $comment->comment_approved ) . '。只有确实发生状态变更、并在状态变更后重新执行匿名接口检查，才能算完成此项。请勿为了测试直接修改生产数据。插件已包含防误批准 Hook，但它不等同于完成真实演练。</div>';
    echo '<p><strong>结论限制：</strong>未审批记录本来通常不会对匿名用户公开；上述检查不能完全证明“误批准后”或“其他插件直接 SQL 输出”一定安全。CDN、REST 禁用策略、回环请求阻止都可能影响结果。正式验收还应使用无痕浏览器从站外再核对。</p></section>';
}

get_header();
$post_id = get_queried_object_id();
$status = isset( $_GET['wem_inquiry_status'] ) && is_string( $_GET['wem_inquiry_status'] )
    ? sanitize_key( wp_unslash( $_GET['wem_inquiry_status'] ) ) : '';
?>
<main id="main-content" class="wem-inquiry-test">
    <style>
      .wem-inquiry-test {max-width:760px;margin:48px auto;padding:0 24px 64px;}
      .wem-inquiry-test h1 {margin-bottom:16px;}
      .wem-inquiry-test .test-note {background:#f4f6f8;padding:15px 18px;margin:20px 0;line-height:1.7;}
      .wem-inquiry-test .test-field {margin:0 0 18px;}
      .wem-inquiry-test label {display:block;font-weight:600;margin-bottom:6px;}
      .wem-inquiry-test input:not([type="hidden"]),.wem-inquiry-test textarea {box-sizing:border-box;width:100%;border:1px solid #a8acb1;border-radius:5px;padding:12px;font:inherit;}
      .wem-inquiry-test button {border:0;border-radius:5px;padding:12px 25px;background:#252a30;color:#fff;cursor:pointer;}
      .wem-inquiry-test :focus-visible {outline:3px solid #276bc6;outline-offset:3px;}
      .wem-test-report{margin-top:38px;padding:20px;border:1px solid #d2d7db;border-radius:8px;background:#fafbfc;line-height:1.65;}
      .wem-test-report h2{margin:0 0 12px;font-size:22px;}
      .wem-test-run{display:inline-block;background:#252a30;color:#fff!important;text-decoration:none;border-radius:5px;padding:9px 14px;margin:6px 0;}
      .wem-test-result{border:1px solid #cfd5d9;background:white;padding:11px 14px;margin:10px 0;border-radius:6px;}
      .wem-test-result strong{margin-right:12px;}
      .wem-test-result span{font-weight:bold;}
      .wem-test-result p{margin:5px 0 0;font-size:14px;}
      .wem-test-pass{border-left:4px solid #238251;} .wem-test-fail{border-left:4px solid #c53030;}
      .wem-test-unknown{border-left:4px solid #b47a17;} .wem-test-sample{border-left:4px solid #4375a2;}
    </style>
    <h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
    <div class="test-note">Test only: this form uses the native WordPress field names but submits to a separate private inquiry endpoint. It does not require comments to be enabled on this page.</div>
    <?php if ( 'sent' === $status ) : ?>
        <p role="status">Thank you. Your inquiry has been received. Check the private inquiry list in WP Admin.</p>
    <?php elseif ( 'error' === $status ) : ?>
        <p role="alert">Submission failed or was rate-limited. Please review the fields and try again later.</p>
    <?php endif; ?>
    <?php if ( function_exists( 'wem_inquiry_submit' ) && $post_id ) : ?>
        <form id="wem-inquiry-privacy-test" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wem_inquiry_submit">
            <input type="hidden" name="wem_post_id" value="<?php echo esc_attr( $post_id ); ?>">
            <?php wp_nonce_field( 'wem_inquiry_' . $post_id, 'wem_inquiry_nonce' ); ?>
            <div class="test-field"><label for="inquiry-author">Name *</label><input id="inquiry-author" name="author" type="text" maxlength="120" autocomplete="name" required></div>
            <div class="test-field"><label for="inquiry-email">Email *</label><input id="inquiry-email" name="email" type="email" maxlength="190" autocomplete="email" required></div>
            <div class="test-field"><label for="inquiry-phone">Phone</label><input id="inquiry-phone" name="phone" type="tel" maxlength="80" autocomplete="tel"></div>
            <div class="test-field"><label for="inquiry-country">Country</label><input id="inquiry-country" name="country" type="text" maxlength="120" autocomplete="country-name"></div>
            <div class="test-field"><label for="inquiry-comment">Message *</label><textarea id="inquiry-comment" name="comment" maxlength="10000" rows="6" required></textarea></div>
            <div aria-hidden="true" style="position:absolute;left:-9999px"><label for="inquiry-website">Leave blank</label><input id="inquiry-website" name="wem_website" type="text" tabindex="-1" autocomplete="off"></div>
            <button type="submit">Send Private Inquiry</button>
        </form>
    <?php else : ?>
        <p role="alert">WEM Inquiry is not active or this page is not configured.</p>
    <?php endif; ?>
    <?php wem_test_admin_report( $post_id ); ?>
</main>
<?php get_footer(); ?>
