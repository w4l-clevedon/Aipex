<?php
if (!defined('ABSPATH')) exit;
class Aipex_Podcast_Post_Types {
    public static function register(){
        register_post_type('aipex_podcast', [
            'labels'=>['name'=>'Podcasts','singular_name'=>'Podcast','add_new_item'=>'Add Podcast Episode','edit_item'=>'Edit Podcast Episode'],
            'public'=>true,'publicly_queryable'=>true,'query_var'=>true,'show_ui'=>true,'show_in_menu'=>true,'menu_icon'=>'dashicons-microphone','has_archive'=>'podcasts',
            'rewrite'=>['slug'=>'podcast','with_front'=>false],'supports'=>['title','editor','thumbnail','excerpt','author','comments'],
            'show_in_rest'=>true,'taxonomies'=>['post_tag']
        ]);
        register_post_type('aipex_series', [
            'labels'=>['name'=>'Podcast Series','singular_name'=>'Series','add_new_item'=>'Add Series','edit_item'=>'Edit Series'],
            'public'=>true,'publicly_queryable'=>true,'query_var'=>true,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=aipex_podcast','has_archive'=>'podcast-series',
            'rewrite'=>['slug'=>'show','with_front'=>false],'supports'=>['title','editor','thumbnail','excerpt'],'show_in_rest'=>true
        ]);
        register_post_type('aipex_presenter', [
            'labels'=>['name'=>'Presenters','singular_name'=>'Presenter','add_new_item'=>'Add Presenter','edit_item'=>'Edit Presenter'],
            'public'=>true,'publicly_queryable'=>true,'query_var'=>true,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=aipex_podcast','has_archive'=>'presenters',
            'rewrite'=>['slug'=>'presenter','with_front'=>false],'supports'=>['title','editor','thumbnail','excerpt'],'show_in_rest'=>true
        ]);
        register_post_type('aipex_guest', [
            'labels'=>['name'=>'Guests','singular_name'=>'Guest'],'public'=>true,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=aipex_podcast','rewrite'=>['slug'=>'guest','with_front'=>false],'supports'=>['title','editor','thumbnail','excerpt'],'show_in_rest'=>true
        ]);
        register_post_type('aipex_sponsor', [
            'labels'=>['name'=>'Sponsors','singular_name'=>'Sponsor'],'public'=>true,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=aipex_podcast','rewrite'=>['slug'=>'sponsor','with_front'=>false],'supports'=>['title','editor','thumbnail','excerpt'],'show_in_rest'=>true
        ]);
    }
}
