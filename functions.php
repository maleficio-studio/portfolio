<?php
/**
 * Theme functions and definitions
 *
 * @package MALEFICIO Portfolio
 * @version 1.0.2
 */

// Add support for block templates
add_action('after_setup_theme', function() {
    add_theme_support('block-templates');
});

/**
 * ─────────────────────────────────────────────────────────────
 * Automatic Theme Updates via GitHub Releases
 * Uses YahnisElsts/plugin-update-checker v5.7
 * Repository: https://github.com/maleficio-studio/portfolio
 * ─────────────────────────────────────────────────────────────
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
function portfolio_dynamic_blocks_with_acf( $block_content, $block ) {
    // Only apply on single posts and if ACF is active
    if ( ! is_single() || ! function_exists('get_field') ) {
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
        // Essayer les différentes clés/noms potentiels
        $lien = get_field('field_6abe3d82e717f', $post_id);
        if ( ! $lien ) {
            $lien = get_field('fichiers_livrables', $post_id);
        }
        if ( ! $lien ) {
            $lien = get_field('livrables', $post_id);
        }
        
        if ( $lien ) {
            $url = '';
            $title = 'Voir le livrable';
            $target = '_blank';

            if ( is_array($lien) && isset($lien['url']) ) {
                $url = $lien['url'];
                $title = !empty($lien['title']) ? $lien['title'] : $title;
                $target = !empty($lien['target']) ? $lien['target'] : $target;
            } elseif ( is_string($lien) ) {
                $url = $lien;
            }

            if ( !empty($url) ) {
                // Création de l'iframe de prévisualisation
                $html = '<div style="margin-bottom:20px; width:100%; max-width:100%; margin-top:20px;">';
                $html .= '<iframe src="' . esc_url($url) . '" width="100%" height="600" style="border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" allowfullscreen loading="lazy" title="' . esc_attr($title) . '"></iframe>';
                $html .= '</div>';
                
                // Bouton de secours / accès direct en dessous
                $html .= '<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url($url) . '" target="' . esc_attr($target) . '" rel="noopener noreferrer">' . esc_html($title) . '</a></div></div>';
                
                $block_content = $html;
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    // 8. Apprentissages critiques
    if ( $block['blockName'] === 'core/paragraph' && isset($block['attrs']['className']) && strpos($block['attrs']['className'], 'acf-apprentissages-critiques') !== false ) {
        $field_obj = get_field_object('apprentissages_critiques', $post_id);
        $acs = get_field('apprentissages_critiques', $post_id);
        if ( ! empty($acs) && is_array($acs) && $field_obj ) {
            $html = '<ul class="acf-ac-list">';
            foreach( $acs as $ac_key ) {
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
            foreach( $ressources as $res_key ) {
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
