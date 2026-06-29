<?php
if (!defined('ABSPATH')) exit;
if (class_exists('\\Elementor\\Widget_Base') && !class_exists('Aipex_Podcast_Elementor_Shortcode_Widget')) {
    abstract class Aipex_Podcast_Elementor_Shortcode_Widget extends \Elementor\Widget_Base {
        public function get_categories(){ return ['aipex-podcast-system']; }
        protected function register_controls(){
            $this->start_controls_section('content',['label'=>'Content']);
            $this->add_control('limit',['label'=>'Limit','type'=>\Elementor\Controls_Manager::NUMBER,'default'=>12]);
            $this->end_controls_section();
        }
        abstract public function shortcode_name();
        protected function render(){ $s=$this->get_settings_for_display(); echo do_shortcode('['.$this->shortcode_name().' limit="'.intval($s['limit']??12).'"]'); }
    }
}
class Aipex_Podcast_Elementor {
    public static function init(){ add_action('elementor/widgets/register',[__CLASS__,'register']); add_action('elementor/elements/categories_registered',[__CLASS__,'category']); }
    public static function category($manager){ if(method_exists($manager,'add_category')) $manager->add_category('aipex-podcast-system',['title'=>'Aipex Podcast System','icon'=>'fa fa-microphone']); }
    public static function register($widgets_manager){
        if(!class_exists('\\Elementor\\Widget_Base')) return;
        if(!class_exists('Aipex_Podcast_Elementor_Shortcode_Widget')) return;
        $defs=[
            'Aipex_Widget_Player'=>['Podcast Player','aipex_podcast_player'],
            'Aipex_Widget_Episode_Grid'=>['Podcast Episode Grid','aipex_podcast_grid'],
            'Aipex_Widget_Show_Episodes'=>['Show Episodes','aipex_show_podcasts'],
            'Aipex_Widget_Presenter_Podcasts'=>['Presenter Podcasts','aipex_presenter_podcasts'],
            'Aipex_Widget_Series_Grid'=>['Series Grid','aipex_series_grid'],
            'Aipex_Widget_Presenter_Grid'=>['Presenter Grid','aipex_presenter_grid'],
            'Aipex_Widget_Guest_Grid'=>['Guest Grid','aipex_guest_grid'],
            'Aipex_Widget_Summary'=>['Episode Summary','aipex_podcast_summary'],
            'Aipex_Widget_Main_Points'=>['Main Points','aipex_podcast_main_points'],
            'Aipex_Widget_Transcript'=>['Transcript','aipex_podcast_transcript'],
            'Aipex_Widget_Series_Details'=>['Series Details','aipex_series_details'],
            'Aipex_Widget_Series_Main_Points'=>['Series Main Topics','aipex_series_main_points'],
            'Aipex_Widget_Series_Episode_Summaries'=>['Series Episode Summaries','aipex_series_episode_summaries'],
            'Aipex_Widget_Presenter_About'=>['Presenter About','aipex_presenter_about'],
            'Aipex_Widget_Presenter_Box'=>['Presenter Box','aipex_presenter_box'],
            'Aipex_Widget_Presenter_Links'=>['Presenter Links','aipex_presenter_links'],
            'Aipex_Widget_Subscribe'=>['Subscribe Links','aipex_subscribe'],
            'Aipex_Widget_Sponsors'=>['Sponsors','aipex_sponsors'],
            'Aipex_Widget_Sponsor_Grid'=>['Sponsor Grid','aipex_sponsor_grid'],
            'Aipex_Widget_Show_Summary'=>['Show Summary','aipex_show_summary'],
            'Aipex_Widget_Show_Main_Topics'=>['Show Main Topics','aipex_show_main_topics'],
            'Aipex_Widget_Episode_Series'=>['Episode Series','aipex_episode_series'],
            'Aipex_Widget_Nav'=>['Next Previous Navigation','aipex_next_previous'],
            'Aipex_Widget_Floating'=>['Floating Player','aipex_floating_player'],
        ];
        foreach($defs as $class=>$pair){
            if(!class_exists($class)){
                $title=addslashes($pair[0]); $sc=$pair[1]; $name=strtolower($class);
                eval('class '.$class.' extends Aipex_Podcast_Elementor_Shortcode_Widget { public function get_name(){return "'.$name.'";} public function get_title(){return "'.$title.'";} public function get_icon(){return "eicon-play";} public function shortcode_name(){return "'.$sc.'";} }');
            }
            $widgets_manager->register(new $class());
        }
    }
}
