<?php
if (!defined('ABSPATH')) exit;

class Aipex_Podcast_Context_Fixes {
    public static function init(){
        add_action('init', [__CLASS__, 'register_post_types'], 6);
        add_action('init', [__CLASS__, 'replace_context_shortcodes'], 30);
        add_action('plugins_loaded', [__CLASS__, 'replace_ajax_handlers'], 30);
    }

    public static function register_post_types(){
        register_post_type('aipex_presenter', [
            'labels'=>['name'=>'Presenters','singular_name'=>'Presenter','add_new_item'=>'Add Presenter','edit_item'=>'Edit Presenter'],
            'public'=>true,
            'publicly_queryable'=>true,
            'exclude_from_search'=>false,
            'query_var'=>'aipex_presenter',
            'show_ui'=>true,
            'show_in_menu'=>'edit.php?post_type=aipex_podcast',
            'has_archive'=>'presenters',
            'rewrite'=>['slug'=>'presenter','with_front'=>false,'feeds'=>false,'pages'=>true],
            'supports'=>['title','editor','thumbnail','excerpt'],
            'show_in_rest'=>true,
        ]);
    }

    public static function replace_context_shortcodes(){
        foreach (['aipex_podcast_grid','aipex_latest_podcasts','aipex_series_podcasts','aipex_show_podcasts','aipex_presenter_podcasts','aipex_floating_player'] as $shortcode) {
            remove_shortcode($shortcode);
            add_shortcode($shortcode, [__CLASS__, $shortcode]);
        }
    }

    public static function replace_ajax_handlers(){
        remove_action('wp_ajax_aipex_grid_load_more', ['Aipex_Podcast_Shortcodes','ajax_grid_load_more']);
        remove_action('wp_ajax_nopriv_aipex_grid_load_more', ['Aipex_Podcast_Shortcodes','ajax_grid_load_more']);
        add_action('wp_ajax_aipex_grid_load_more', [__CLASS__, 'ajax_grid_load_more']);
        add_action('wp_ajax_nopriv_aipex_grid_load_more', [__CLASS__, 'ajax_grid_load_more']);
    }

    private static function aliases($key){
        $aliases = [
            'series' => ['series','aipex_series','podcast_series','series_id'],
            'presenters' => ['presenters','aipex_presenters','presenter','presenter_id','ovau_host_id'],
        ];
        return $aliases[$key] ?? [$key];
    }

    private static function relationship_meta_query($key, $id){
        $id = absint($id);
        $or = ['relation'=>'OR'];
        foreach (self::aliases($key) as $meta_key) {
            $or[] = ['key'=>$meta_key,'value'=>'"'.$id.'"','compare'=>'LIKE'];
            $or[] = ['key'=>$meta_key,'value'=>'i:'.$id.';','compare'=>'LIKE'];
            $or[] = ['key'=>$meta_key,'value'=>'s:'.strlen((string)$id).':"'.$id.'";','compare'=>'LIKE'];
            $or[] = ['key'=>$meta_key,'value'=>(string)$id,'compare'=>'='];
        }
        return $or;
    }

    private static function resolve_context_id($post_type, $atts, $keys){
        foreach ($keys as $key) {
            if (empty($atts[$key])) continue;
            $value = $atts[$key];
            if (is_numeric($value)) return absint($value);
            $post = get_page_by_path(sanitize_title($value), OBJECT, $post_type);
            if ($post) return absint($post->ID);
        }
        if (is_singular($post_type)) return get_the_ID();
        $qid = get_queried_object_id();
        if ($qid && get_post_type($qid) === $post_type) return absint($qid);
        global $post;
        return ($post && isset($post->ID) && get_post_type($post->ID) === $post_type) ? absint($post->ID) : 0;
    }

