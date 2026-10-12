<?php
/**
 * Theme functions and definitions
 *
 * @package MALEFICIO Portfolio
 * @version 1.2.3
 */

// Add support for block templates
add_action('after_setup_theme', function() {
    add_theme_support('block-templates');
});

/**
 * Automatic Theme Updates via GitHub Releases
 * Uses YahnisElsts/plugin-update-checker v5.7
 * Repository: https://github.com/maleficio-studio/portfolio
 */
require get_template_directory() . '/vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p7\PucFactory;

$maleficio_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/maleficio-studio/portfolio/',
    __FILE__,
    'maleficio-portfolio'
);

// Use GitHub Releases as the source of updates
$maleficio_update_checker->setBranch('main');

/**
 * Filter block output to replace content with ACF data
 */
function portfolio_get_livrable_field_value( $post_id ) {
    $field_names = array( 'field_6abe3d82e717f', 'fichiers_livrables', 'livrables' );

    if ( function_exists( 'get_field' ) ) {
        foreach ( $field_names as $field_name ) {
            $livrable = portfolio_normalize_livrable( get_field( $field_name, $post_id ) );
            if ( $livrable ) {
                return $livrable;
            }
        }

        if ( function_exists( 'get_field_objects' ) ) {
            $fields = get_field_objects( $post_id );
            if ( is_array( $fields ) ) {
                foreach ( $fields as $field ) {
                    $field_name = $field['name'] ?? '';
                    $field_label = $field['label'] ?? '';
                    if ( false === stripos( $field_name . ' ' . $field_label, 'livrable' ) ) {
                        continue;
                    }

                    $livrable = portfolio_normalize_livrable( $field['value'] ?? null );
                    if ( $livrable ) {
                        return $livrable;
                    }
                }
            }
        }
    }

    foreach ( array( 'fichiers_livrables', 'livrables' ) as $meta_key ) {
        $livrable = portfolio_normalize_livrable( get_post_meta( $post_id, $meta_key, true ) );
        if ( $livrable ) {
            return $livrable;
        }
    }

    return null;
}

function portfolio_normalize_livrable( $value ) {
    $title = 'Voir le livrable';
    $target = '_blank';

    if ( is_array( $value ) ) {
        $title = ! empty( $value['title'] ) ? $value['title'] : $title;
        $target = ! empty( $value['target'] ) ? $value['target'] : $target;
    }

    $url = portfolio_extract_livrable_url( $value );
    if ( ! $url && is_numeric( $value ) && function_exists( 'wp_get_attachment_url' ) ) {
        $url = wp_get_attachment_url( (int) $value ) ?: '';
    }

    $url = esc_url_raw( trim( $url ) );
    $parsed_url = wp_parse_url( $url );
    if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) || ! in_array( $parsed_url['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
        return null;
    }

    return array(
        'url'    => $url,
        'title'  => $title,
        'target' => $target,
    );
}

function portfolio_extract_livrable_url( $value ) {
    if ( is_object( $value ) ) {
        $value = (array) $value;
    }

    if ( is_array( $value ) ) {
        foreach ( array( 'url', 'value', 'ID', 'id' ) as $key ) {
            if ( isset( $value[ $key ] ) ) {
                $url = portfolio_extract_livrable_url( $value[ $key ] );
                if ( $url ) {
                    return $url;
                }
            }
        }

        foreach ( $value as $key => $nested_value ) {
            if ( in_array( $key, array( 'title', 'target', 'label' ), true ) ) {
                continue;
            }

            $url = portfolio_extract_livrable_url( $nested_value );
            if ( $url ) {
                return $url;
            }
        }

        return '';
    }

    if ( is_numeric( $value ) && function_exists( 'wp_get_attachment_url' ) ) {
        return wp_get_attachment_url( (int) $value ) ?: '';
    }

    if ( ! is_string( $value ) || '' === trim( $value ) ) {
        return '';
    }

    $url = esc_url_raw( trim( $value ) );
    $parsed_url = wp_parse_url( $url );
    if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) || ! in_array( $parsed_url['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
        return '';
    }

    return $url;
}

function portfolio_livrable_embed_url( $url ) {
    $parsed_url = wp_parse_url( $url );
    if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
        return $url;
    }

    $host = strtolower( $parsed_url['host'] );
    if ( 'drive.google.com' === $host && preg_match( '#/file/d/([^/]+)#', $parsed_url['path'] ?? '', $matches ) ) {
        return 'https://drive.google.com/file/d/' . rawurlencode( $matches[1] ) . '/preview';
    }

    if ( 'drive.google.com' === $host && ! empty( $parsed_url['query'] ) ) {
        parse_str( $parsed_url['query'], $query_args );
        if ( ! empty( $query_args['id'] ) ) {
            return 'https://drive.google.com/file/d/' . rawurlencode( $query_args['id'] ) . '/preview';
        }
    }

    if ( 'docs.google.com' === $host && preg_match( '#/(document|spreadsheets|presentation)/(?:u/\d+/)?d/([^/]+)#', $parsed_url['path'] ?? '', $matches ) ) {
        return 'https://docs.google.com/' . $matches[1] . '/d/' . rawurlencode( $matches[2] ) . '/preview';
    }

    return $url;
}

