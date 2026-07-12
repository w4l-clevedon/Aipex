<?php

namespace Aipex_DGP\Widgets;

defined( 'ABSPATH' ) || exit;

final class Masonry_Gallery_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'aipex_dropbox_masonry_gallery';
    }

    public function get_title(): string {
        return __( 'Aipex Dropbox Masonry Gallery', 'aipex-dropbox-gallery-print' );
    }

    public function get_icon(): string {
        return 'eicon-gallery-masonry';
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
            array( 'label' => __( 'Gallery', 'aipex-dropbox-gallery-print' ) )
        );

        $this->add_control(
            'gallery_id',
            array(
                'label'   => __( 'Dropbox gallery', 'aipex-dropbox-gallery-print' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_gallery_options(),
            )
        );

        $this->add_responsive_control(
            'columns',
            array(
                'label'   => __( 'Columns', 'aipex-dropbox-gallery-print' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 8,
                'default' => 4,
                'selectors' => array(
                    '{{WRAPPER}} .aipex-dgp-grid' => 'column-count: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'show_captions',
            array(
                'label'        => __( 'Show captions', 'aipex-dropbox-gallery-print' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
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
        $settings   = $this->get_settings_for_display();
        $gallery_id = absint( $settings['gallery_id'] ?? 0 );

        if ( ! $gallery_id ) {
            echo '<p>' . esc_html__( 'Select a Dropbox gallery.', 'aipex-dropbox-gallery-print' ) . '</p>';
            return;
        }

        $photos = get_posts(
            array(
                'post_type'      => 'aipex_photo',
                'posts_per_page' => 100,
                'post_status'    => 'publish',
                'meta_key'       => '_aipex_gallery_id',
                'meta_value'     => $gallery_id,
                'orderby'        => $this->get_orderby( $gallery_id ),
                'order'          => $this->get_order( $gallery_id ),
            )
        );

        if ( ! $photos ) {
            echo '<p>' . esc_html__( 'This gallery has no synchronised photographs yet.', 'aipex-dropbox-gallery-print' ) . '</p>';
            return;
        }

        echo '<div class="aipex-dgp-grid">';
        foreach ( $photos as $photo ) {
            $preview_url = (string) get_post_meta( $photo->ID, '_aipex_preview_url', true );
            $full_url    = (string) get_post_meta( $photo->ID, '_aipex_full_url', true );
            $product_id  = (int) get_post_meta( $gallery_id, '_aipex_product_id', true );

            if ( ! $preview_url ) {
                $preview_url = get_the_post_thumbnail_url( $photo->ID, 'large' ) ?: '';
            }
            if ( ! $full_url ) {
                $full_url = $preview_url;
            }
            if ( ! $preview_url ) {
                continue;
            }

            echo '<figure class="aipex-dgp-item">';
            echo '<a class="aipex-dgp-lightbox" href="' . esc_url( $full_url ) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-title="' . esc_attr( get_the_title( $photo ) ) . '">';
            echo '<img loading="lazy" src="' . esc_url( $preview_url ) . '" alt="' . esc_attr( get_the_title( $photo ) ) . '">';
            echo '</a>';

            if ( 'yes' === ( $settings['show_captions'] ?? '' ) ) {
                echo '<figcaption>' . esc_html( get_the_title( $photo ) ) . '</figcaption>';
            }

            if ( 'yes' === ( $settings['show_purchase'] ?? '' ) && $product_id ) {
                $purchase_url = add_query_arg( 'aipex_photo_id', $photo->ID, get_permalink( $product_id ) );
                echo '<a class="button aipex-dgp-buy" href="' . esc_url( $purchase_url ) . '">' . esc_html__( 'Buy this photograph', 'aipex-dropbox-gallery-print' ) . '</a>';
            }
            echo '</figure>';
        }
        echo '</div>';
    }

    private function get_gallery_options(): array {
        $options = array( '' => __( 'Select gallery', 'aipex-dropbox-gallery-print' ) );
        foreach ( get_posts( array( 'post_type' => 'aipex_gallery', 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $gallery ) {
            $options[ $gallery->ID ] = $gallery->post_title;
        }
        return $options;
    }

    private function get_orderby( int $gallery_id ): string {
        $sort = (string) get_post_meta( $gallery_id, '_aipex_sort', true );
        return str_starts_with( $sort, 'date_' ) ? 'date' : 'title';
    }

    private function get_order( int $gallery_id ): string {
        $sort = (string) get_post_meta( $gallery_id, '_aipex_sort', true );
        return str_ends_with( $sort, '_desc' ) ? 'DESC' : 'ASC';
    }
}
