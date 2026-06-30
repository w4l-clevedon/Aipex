<?php
if (!defined('ABSPATH')) exit;

class Aipex_Podcast_Relationships {
    const EPISODE = 'aipex_podcast';
    const SHOW = 'aipex_series';
    const HOST = 'aipex_presenter';
    const GUEST = 'aipex_guest';
    const SPONSOR = 'aipex_sponsor';

    public static function entity_types(){
        return [self::EPISODE, self::SHOW, self::HOST, self::GUEST, self::SPONSOR];
    }

    public static function id($entity=null){
        if (!$entity) return get_the_ID() ?: 0;
        if (is_numeric($entity)) return absint($entity);
        if (is_object($entity) && isset($entity->ID)) return absint($entity->ID);
        if (is_array($entity) && isset($entity['ID'])) return absint($entity['ID']);
        return 0;
    }

    public static function is_entity($entity){
        $id = self::id($entity);
        return $id && in_array(get_post_type($id), self::entity_types(), true);
    }

    public static function relationship_keys(){
        return [
            self::SHOW => 'series',
            self::HOST => 'presenters',
            self::GUEST => 'guests',
            self::SPONSOR => 'sponsors',
        ];
    }

    public static function key_for_type($post_type){
        $map = self::relationship_keys();
        return $map[$post_type] ?? '';
    }

    public static function episodes($entity=null, $args=[]){
        $id = self::id($entity);
        $type = $id ? get_post_type($id) : '';
        $query = [
            'post_type' => self::EPISODE,
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($type === self::EPISODE) $query['p'] = $id;
        elseif ($type === self::SHOW) $query['meta_query'] = [self::meta_query('series', $id)];
        elseif ($type === self::HOST) $query['meta_query'] = [self::meta_query('presenters', $id)];
        elseif ($type === self::GUEST) $query['meta_query'] = [self::meta_query('guests', $id)];
        elseif ($type === self::SPONSOR) $query['meta_query'] = [self::meta_query('sponsors', $id)];
        else $query['post__in'] = [0];

        return new WP_Query(array_merge($query, $args));
    }

    public static function shows($entity=null, $args=[]){ return self::entities($entity, self::SHOW, $args); }
    public static function hosts($entity=null, $args=[]){ return self::entities($entity, self::HOST, $args); }
    public static function presenters($entity=null, $args=[]){ return self::hosts($entity, $args); }
    public static function guests($entity=null, $args=[]){ return self::entities($entity, self::GUEST, $args); }
    public static function sponsors($entity=null, $args=[]){ return self::entities($entity, self::SPONSOR, $args); }

    public static function entities($entity, $target_type, $args=[]){
        $id = self::id($entity);
        if (!$id || !in_array($target_type, self::entity_types(), true)) return new WP_Query(['post__in'=>[0]]);

        $source_type = get_post_type($id);
        $target_key = self::key_for_type($target_type);

        if ($source_type === $target_type) {
            $ids = [$id];
        } elseif ($source_type === self::EPISODE) {
            $ids = Aipex_Podcast_Fields::ids($target_key, $id);
        } else {
            $ids = self::collect_from_episodes(self::episode_ids($id), $target_key);
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (!$ids) return new WP_Query(['post__in'=>[0]]);

        return new WP_Query(array_merge([
            'post_type' => $target_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $ids,
            'orderby' => 'post__in',
        ], $args));
    }

    public static function episode_ids($entity=null, $args=[]){
        $q = self::episodes($entity, array_merge(['fields'=>'ids','posts_per_page'=>-1], $args));
        return array_values(array_map('absint', (array)$q->posts));
    }

    public static function show_ids($entity=null){ return self::query_ids(self::shows($entity, ['fields'=>'ids'])); }
    public static function host_ids($entity=null){ return self::query_ids(self::hosts($entity, ['fields'=>'ids'])); }
    public static function presenter_ids($entity=null){ return self::host_ids($entity); }
    public static function guest_ids($entity=null){ return self::query_ids(self::guests($entity, ['fields'=>'ids'])); }
    public static function sponsor_ids($entity=null){ return self::query_ids(self::sponsors($entity, ['fields'=>'ids'])); }

    public static function query_ids($query){
        return ($query instanceof WP_Query) ? array_values(array_map('absint', (array)$query->posts)) : [];
    }

    public static function collect_from_episodes($episode_ids, $target_key){
        $ids = [];
        foreach ((array)$episode_ids as $episode_id) {
            $ids = array_merge($ids, Aipex_Podcast_Fields::ids($target_key, $episode_id));
        }
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    public static function meta_query($key, $id){
        return Aipex_Podcast_Fields::relationship_meta_query($key, absint($id));
    }

    public static function all($entity=null){
        return [
            'episodes' => self::episode_ids($entity),
            'shows' => self::show_ids($entity),
            'hosts' => self::host_ids($entity),
            'guests' => self::guest_ids($entity),
            'sponsors' => self::sponsor_ids($entity),
        ];
    }

    public static function current_id(){
        $id = get_queried_object_id();
        if ($id && self::is_entity($id)) return $id;
        return self::id();
    }
}