function portfolio_dynamic_blocks_with_acf( $block_content, $block ) {
    // Only apply on single posts and if ACF is active
    if ( ! is_singular() || ! function_exists('get_field') ) {
        return $block_content;
    }

    $post_id = get_the_ID();

    // 0. Titre principal
    if ( $block['blockName'] === 'core/post-title' ) {
        $acf_titre = get_field('titre', $post_id);
        if ( ! empty($acf_titre) ) {
            // Remplace le texte à l'intérieur de la balise H1, H2, etc. générée par le bloc
            $block_content = preg_replace('/(<h[1-6][^>]*>).*?(<\/h[1-6]>)/s', '${1}' . esc_html($acf_titre) . '${2}', $block_content);
        }
    }

    // 1. Sous-titre
    if ( $block['blockName'] === 'core/heading' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-sous-titre') !== false ) {
        $sous_titre = get_field('sous-titre', $post_id);
        if ( ! empty($sous_titre) ) {
            // Replace inner HTML of the heading tag
            $block_content = preg_replace('/(<h[1-6][^>]*>).*?(<\/h[1-6]>)/s', '${1}' . esc_html($sous_titre) . '${2}', $block_content);
        } else {
            return ''; // Hide if empty
        }
    }

    // 2. Description
    if ( $block['blockName'] === 'core/paragraph' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-description') !== false ) {
        $description = get_field('description', $post_id);
        if ( ! empty($description) ) {
            $block_content = preg_replace('/(<p[^>]*>).*?(<\/p>)/s', '${1}' . wp_kses_post($description) . '${2}', $block_content);
        } else {
            return ''; // Hide if empty
        }
    }

    // 3. Kadence Countup (Durée)
    if ( $block['blockName'] === 'kadence/countup' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-duree') !== false ) {
        $duree = get_field('duree', $post_id);
        if ( $duree !== '' && $duree !== false ) {
            $block_content = preg_replace('/data-end="[^"]*"/', 'data-end="' . esc_attr($duree) . '"', $block_content);
        } else {
            return ''; // Hide if empty
        }
    }

    // 4. Kadence Countup (Livrables)
    if ( $block['blockName'] === 'kadence/countup' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-nombre-livrables') !== false ) {
        $livrables = get_field('field_6abe3d19e717b', $post_id);
        if ( $livrables === '' || $livrables === false || $livrables === null ) {
            $livrables = get_field('nombre_livrables', $post_id);
        }
        if ( $livrables === '' || $livrables === false || $livrables === null ) {
            $livrables = get_field('livrables', $post_id);
        }

        if ( $livrables !== '' && $livrables !== false && $livrables !== null ) {
            $block_content = preg_replace('/data-end="[^"]*"/', 'data-end="' . esc_attr($livrables) . '"', $block_content);
        } else {
            return '';
        }
    }

    // 5. Kadence Countup (Équipe)
    if ( $block['blockName'] === 'kadence/countup' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-equipe') !== false ) {
        $equipe = get_field('equipe', $post_id);
        if ( $equipe !== '' && $equipe !== false ) {
            $block_content = preg_replace('/data-end="[^"]*"/', 'data-end="' . esc_attr($equipe) . '"', $block_content);
        } else {
            return '';
        }
    }

    // 6. Déroulement
    if ( $block['blockName'] === 'core/paragraph' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-deroulement') !== false ) {
        $deroulement = get_field('deroulement', $post_id);
        if ( ! empty($deroulement) ) {
            $clean = wp_kses_post($deroulement);
            $formatted = wpautop($clean);
            $block_content = '<div class="acf-deroulement">' . $formatted . '</div>';
        } else {
            return '';
        }
    }

    // 7. Fichiers livrables (Lien)
    if ( $block['blockName'] === 'core/paragraph' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-fichiers-livrables') !== false ) {
        $lien = portfolio_get_livrable_field_value( $post_id );

        if ( ! $lien ) {
            return '';
        }

        $url = $lien['url'];
        $title = ! empty( $lien['title'] ) ? $lien['title'] : 'Voir le livrable';
        $embed_url = portfolio_livrable_embed_url( $url );
        $block_content = '<div class="acf-livrable-preview" style="width: 100%; height: 800px;">
        <iframe
            src="' . esc_url( $embed_url ) . '"
            title="' . esc_attr( $title ) . '"
            loading="lazy"
            allowfullscreen
            style="width: 100%; height: 100%; border: 0; display: block;"
        ></iframe>
        </div>';
        $block_content .= '<p class="acf-livrable-preview-link"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a></p>';
    }

    // 8. Apprentissages critiques
    if ( $block['blockName'] === 'core/paragraph' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-apprentissages-critiques') !== false ) {
        $field_obj = get_field_object('apprentissages_critiques', $post_id);
        $acs = get_field('apprentissages_critiques', $post_id);
        if ( ! empty($acs) && is_array($acs) && $field_obj ) {
            $html = '<ul class="acf-ac-list">';
            foreach ( $acs as $ac_key ) {
                $label = isset($field_obj['choices'][$ac_key]) ? $field_obj['choices'][$ac_key] : $ac_key;
                $display = $label;
                $html .= '<li>' . esc_html($display) . '</li>';
            }
            $html .= '</ul>';
            $block_content = $html;
        } else {
            return '';
        }
    }

    // 9. Ressources mobilisées
    if ( $block['blockName'] === 'core/paragraph' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-ressources-mobilisees') !== false ) {
        $field_obj = get_field_object('ressources_mobilisees', $post_id);
        $ressources = get_field('ressources_mobilisees', $post_id);
        if ( ! empty($ressources) && is_array($ressources) && $field_obj ) {
            $html = '<ul class="acf-rm-list">';
            foreach ( $ressources as $res_key ) {
                $label = isset($field_obj['choices'][$res_key]) ? $field_obj['choices'][$res_key] : $res_key;
                $display = $label;
                $html .= '<li>' . esc_html($display) . '</li>';
            }
            $html .= '</ul>';
            $block_content = $html;
        } else {
            return '';
        }
    }

    return $block_content;
}
add_filter( 'render_block', 'portfolio_dynamic_blocks_with_acf', 10, 2 );