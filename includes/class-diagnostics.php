<?php
/** Administrator-only, opt-in privacy smoke tests using synthetic inquiries. */
defined('ABSPATH') || exit;
final class WEM_Inquiry_Diagnostics {
    const PAGE = 'wem-inquiry-privacy';
    const MARKER_META = '_wem_inquiry_diagnostic';
    public static function register() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_wem_inquiry_privacy_run', array(__CLASS__, 'run'));
        add_action('wem_inquiry_diagnostic_cleanup', array(__CLASS__, 'cleanup'));
    }
    public static function menu() {
        add_options_page('WEM Inquiry Privacy', 'WEM Inquiry Privacy', 'manage_options', self::PAGE, array(__CLASS__, 'page'));
    }
    private static function result_key() {
        return 'wem_inquiry_diag_result_' . get_current_user_id();
    }
    public static function page() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You are not allowed to access this page.', 'wem-inquiry'));
        $report = get_transient(self::result_key());
        echo '<div class="wrap"><h1>WEM Inquiry — 隐私检测</h1>';
        echo '<p>点击后创建一条<strong>虚构且已批准</strong>的询盘，并通过无登录 Cookie 的本站 HTTP 请求检测 REST、评论 Feed 和来源页 HTML。测试完成后删除记录；不会读取或修改真实客户询盘。</p>';
        echo '<p><strong>检测限制：</strong>服务器回环请求不等于真正的外网访客测试；通过仅代表本次检查的接口未发现测试标识。无法覆盖第三方自定义接口、直接 SQL 查询、缓存历史副本或插件停用后的风险。</p>';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
        echo '<input type="hidden" name="action" value="wem_inquiry_privacy_run">';
        wp_nonce_field('wem_inquiry_privacy_run');
        submit_button('开始一键检测', 'primary', 'submit', false);
        echo '</form>';
        if (is_array($report)) {
            echo '<h2>最近一次检测</h2><p>时间：' . esc_html($report['time']) . '（站点时间）；结果仅在后台临时保留，不包含测试邮箱或测试正文。</p>';
            if (!empty($report['notice'])) echo '<div class="notice notice-warning inline"><p>' . esc_html($report['notice']) . '</p></div>';
            echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>检测项目</th><th>状态</th><th>HTTP</th><th>说明</th></tr></thead><tbody>';
            foreach ((array) $report['checks'] as $item) {
                $labels = array('pass'=>'本次未发现', 'risk'=>'发现泄露', 'unknown'=>'无法判断');
                $color = $item['status']==='risk'?'#b32d2e':($item['status']==='pass'?'#177245':'#916900');
                echo '<tr><td>' . esc_html($item['name']) . '</td><td style="font-weight:700;color:' . esc_attr($color) . '">' . esc_html($labels[$item['status']]) . '</td><td>' . esc_html((string)$item['http']) . '</td><td>' . esc_html($item['detail']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p>需要额外人工使用无痕浏览器检查，尤其是已通过批准的虚构询盘不会出现在公开主题、REST 或 Feed 中。不要在生产环境停用隐私过滤插件。</p></div>';
    }
    private static function add_check(&$checks, $name, $status, $http, $detail) {
        $checks[] = array('name'=>$name,'status'=>$status,'http'=>$http,'detail'=>$detail);
    }
    public static function cleanup($id) {
        $id=absint($id);
        if (!$id || get_comment_meta($id,self::MARKER_META,true) !== '1') return;
        wp_delete_comment($id,true);
    }
    public static function run() {
        if (!current_user_can('manage_options')) wp_die('Forbidden', '', array('response'=>403));
        check_admin_referer('wem_inquiry_privacy_run');
        $back=admin_url('options-general.php?page='.self::PAGE);
        $key=self::result_key();
        $report=array('time'=>current_time('mysql'),'checks'=>array(),'notice'=>'');
        $lock='wem_inquiry_diag_lock_'.get_current_user_id();
        if (get_transient($lock)) {
            $report['notice']='检测正在运行，请稍后重试。';
            set_transient($key,$report,20*MINUTE_IN_SECONDS);
            wp_safe_redirect($back); exit;
        }
        set_transient($lock,1,90);
        $id=0;
        try {
            // Pick a published public Page/Post as the source without relying on an admin-selected URL.
            $posts=get_posts(array('post_type'=>array('page','post'),'post_status'=>'publish','numberposts'=>1,'orderby'=>'date','order'=>'DESC','suppress_filters'=>true));
            $source=$posts ? $posts[0] : null;
            if (!$source || !get_permalink($source)) {
                $report['notice']='未找到已发布且可访问的 Page/Post，未生成测试询盘。';
            } else {
                $token=wp_generate_password(28,false,false);
                $marker='WEM_PRIVACY_TEST_'.$token;
                $email='privacy-test-' . strtolower($token) . '@example.invalid';
                $id=wp_insert_comment(array(
                    'comment_post_ID'=>$source->ID,
                    'comment_author'=>'WEM Diagnostic (synthetic)',
                    'comment_author_email'=>$email,
                    'comment_content'=>$marker,
                    'comment_type'=>WEM_INQUIRY_TYPE,
                    'comment_approved'=>'1',
                    'comment_agent'=>'WEM Inquiry Privacy Diagnostic',
                ));
                if (!$id || is_wp_error($id)) {
                    $id=0;$report['notice']='无法创建虚构测试询盘；没有进行 HTTP 检测。';
                } else {
                    update_comment_meta($id,self::MARKER_META,'1');
                    wp_schedule_single_event(time()+600,'wem_inquiry_diagnostic_cleanup',array($id));
                    $saved=get_comment($id);
                    if (!$saved || $saved->comment_type!==WEM_INQUIRY_TYPE || (string)$saved->comment_approved!=='1') {
                        $report['notice']='无法建立 Approved 类型的测试记录，本次检测中止。';
                    } else {
                        self::add_check($report['checks'],'虚构记录的类型和 Approved 状态','pass','—','测试记录已确认；不代表公开接口安全。');
                        $urls=array(
                            'REST 单条'=>rest_url('wp/v2/comments/'.$id),
                            'REST 来源页列表'=>add_query_arg(array('post'=>$source->ID,'per_page'=>100),rest_url('wp/v2/comments')),
                            'REST 全站列表'=>add_query_arg(array('per_page'=>100),rest_url('wp/v2/comments')),
                            '全站评论 Feed'=>get_feed_link('comments_rss2'),
                            '来源页评论 Feed'=>get_post_comments_feed_link($source->ID),
                            '来源页 HTML'=>get_permalink($source),
                        );
                        foreach ($urls as $name=>$url) {
                            // Cache-busting on our own site; disallow external redirect targets.
                            $url=add_query_arg('wem_diag',rawurlencode($token),$url);
                            $response=wp_remote_get($url,array('timeout'=>7,'redirection'=>0,'cookies'=>array(),
                                'headers'=>array('Cache-Control'=>'no-cache, no-store','Pragma'=>'no-cache','Accept'=>'*/*'),
                                'user-agent'=>'WEM-Inquiry-Privacy-Check/1.1.0',
                                'limit_response_size'=>1048576,
                                'reject_unsafe_urls'=>true,
                            ));
                            if (is_wp_error($response)) {
                                self::add_check($report['checks'],$name,'unknown','—','服务器请求失败：'.$response->get_error_message());continue;
                            }
                            $code=(int)wp_remote_retrieve_response_code($response);
                            $body=(string)wp_remote_retrieve_body($response);
                            if (strpos($body,$marker)!==false || strpos($body,$email)!==false) {
                                self::add_check($report['checks'],$name,'risk',$code,'匿名 HTTP 响应包含虚构询盘标识。');
                            } elseif ($code>=200 && $code<300) {
                                self::add_check($report['checks'],$name,'pass',$code,'本次响应未发现标识；可能受分页、主题渲染或缓存影响，非全面保证。');
                            } elseif ($name==='REST 单条' && $code===404 && strpos($body,'wem_inquiry_private')!==false) {
                                self::add_check($report['checks'],$name,'pass',$code,'命中插件私密访问拦截规则。');
                            } else {
                                self::add_check($report['checks'],$name,'unknown',$code,'非预期 HTTP 状态；可能被 CDN、防火墙或服务器限制。');
                            }
                        }
                    }
                }
            }
        } finally {
            if ($id) self::cleanup($id);
            delete_transient($lock);
            set_transient($key,$report,20*MINUTE_IN_SECONDS);
        }
        wp_safe_redirect($back);exit;
    }
}