    private static function query_episodes($args=[]){
        $meta = [];
        if (!empty($args['series_id'])) $meta[] = self::relationship_meta_query('series', $args['series_id']);
        if (!empty($args['presenter_id'])) $meta[] = self::relationship_meta_query('presenters', $args['presenter_id']);
        $query = [
            'post_type'=>'aipex_podcast',
            'posts_per_page'=>12,
            'post_status'=>'publish',
            'orderby'=>'date',
            'order'=>'DESC',
        ];
        if ($meta) $query['meta_query'] = count($meta) > 1 ? array_merge(['relation'=>'AND'], $meta) : $meta;
        unset($args['series_id'], $args['presenter_id']);
        return new WP_Query(array_merge($query, $args));
    }

    public static function aipex_podcast_grid($atts=[]){
        $atts = shortcode_atts(['limit'=>12,'series_id'=>0,'series'=>'','show'=>'','presenter_id'=>0,'presenter'=>'','context'=>'auto'], $atts);
        if ($atts['context'] !== 'all') {
            if (!$atts['series_id'] && !$atts['series'] && !$atts['show']) $atts['series_id'] = self::resolve_context_id('aipex_series', [], []);
            if (!$atts['presenter_id'] && !$atts['presenter']) $atts['presenter_id'] = self::resolve_context_id('aipex_presenter', [], []);
        }
        if (!$atts['series_id']) $atts['series_id'] = self::resolve_context_id('aipex_series', $atts, ['series','show']);
        if (!$atts['presenter_id']) $atts['presenter_id'] = self::resolve_context_id('aipex_presenter', $atts, ['presenter']);
        return self::grid($atts);
    }

    public static function aipex_latest_podcasts($atts=[]){
        $atts['context'] = 'all';
        return self::aipex_podcast_grid($atts);
    }

    public static function aipex_series_podcasts($atts=[]){
        $atts = shortcode_atts(['limit'=>12,'series_id'=>0,'series'=>'','show'=>''], $atts);
        $atts['series_id'] = self::resolve_context_id('aipex_series', $atts, ['series_id','series','show']);
        return self::grid($atts);
    }

    public static function aipex_show_podcasts($atts=[]){
        return self::aipex_series_podcasts($atts);
    }

    public static function aipex_presenter_podcasts($atts=[]){
        $atts = shortcode_atts(['limit'=>12,'presenter_id'=>0,'presenter'=>''], $atts);
        $atts['presenter_id'] = self::resolve_context_id('aipex_presenter', $atts, ['presenter_id','presenter']);
        return self::grid($atts);
    }

    private static function grid($args=[]){
        wp_enqueue_style('aipex-podcast');
        wp_enqueue_script('aipex-podcast');
        $limit = !empty($args['limit']) ? absint($args['limit']) : 12;
        $page = !empty($args['page']) ? absint($args['page']) : 1;
        $q = self::query_episodes([
            'posts_per_page'=>$limit,
            'paged'=>$page,
            'series_id'=>$args['series_id'] ?? 0,
            'presenter_id'=>$args['presenter_id'] ?? 0,
        ]);
        $context = ['kind'=>'episodes','series_id'=>absint($args['series_id'] ?? 0),'presenter_id'=>absint($args['presenter_id'] ?? 0),'limit'=>$limit];
        $out = '<div class="aipex-card-grid aipex-episode-grid" data-context="'.esc_attr(wp_json_encode($context)).'">';
        while ($q->have_posts()) { $q->the_post(); $out .= Aipex_Podcast_Shortcodes::episode_card(get_the_ID()); }
        wp_reset_postdata();
        $out .= '</div>';
        if ($q->max_num_pages > $page) $out .= '<p class="aipex-load-wrap"><button class="aipex-btn aipex-load-more" type="button" data-page="1" data-kind="episodes" data-context="'.esc_attr(wp_json_encode($context)).'"><span class="aipex-btn-icon">＋</span> Load More Episodes</button></p>';
        return $out;
    }

