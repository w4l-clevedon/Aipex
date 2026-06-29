<?php
if (!defined('ABSPATH')) exit;
class Aipex_Podcast_Utils {
    public static function aliases($key){
        $map = [
            'audio_file' => ['aipex_audio_file','ovau_audio_url'],
            'dropbox_url' => ['aipex_dropbox_url','dropbox_file_url'],
            'soundcloud_url' => ['aipex_soundcloud_url','ovau_soundcloud_url'],
            'episode_summary' => ['aipex_episode_summary','summary'],
            'main_points' => ['aipex_main_points','aipex_main_points_covered'],
            'transcript' => ['aipex_transcript'],
            'series' => ['aipex_series','podcast_series','series_id'],
            'presenters' => ['aipex_presenters','presenter','presenter_id','ovau_host_id'],
            'guests' => ['aipex_guests'],
            'sponsors' => ['aipex_sponsors'],
            'series_overview' => ['aipex_series_about','aipex_series_details'],
            'series_main_points' => ['aipex_series_main_points'],
            'series_episode_summaries' => ['aipex_series_episode_summaries'],
            'presenter_about' => ['aipex_presenter_about','ovau_host_about','about'],
            'rss_url' => ['aipex_series_rss','series_rss','rss_feed','rss','aipex_presenter_rss'],
            'spotify_url' => ['aipex_series_spotify','series_spotify','spotify','aipex_presenter_spotify'],
            'apple_url' => ['aipex_series_apple','series_apple','apple_podcasts_url','apple','aipex_presenter_apple'],
            'youtube_url' => ['aipex_series_youtube','series_youtube','youtube','aipex_presenter_youtube'],
            'amazon_url' => ['aipex_series_amazon','series_amazon','amazon_music_url','amazon','aipex_presenter_amazon'],
            'pocketcasts_url' => ['aipex_series_pocketcasts','series_pocketcasts','pocket_casts_url','pocketcasts','aipex_presenter_pocketcasts'],
            'website' => ['aipex_series_website','series_website','aipex_presenter_website','presenter_website','aipex_sponsor_website'],
        ];
        return $map[$key] ?? [];
    }

    public static function field($key,$post_id=null,$default=''){
        $post_id = $post_id ?: get_the_ID();
        $keys = array_merge([$key], self::aliases($key));
        foreach ($keys as $k) {
            if (function_exists('get_field')) {
                $v = get_field($k,$post_id);
                if ($v!==null && $v!==false && $v!=='' && $v!==[]) return $v;
            }
            $v = get_post_meta($post_id,$k,true);
            if ($v!=='' && $v!==null && $v!==[]) return $v;
        }
        return $default;
    }

    public static function update_field($key,$value,$post_id){
        if (function_exists('update_field')) {
            $ok = update_field($key,$value,$post_id);
            if ($ok) return $ok;
        }
        return update_post_meta($post_id,$key,$value);
    }

    public static function ids($key,$post_id=null){
        $v = self::field($key,$post_id,[]);
        return self::normalise_ids($v);
    }

    public static function normalise_ids($value){
        if (!$value && $value !== 0 && $value !== '0') return [];
        if (is_numeric($value)) return [(int)$value];
        if (is_object($value) && isset($value->ID)) return [(int)$value->ID];
        if (is_string($value)) {
            $maybe = maybe_unserialize($value);
            if ($maybe !== $value) return self::normalise_ids($maybe);
            if (preg_match_all('/\d+/', $value, $m)) return array_values(array_unique(array_map('intval', $m[0])));
            return [];
        }
        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) $ids = array_merge($ids, self::normalise_ids($item));
            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        }
        return [];
    }

    public static function audio_url($post_id=null){
        $post_id=$post_id?:get_the_ID();
        $file=self::field('audio_file',$post_id);
        if (is_array($file) && !empty($file['url'])) return $file['url'];
        if (is_object($file) && !empty($file->ID)) return wp_get_attachment_url((int)$file->ID);
        if (is_numeric($file)) return wp_get_attachment_url((int)$file);
        if (is_string($file) && preg_match('~^https?://~',$file)) return $file;
        $drop=self::field('dropbox_url',$post_id); if ($drop) return self::dropbox_direct($drop);
        $sc=self::field('soundcloud_url',$post_id); if ($sc) return $sc;
        return '';
    }

    public static function dropbox_direct($url){ return preg_replace('~\?dl=0$~','?raw=1', str_replace('www.dropbox.com','dl.dropboxusercontent.com',$url)); }
    public static function normalize($s){ $s=strtolower(wp_strip_all_tags((string)$s)); $s=preg_replace('/\.[a-z0-9]{2,5}$/','',$s); $s=preg_replace('/[^a-z0-9]+/',' ',$s); return trim($s); }
    public static function match_score($a,$b){ $a=self::normalize($a); $b=self::normalize($b); if(!$a||!$b)return 0; similar_text($a,$b,$p); if(str_contains($a,$b)||str_contains($b,$a)) $p=max($p,92); return (int)round($p); }

    public static function current_context($type){
        if (is_singular($type)) return get_the_ID();
        $qid = get_queried_object_id();
        if ($qid && get_post_type($qid)===$type) return $qid;
        global $post;
        return ($post && isset($post->ID) && get_post_type($post->ID)===$type) ? (int)$post->ID : 0;
    }

    public static function resolve_context_id($type, $atts=[], $keys=[]){
        foreach ($keys as $key) {
            if (!empty($atts[$key])) {
                $raw = $atts[$key];
                if (is_numeric($raw)) return (int)$raw;
                $p = get_page_by_path(sanitize_title($raw), OBJECT, $type);
                if ($p) return (int)$p->ID;
            }
        }
        return self::current_context($type);
    }

    public static function relationship_meta_query_for_id($key,$id){
        $id=(int)$id;
        $keys=array_merge([$key], self::aliases($key));
        $or=['relation'=>'OR'];
        foreach($keys as $k){
            $or[]=['key'=>$k,'value'=>'"'.$id.'"','compare'=>'LIKE'];
            $or[]=['key'=>$k,'value'=>'i:'.$id.';','compare'=>'LIKE'];
            $or[]=['key'=>$k,'value'=>'s:'.strlen((string)$id).':"'.$id.'";','compare'=>'LIKE'];
            $or[]=['key'=>$k,'value'=>(string)$id,'compare'=>'='];
        }
        return $or;
    }

    public static function query_episodes($args=[]){
        $meta=[];
        if (!empty($args['series_id'])) $meta[] = self::relationship_meta_query_for_id('series', (int)$args['series_id']);
        if (!empty($args['presenter_id'])) $meta[] = self::relationship_meta_query_for_id('presenters', (int)$args['presenter_id']);
        $base=['post_type'=>'aipex_podcast','posts_per_page'=>12,'post_status'=>'publish','orderby'=>'date','order'=>'DESC'];
        if ($meta) $base['meta_query'] = count($meta)>1 ? array_merge(['relation'=>'AND'],$meta) : $meta;
        unset($args['series_id'],$args['presenter_id']);
        return new WP_Query(array_merge($base,$args));
    }
    public static function button($url,$text){ return '<a class="aipex-btn" href="'.esc_url($url).'"><span class="aipex-btn-icon" aria-hidden="true">♫</span> '.esc_html($text).'</a>'; }
}
