<?php

namespace Aipex_DGP\Widgets;

defined( 'ABSPATH' ) || exit;

final class Hotspot_Photo_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'aipex_hotspot_photo';
    }

    public function get_title(): string {
        return __( 'Aipex Hotspot Group Photograph', 'aipex-dropbox-gallery-print' );
    }

    public function get_icon(): string {
        return 'eicon-image-hotspot';
    }

    public function get_categories(): array {
        return array( 'general' );
    }

    public function get_style_depends(): array {
        return array( 'aipex-dgp-gallery' );
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'content_section',
            array( 'label' => __( 'Photograph', 'aipex-dropbox-gallery-print' ) )
        );

        $this->add_control(
            'photo_id',
            array(
                'label'   => __( 'Gallery photograph', 'aipex-dropbox-gallery-print' ),
                'type'    => \Elementor\Controls_Manager::SELECT2,
                'options' => $this->get_photo_options(),
            )
        );

        $this->add_control(
            'show_purchase',
            array(
                'label'        => __( 'Show purchase button', 'aipex-dropbox-gallery-print' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $photo_id = absint( $settings['photo_id'] ?? 0 );
        if ( ! $photo_id ) {
            echo '<p>' . esc_html__( 'Select a group photograph.', 'aipex-dropbox-gallery-print' ) . '</p>';
            return;
        }

        $image_url = (string) get_post_meta( $photo_id, '_aipex_full_url', true );
        if ( ! $image_url ) {
            $image_url = get_the_post_thumbnail_url( $photo_id, 'full' ) ?: '';
        }
        if ( ! $image_url ) {
            echo '<p>' . esc_html__( 'The selected photograph has no image.', 'aipex-dropbox-gallery-print' ) . '</p>';
            return;
        }

        $hotspots = get_post_meta( $photo_id, '_aipex_hotspots', true );
        $hotspots = is_array( $hotspots ) ? $hotspots : array();

        echo '<div class="aipex-dgp-hotspot-stage">';
        echo '<img class="aipex-dgp-hotspot-image" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( get_the_title( $photo_id ) ) . '">';

        foreach ( $hotspots as $index => $hotspot ) {
            $name   = sanitize_text_field( $hotspot['name'] ?? '' );
            $role   = sanitize_text_field( $hotspot['role'] ?? '' );
            $x      = max( 0, min( 1, (float) ( $hotspot['x'] ?? 0 ) ) );
            $y      = max( 0, min( 1, (float) ( $hotspot['y'] ?? 0 ) ) );
            $width  = max( 0.01, min( 1, (float) ( $hotspot['width'] ?? 0.04 ) ) );
            $height = max( 0.01, min( 1, (float) ( $hotspot['height'] ?? 0.08 ) ) );
            if ( '' === $name ) {
                continue;
            }

            $style = sprintf(
                'left:%1$.4f%%;top:%2$.4f%%;width:%3$.4f%%;height:%4$.4f%%;',
                $x * 100,
                $y * 100,
                $width * 100,
                $height * 100
            );
            $label = $role ? $name . ', ' . $role : $name;

            echo '<button type="button" class="aipex-dgp-hotspot" style="' . esc_attr( $style ) . '" aria-label="' . esc_attr( $label ) . '">';
            echo '<span class="aipex-dgp-hotspot-tooltip"><strong>' . esc_html( $name ) . '</strong>';
            if ( $role ) {
                echo '<br>' . esc_html( $role );
            }
            echo '</span></button>';
        }
        echo '</div>';

        $gallery_id = (int) get_post_meta( $photo_id, '_aipex_gallery_id', true );
        $product_id = (int) get_post_meta( $gallery_id, '_aipex_product_id', true );
        if ( 'yes' === ( $settings['show_purchase'] ?? '' ) && $product_id ) {
            $url = add_query_arg( 'aipex_photo_id', $photo_id, get_permalink( $product_id ) );
            echo '<p><a class="button aipex-dgp-buy" href="' . esc_url( $url ) . '">' . esc_html__( 'Buy this photograph', 'aipex-dropbox-gallery-print' ) . '</a></p>';
        }
    }

    private function get_photo_options(): array {
        $options = array( '' => __( 'Select photograph', 'aipex-dropbox-gallery-print' ) );
        foreach ( get_posts( array( 'post_type' => 'aipex_photo', 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $photo ) {
            $options[ $photo->ID ] = $photo->post_title;
        }
        return $options;
    }
}