    public static function aipex_floating_player($atts=[]){
        $atts = shortcode_atts(['limit'=>12,'context'=>'auto','series_id'=>0,'series'=>'','show'=>'','presenter_id'=>0,'presenter'=>''], $atts);
        wp_enqueue_style('aipex-podcast');
        wp_enqueue_script('aipex-podcast');
        $query_args = ['posts_per_page'=>absint($atts['limit'])];
        if ($atts['context'] !== 'all') {
            $series_id = self::resolve_context_id('aipex_series', $atts, ['series_id','series','show']);
            $presenter_id = self::resolve_context_id('aipex_presenter', $atts, ['presenter_id','presenter']);
            if ($series_id) $query_args['series_id'] = $series_id;
            if ($presenter_id) $query_args['presenter_id'] = $presenter_id;
        }
        $q = self::query_episodes($query_args);
        $tracks = [];
        while ($q->have_posts()) { $q->the_post();
            $url = Aipex_Podcast_Utils::audio_url(get_the_ID());
            if (!$url) continue;
            $tracks[] = ['title'=>get_the_title(),'url'=>$url,'date'=>get_the_date('Y-m-d'),'duration'=>Aipex_Podcast_Utils::field('duration', get_the_ID(), '')];
        }
        wp_reset_postdata();
        if (!$tracks) return '';
        $first = $tracks[0];
        $out = '<div class="aipex-floating-player aipex-floating-player-v3" data-current="0"><div class="aipex-floating-bar">';
        $out .= '<button class="aipex-player-minimise" type="button" aria-label="Minimise player">🎧</button><div class="aipex-floating-now"><span>Now playing</span><strong>'.esc_html($first['title']).'</strong></div>';
        $out .= '<audio id="aipex-floating-audio" controls preload="none" src="'.esc_url($first['url']).'"></audio><div class="aipex-floating-controls"><button type="button" class="aipex-float-prev" aria-label="Previous episode">‹</button><button type="button" class="aipex-float-next" aria-label="Next episode">›</button></div>';
        $out .= '<button class="aipex-episode-drawer-toggle" type="button"><span class="aipex-btn-icon">☰</span> Episodes <span class="aipex-track-count">'.count($tracks).'</span> <span class="aipex-caret">⌄</span></button></div><div class="aipex-episode-drawer" hidden><div class="aipex-episode-drawer-head"><strong>Choose an episode</strong><input type="search" class="aipex-episode-search" placeholder="Search episodes…"></div><div class="aipex-episode-list">';
        foreach ($tracks as $i=>$track) {
            $meta = trim($track['date'] . (!empty($track['duration']) ? ' · '.$track['duration'] : ''));
            $out .= '<button type="button" class="aipex-floating-track'.($i===0?' is-active':'').'" data-index="'.esc_attr($i).'" data-audio="'.esc_url($track['url']).'" data-title="'.esc_attr($track['title']).'"><span class="aipex-track-play">▶</span><span class="aipex-track-text"><strong>'.esc_html($track['title']).'</strong>'.($meta?'<small>'.esc_html($meta).'</small>':'').'</span></button>';
        }
        return $out.'</div></div></div>';
    }

    public static function ajax_grid_load_more(){
        check_ajax_referer('aipex_podcast','nonce');
        $page = max(1, absint($_POST['page'] ?? 1)) + 1;
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        if (!is_array($context)) $context = [];
        $limit = max(1, min(48, absint($context['limit'] ?? 12)));
        $q = self::query_episodes(['posts_per_page'=>$limit,'paged'=>$page,'series_id'=>absint($context['series_id'] ?? 0),'presenter_id'=>absint($context['presenter_id'] ?? 0)]);
        $html = '';
        while($q->have_posts()){ $q->the_post(); $html .= Aipex_Podcast_Shortcodes::episode_card(get_the_ID()); }
        $has_more = $q->max_num_pages > $page;
        wp_reset_postdata();
        wp_send_json_success(['html'=>$html,'page'=>$page,'has_more'=>$has_more]);
    }
}
